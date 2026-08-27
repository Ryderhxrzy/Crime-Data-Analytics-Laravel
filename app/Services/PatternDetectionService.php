<?php

namespace App\Services;

use App\Models\CrimeIncident;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Pattern detection over real crime records, with an optional simulation layer.
 *
 * Two hard rules drive the design:
 *   1. Simulation is OFF by default. With it off, nothing synthetic can reach the
 *      output — every record carries is_simulated and the flag is set at the source.
 *   2. Every figure is reported alongside the sample size it came from, so a
 *      "pattern" drawn from four incidents cannot be mistaken for a real signal.
 *
 * Aggregation is grid/bucket based (single pass, O(n)) so it scales past the
 * current dataset without holding pairwise distances in memory.
 */
class PatternDetectionService
{
    /** Hotspot grid cell, in degrees (~165 m at this latitude) */
    private const HOTSPOT_CELL = 0.0015;

    /** A repeat cluster = incidents within this radius AND this many hours */
    private const REPEAT_RADIUS_M = 250;
    private const REPEAT_WINDOW_HOURS = 72;

    /** A day is anomalous past this many standard deviations from the mean */
    private const ANOMALY_Z = 2.0;

    /** Below this many records, patterns are reported but marked low confidence */
    private const LOW_CONFIDENCE_N = 30;

    public function analyze(array $options = []): array
    {
        $days = (int) ($options['days'] ?? 180);
        $simulationOn = (bool) ($options['simulation'] ?? false);
        $scenarios = $options['scenarios'] ?? [];

        $real = $this->loadRealIncidents($days);

        $simulated = collect();
        $genStats = [];
        if ($simulationOn) {
            $generator = new PatternSimulationGenerator();
            $simulated = $generator->generate($real, $scenarios, $days);
            $genStats = $generator->stats;
        }

        $all = $real->concat($simulated);

        // Echo the scenario back to the client without the bulky street geometry,
        // and attach the prevention outcome so the UI can report it.
        $scenariosMeta = $scenarios;
        unset($scenariosMeta['street_points']);
        if ($simulationOn) {
            $scenariosMeta['prevention_result'] = [
                'active'    => $genStats['active_interventions'] ?? [],
                'percent'   => $genStats['prevention_percent'] ?? 0,
                'prevented' => $genStats['prevented'] ?? 0,
                'target'    => $genStats['target'] ?? 0,
            ];
        }

        return [
            'meta' => [
                'generated_at'       => now()->toIso8601String(),
                'period_days'        => $days,
                'period_start'       => now()->subDays($days)->toDateString(),
                'period_end'         => now()->toDateString(),
                'simulation_enabled' => $simulationOn,
                'scenarios'          => $simulationOn ? $scenariosMeta : [],
                'real_count'         => $real->count(),
                'simulated_count'    => $simulated->count(),
                'total_count'        => $all->count(),
                'low_confidence'     => $all->count() < self::LOW_CONFIDENCE_N,
                'confidence_note'    => $all->count() < self::LOW_CONFIDENCE_N
                    ? 'Fewer than ' . self::LOW_CONFIDENCE_N . ' records in this period — treat every pattern below as indicative only.'
                    : null,
            ],
            'trends'            => $this->crimeTrends($all, $days),
            'hotspots'          => $this->hotspots($all),
            'time_patterns'     => $this->timePatterns($all),
            'type_distribution' => $this->typeDistribution($all),
            'repeat_clusters'   => $this->repeatClusters($all),
            'anomalies'         => $this->anomalies($all, $days),
            'insights'          => [],   // filled below, needs the sections above
        ];
    }

    /** Runs analyze() then derives plain-language insights from the result */
    public function analyzeWithInsights(array $options = []): array
    {
        $result = $this->analyze($options);
        $result['insights'] = $this->insights($result);

        return $result;
    }

    // ---------------------------------------------------------------- loading

    private function loadRealIncidents(int $days): Collection
    {
        return CrimeIncident::query()
            ->where('incident_date', '>=', now()->subDays($days))
            ->get([
                'id', 'incident_code', 'incident_title', 'incident_date', 'incident_time',
                'latitude', 'longitude', 'category_name', 'barangay_name', 'record_type',
                'status', 'clearance_status',
            ])
            ->map(function ($i) {
                $date = $i->incident_date instanceof Carbon
                    ? $i->incident_date
                    : Carbon::parse($i->incident_date);

                return [
                    'id'            => $i->id,
                    'incident_code' => $i->incident_code,
                    'title'         => $i->incident_title,
                    'date'          => $date->toDateString(),
                    'hour'          => $this->hourOf($i->incident_time),
                    'dow'           => $date->dayOfWeek,           // 0 = Sunday
                    'latitude'      => (float) $i->latitude,
                    'longitude'     => (float) $i->longitude,
                    'category'      => $i->category_name ?: 'Uncategorized',
                    'barangay'      => $i->barangay_name,
                    'record_type'   => $i->record_type ?: 'crime',
                    'is_simulated'  => false,
                ];
            })
            ->values();
    }

    private function hourOf($time): ?int
    {
        if (!$time) {
            return null;
        }
        if (preg_match('/^(\d{1,2}):/', (string) $time, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function formatHour12(int $hour, int $minute = 0): string
    {
        $suffix = $hour < 12 ? 'AM' : 'PM';
        $displayHour = $hour % 12 ?: 12;

        return sprintf('%d:%02d %s', $displayHour, $minute, $suffix);
    }

    // ----------------------------------------------------------------- trends

    private function crimeTrends(Collection $all, int $days): array
    {
        $daily   = $this->splitByBucket($all, fn ($i) => $i['date']);
        $weekly  = $this->splitByBucket($all, fn ($i) => Carbon::parse($i['date'])->startOfWeek()->toDateString());
        $monthly = $this->splitByBucket($all, fn ($i) => Carbon::parse($i['date'])->format('Y-m'));

        // Every day/week/month in the window gets a slot, so a gap in the data
        // reads as a zero rather than silently collapsing the axis.
        $dayLabels = $weekLabels = $monthLabels = [];
        $cursor = now()->subDays($days)->startOfDay();
        $end = now()->startOfDay();
        while ($cursor->lte($end)) {
            $dayLabels[] = $cursor->toDateString();
            // copy() matters: startOfWeek() mutates in place and would rewind the cursor
            $weekLabels[$cursor->copy()->startOfWeek()->toDateString()] = true;
            $monthLabels[$cursor->format('Y-m')] = true;
            $cursor->addDay();
        }
        $weekLabels = array_keys($weekLabels);
        $monthLabels = array_keys($monthLabels);
        sort($weekLabels);
        sort($monthLabels);

        $dailySeries = $this->toSeries($daily, $dayLabels);

        return [
            'daily'     => $dailySeries,
            'weekly'    => $this->toSeries($weekly, $weekLabels),
            'monthly'   => $this->toSeries($monthly, $monthLabels),
            'direction' => $this->trendDirection(array_column($dailySeries, 'count')),
        ];
    }

    /** Least-squares slope over the daily counts, expressed per week */
    private function trendDirection(array $counts): array
    {
        $n = count($counts);
        if ($n < 4 || array_sum($counts) === 0) {
            return [
                'label'          => 'insufficient data',
                'change_percent' => 0.0,
                'per_week'       => 0.0,
                'explanation'    => 'Not enough days with records to establish a direction.',
            ];
        }

        $sumX = $sumY = $sumXY = $sumXX = 0;
        foreach ($counts as $x => $y) {
            $sumX += $x; $sumY += $y; $sumXY += $x * $y; $sumXX += $x * $x;
        }
        $denominator = ($n * $sumXX) - ($sumX * $sumX);
        $slope = $denominator == 0 ? 0 : (($n * $sumXY) - ($sumX * $sumY)) / $denominator;

        // First half vs second half is easier to explain than a raw slope
        $half = (int) floor($n / 2);
        $firstHalf = array_sum(array_slice($counts, 0, $half));
        $secondHalf = array_sum(array_slice($counts, $half));
        $changePercent = $firstHalf > 0
            ? (($secondHalf - $firstHalf) / $firstHalf) * 100
            : ($secondHalf > 0 ? 100.0 : 0.0);

        $label = 'stable';
        if ($changePercent >= 15) {
            $label = 'increasing';
        } elseif ($changePercent <= -15) {
            $label = 'decreasing';
        }

        return [
            'label'          => $label,
            'change_percent' => round($changePercent, 1),
            'per_week'       => round($slope * 7, 2),
            'first_half'     => $firstHalf,
            'second_half'    => $secondHalf,
            'explanation'    => sprintf(
                '%d incidents in the first half of the period vs %d in the second (%s%.1f%%).',
                $firstHalf,
                $secondHalf,
                $changePercent >= 0 ? '+' : '',
                $changePercent
            ),
        ];
    }

    // --------------------------------------------------------------- hotspots

    /**
     * Grid-based clustering: snap each incident to a cell, then merge. Single
     * pass, so it stays linear as the dataset grows.
     */
    private function hotspots(Collection $all): array
    {
        $cells = [];

        foreach ($all as $i) {
            if (!$i['latitude'] || !$i['longitude']) {
                continue;
            }
            $key = round($i['latitude'] / self::HOTSPOT_CELL) . ':' . round($i['longitude'] / self::HOTSPOT_CELL);

            if (!isset($cells[$key])) {
                $cells[$key] = [
                    'count' => 0, 'sum_lat' => 0.0, 'sum_lng' => 0.0,
                    'categories' => [], 'real' => 0, 'simulated' => 0,
                ];
            }

            $cells[$key]['count']++;
            $cells[$key]['sum_lat'] += $i['latitude'];
            $cells[$key]['sum_lng'] += $i['longitude'];
            $cells[$key]['categories'][$i['category']] = ($cells[$key]['categories'][$i['category']] ?? 0) + 1;
            $i['is_simulated'] ? $cells[$key]['simulated']++ : $cells[$key]['real']++;
        }

        $total = max(1, $all->count());

        $hotspots = collect($cells)
            ->map(function ($c) use ($total) {
                arsort($c['categories']);

                return [
                    'latitude'          => round($c['sum_lat'] / $c['count'], 6),
                    'longitude'         => round($c['sum_lng'] / $c['count'], 6),
                    'count'             => $c['count'],
                    'real_count'        => $c['real'],
                    'simulated_count'   => $c['simulated'],
                    'share_percent'     => round(($c['count'] / $total) * 100, 1),
                    'dominant_category' => array_key_first($c['categories']),
                    'categories'        => $c['categories'],
                    'radius_meters'     => (int) round(self::HOTSPOT_CELL * 111000 / 2),
                ];
            })
            ->sortByDesc('count')
            ->values();

        // Rank only meaningful concentrations
        return $hotspots->filter(fn ($h) => $h['count'] > 1)
            ->take(10)
            ->values()
            ->map(fn ($h, $idx) => $h + ['rank' => $idx + 1])
            ->all();
    }

    // ---------------------------------------------------------- time patterns

    private function timePatterns(Collection $all): array
    {
        $withTime = $all->filter(fn ($i) => $i['hour'] !== null);

        $byHour = array_fill(0, 24, 0);
        $byHourReal = array_fill(0, 24, 0);
        $byHourSim = array_fill(0, 24, 0);
        foreach ($withTime as $i) {
            $byHour[$i['hour']]++;
            $i['is_simulated'] ? $byHourSim[$i['hour']]++ : $byHourReal[$i['hour']]++;
        }

        $dowNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $byDow = array_fill(0, 7, 0);
        $byDowReal = array_fill(0, 7, 0);
        $byDowSim = array_fill(0, 7, 0);
        foreach ($all as $i) {
            $byDow[$i['dow']]++;
            $i['is_simulated'] ? $byDowSim[$i['dow']]++ : $byDowReal[$i['dow']]++;
        }

        $peakHour = $withTime->isEmpty() ? null : array_keys($byHour, max($byHour))[0];
        $peakDow = array_sum($byDow) === 0 ? null : array_keys($byDow, max($byDow))[0];

        // Sunday(0) and Saturday(6) are the weekend
        $weekend = $byDow[0] + $byDow[6];
        $weekday = array_sum($byDow) - $weekend;
        $weekendDailyAvg = $weekend / 2;
        $weekdayDailyAvg = $weekday / 5;

        $blocks = [
            'Early morning (12:00 AM-5:59 AM)' => array_sum(array_slice($byHour, 0, 6)),
            'Morning (6:00 AM-11:59 AM)'       => array_sum(array_slice($byHour, 6, 6)),
            'Afternoon (12:00 PM-5:59 PM)'     => array_sum(array_slice($byHour, 12, 6)),
            'Evening/Night (6:00 PM-11:59 PM)' => array_sum(array_slice($byHour, 18, 6)),
        ];
        arsort($blocks);

        return [
            'by_hour' => collect($byHour)->map(fn ($c, $h) => [
                'hour'      => $h,
                'label'     => $this->formatHour12($h),
                'count'     => $c,
                'real'      => $byHourReal[$h],
                'simulated' => $byHourSim[$h],
            ])->values()->all(),

            'by_day_of_week' => collect($byDow)->map(fn ($c, $d) => [
                'day'       => $dowNames[$d],
                'label'     => $dowNames[$d],
                'index'     => $d,
                'count'     => $c,
                'real'      => $byDowReal[$d],
                'simulated' => $byDowSim[$d],
            ])->values()->all(),

            'peak_hour'       => $peakHour,
            'peak_hour_label' => $peakHour === null
                ? null
                : $this->formatHour12($peakHour) . '-' . $this->formatHour12($peakHour, 59),
            'peak_hour_count' => $peakHour === null ? 0 : $byHour[$peakHour],
            'peak_day'        => $peakDow === null ? null : $dowNames[$peakDow],
            'peak_day_count'  => $peakDow === null ? 0 : $byDow[$peakDow],

            'missing_time_count' => $all->count() - $withTime->count(),

            'time_blocks' => collect($blocks)->map(fn ($c, $label) => [
                'block' => $label,
                'count' => $c,
            ])->values()->all(),

            'weekday_vs_weekend' => [
                'weekday_total'     => $weekday,
                'weekend_total'     => $weekend,
                'weekday_daily_avg' => round($weekdayDailyAvg, 2),
                'weekend_daily_avg' => round($weekendDailyAvg, 2),
                'busier'            => $weekendDailyAvg > $weekdayDailyAvg ? 'weekend' : 'weekday',
                'difference_percent' => $weekdayDailyAvg > 0
                    ? round((($weekendDailyAvg - $weekdayDailyAvg) / $weekdayDailyAvg) * 100, 1)
                    : 0.0,
            ],
        ];
    }

    // ----------------------------------------------------- type distribution

    private function typeDistribution(Collection $all): array
    {
        $total = max(1, $all->count());
        $byCategory = [];

        foreach ($all as $i) {
            $key = $i['category'];
            if (!isset($byCategory[$key])) {
                $byCategory[$key] = ['count' => 0, 'real' => 0, 'simulated' => 0, 'crime' => 0, 'incident' => 0];
            }
            $byCategory[$key]['count']++;
            $i['is_simulated'] ? $byCategory[$key]['simulated']++ : $byCategory[$key]['real']++;
            $byCategory[$key][$i['record_type'] === 'incident' ? 'incident' : 'crime']++;
        }

        return collect($byCategory)
            ->map(fn ($c, $name) => [
                'category'        => $name,
                'count'           => $c['count'],
                'real_count'      => $c['real'],
                'simulated_count' => $c['simulated'],
                'crime_count'     => $c['crime'],
                'incident_count'  => $c['incident'],
                'percent'         => round(($c['count'] / $total) * 100, 1),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    // ----------------------------------------------------- repeat / clusters

    /**
     * Incidents close in BOTH space and time. Sorted by timestamp then compared
     * only against others inside the time window, so this stays near-linear.
     */
    private function repeatClusters(Collection $all): array
    {
        $points = $all->filter(fn ($i) => $i['latitude'] && $i['longitude'])
            ->sortBy('date')
            ->values();

        $clusters = [];
        $assigned = [];

        foreach ($points as $idx => $a) {
            if (isset($assigned[$idx])) {
                continue;
            }

            $members = [$idx];
            $startTs = strtotime($a['date']);

            for ($j = $idx + 1; $j < $points->count(); $j++) {
                if (isset($assigned[$j])) {
                    continue;
                }
                $b = $points[$j];

                $hoursApart = (strtotime($b['date']) - $startTs) / 3600;
                if ($hoursApart > self::REPEAT_WINDOW_HOURS) {
                    break;   // sorted by date, so nothing further can qualify
                }

                if ($this->metersBetween($a['latitude'], $a['longitude'], $b['latitude'], $b['longitude']) <= self::REPEAT_RADIUS_M) {
                    $members[] = $j;
                }
            }

            if (count($members) < 2) {
                continue;
            }

            foreach ($members as $m) {
                $assigned[$m] = true;
            }

            $rows = collect($members)->map(fn ($m) => $points[$m]);

            $clusters[] = [
                'incident_count'  => $rows->count(),
                'real_count'      => $rows->where('is_simulated', false)->count(),
                'simulated_count' => $rows->where('is_simulated', true)->count(),
                'first_date'      => $rows->min('date'),
                'last_date'       => $rows->max('date'),
                'span_days'       => (int) round((strtotime($rows->max('date')) - strtotime($rows->min('date'))) / 86400),
                'latitude'        => round($rows->avg('latitude'), 6),
                'longitude'       => round($rows->avg('longitude'), 6),
                'categories'      => $rows->pluck('category')->unique()->values()->all(),
                'radius_meters'   => self::REPEAT_RADIUS_M,
                'window_hours'    => self::REPEAT_WINDOW_HOURS,
                'incidents'       => $rows->map(fn ($r) => [
                    'incident_code' => $r['incident_code'],
                    'title'         => $r['title'],
                    'date'          => $r['date'],
                    'category'      => $r['category'],
                    'is_simulated'  => $r['is_simulated'],
                ])->values()->all(),
            ];
        }

        usort($clusters, fn ($a, $b) => $b['incident_count'] <=> $a['incident_count']);

        return array_slice($clusters, 0, 10);
    }

    private function metersBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // -------------------------------------------------------------- anomalies

    /** Days whose count sits more than ANOMALY_Z standard deviations from the mean */
    private function anomalies(Collection $all, int $days): array
    {
        $daily = $this->countByBucket($all, fn ($i) => $i['date']);

        $series = [];
        $cursor = now()->subDays($days)->startOfDay();
        $end = now()->startOfDay();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $series[$key] = $daily[$key] ?? 0;
            $cursor->addDay();
        }

        $values = array_values($series);
        $n = count($values);
        if ($n < 7) {
            return ['detected' => [], 'mean' => 0, 'std_dev' => 0, 'threshold' => 0,
                    'note' => 'Need at least a week of data before outliers mean anything.'];
        }

        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values)) / $n;
        $stdDev = sqrt($variance);

        if ($stdDev == 0.0) {
            return ['detected' => [], 'mean' => round($mean, 2), 'std_dev' => 0, 'threshold' => 0,
                    'note' => 'Daily counts show no variation in this period.'];
        }

        $detected = [];
        foreach ($series as $date => $count) {
            $z = ($count - $mean) / $stdDev;
            if (abs($z) >= self::ANOMALY_Z && $count > 0) {
                $detected[] = [
                    'date'        => $date,
                    'count'       => $count,
                    'expected'    => round($mean, 2),
                    'z_score'     => round($z, 2),
                    'type'        => $z > 0 ? 'spike' : 'drop',
                    'severity'    => abs($z) >= 3 ? 'high' : 'moderate',
                    'explanation' => sprintf(
                        '%d incidents on this day against a period average of %.1f (%.1f standard deviations).',
                        $count, $mean, $z
                    ),
                ];
            }
        }

        usort($detected, fn ($a, $b) => abs($b['z_score']) <=> abs($a['z_score']));

        return [
            'detected'  => array_slice($detected, 0, 10),
            'mean'      => round($mean, 2),
            'std_dev'   => round($stdDev, 2),
            'threshold' => round($mean + self::ANOMALY_Z * $stdDev, 2),
            'note'      => null,
        ];
    }

    // --------------------------------------------------------------- insights

    /** Plain-language summary lines, each tied to the numbers behind it */
    private function insights(array $r): array
    {
        $out = [];
        $meta = $r['meta'];
        $simulated = $meta['simulation_enabled'];

        if ($meta['total_count'] === 0) {
            return [[
                'text'       => 'No incidents recorded in this period.',
                'confidence' => 'n/a',
                'based_on'   => '0 records',
                'simulated'  => false,
            ]];
        }

        $confidence = $meta['low_confidence'] ? 'low' : 'normal';
        $basis = $simulated
            ? sprintf('%d records (%d real + %d simulated)', $meta['total_count'], $meta['real_count'], $meta['simulated_count'])
            : sprintf('%d real records', $meta['real_count']);

        // Trend
        $direction = $r['trends']['direction'];
        if ($direction['label'] !== 'insufficient data') {
            $out[] = [
                'text' => match ($direction['label']) {
                    'increasing' => sprintf('Incidents are trending upward — %+.1f%% in the second half of the period.', $direction['change_percent']),
                    'decreasing' => sprintf('Incidents are trending downward — %+.1f%% in the second half of the period.', $direction['change_percent']),
                    default      => sprintf('Incident volume is broadly stable (%+.1f%% change across the period).', $direction['change_percent']),
                },
                'confidence' => $confidence,
                'based_on'   => $direction['explanation'],
                'simulated'  => $simulated,
            ];
        }

        // Time of day
        $tp = $r['time_patterns'];
        if (!empty($tp['time_blocks']) && $tp['time_blocks'][0]['count'] > 0) {
            $block = $tp['time_blocks'][0];
            $share = round(($block['count'] / max(1, $meta['total_count'])) * 100);
            $out[] = [
                'text'       => sprintf('Most incidents happen in the %s window.', strtolower($block['block'])),
                'confidence' => $tp['missing_time_count'] > $meta['total_count'] / 2 ? 'low' : $confidence,
                'based_on'   => sprintf('%d of %d records (%d%%)%s', $block['count'], $meta['total_count'], $share,
                    $tp['missing_time_count'] > 0 ? sprintf('; %d records have no recorded time', $tp['missing_time_count']) : ''),
                'simulated'  => $simulated,
            ];
        }

        if ($tp['peak_day']) {
            $out[] = [
                'text'       => sprintf('%s is the busiest day of the week.', $tp['peak_day']),
                'confidence' => $confidence,
                'based_on'   => sprintf('%d incidents on %ss', $tp['peak_day_count'], $tp['peak_day']),
                'simulated'  => $simulated,
            ];
        }

        $wk = $tp['weekday_vs_weekend'];
        if ($wk['weekday_total'] + $wk['weekend_total'] > 0) {
            $out[] = [
                'text'       => sprintf('Daily activity is higher on %ss.', $wk['busier']),
                'confidence' => $confidence,
                'based_on'   => sprintf('%.2f per weekday vs %.2f per weekend day', $wk['weekday_daily_avg'], $wk['weekend_daily_avg']),
                'simulated'  => $simulated,
            ];
        }

        // Dominant category
        if (!empty($r['type_distribution'])) {
            $top = $r['type_distribution'][0];
            $out[] = [
                'text'       => sprintf('%s is the most common category at %.1f%% of all records.', $top['category'], $top['percent']),
                'confidence' => $confidence,
                'based_on'   => sprintf('%d of %d records', $top['count'], $meta['total_count']),
                'simulated'  => $simulated,
            ];
        }

        // Hotspot concentration
        if (!empty($r['hotspots'])) {
            $top = $r['hotspots'][0];
            $out[] = [
                'text'       => sprintf('The busiest location accounts for %.1f%% of records, mostly %s.', $top['share_percent'], $top['dominant_category']),
                'confidence' => $confidence,
                'based_on'   => sprintf('%d incidents within ~%dm of %.5f, %.5f', $top['count'], $top['radius_meters'], $top['latitude'], $top['longitude']),
                'simulated'  => $simulated,
            ];
        }

        // Repeat clusters
        if (!empty($r['repeat_clusters'])) {
            $c = $r['repeat_clusters'][0];
            $out[] = [
                'text'       => sprintf('Repeat activity detected: %d incidents within %dm over %d days.', $c['incident_count'], $c['radius_meters'], max(1, $c['span_days'])),
                'confidence' => $confidence,
                'based_on'   => sprintf('%s to %s', $c['first_date'], $c['last_date']),
                'simulated'  => $simulated,
            ];
        }

        // Anomalies
        if (!empty($r['anomalies']['detected'])) {
            $a = $r['anomalies']['detected'][0];
            $out[] = [
                'text'       => sprintf('Unusual %s on %s — %d incidents against an average of %.1f.', $a['type'], $a['date'], $a['count'], $a['expected']),
                'confidence' => $confidence,
                'based_on'   => sprintf('z-score %.2f, threshold %.1f', $a['z_score'], self::ANOMALY_Z),
                'simulated'  => $simulated,
            ];
        }

        if ($simulated) {
            array_unshift($out, [
                'text'       => 'Simulation is ON — these insights mix real and generated records and must not be used for operational decisions.',
                'confidence' => 'n/a',
                'based_on'   => $basis,
                'simulated'  => true,
            ]);
        }

        return $out;
    }

    // ---------------------------------------------------------------- helpers

    private function countByBucket(Collection $items, callable $keyFn): array
    {
        $out = [];
        foreach ($items as $i) {
            $key = $keyFn($i);
            $out[$key] = ($out[$key] ?? 0) + 1;
        }
        return $out;
    }

    /** Per-bucket totals split into real and simulated, so charts can stack them */
    private function splitByBucket(Collection $items, callable $keyFn): array
    {
        $out = [];
        foreach ($items as $i) {
            $key = $keyFn($i);
            $out[$key] ??= ['count' => 0, 'real' => 0, 'simulated' => 0];
            $out[$key]['count']++;
            $i['is_simulated'] ? $out[$key]['simulated']++ : $out[$key]['real']++;
        }
        return $out;
    }

    /**
     * @param array $buckets  label => ['count','real','simulated']
     * @param array $skeleton Optional ordered labels; missing ones become zero so
     *                        a chart never implies a gap was a quiet day
     */
    private function toSeries(array $buckets, ?array $skeleton = null): array
    {
        $labels = $skeleton ?? array_keys($buckets);
        $out = [];

        foreach ($labels as $label) {
            $b = $buckets[$label] ?? ['count' => 0, 'real' => 0, 'simulated' => 0];
            $out[] = [
                'label'     => (string) $label,
                'count'     => $b['count'],
                'real'      => $b['real'],
                'simulated' => $b['simulated'],
            ];
        }

        return $out;
    }
}
