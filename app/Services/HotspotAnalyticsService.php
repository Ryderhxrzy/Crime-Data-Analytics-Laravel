<?php

namespace App\Services;

use App\Models\CrimeIncident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Hotspot analytics.
 *
 * Every incident in this system sits in one barangay (San Agustin), so the unit
 * of analysis is the STREET the incident happened on, taken from
 * address_details the same way the map and the street modal take it. Ranking by
 * barangay would always return a single row.
 *
 * Composite Risk Score (0-100) =
 *     40% volume    (incident count, normalised against the busiest street)
 *   + 20% density   (incidents per 100 m of street, normalised)
 *   + 25% severity  (average severity of the incidents: critical=4 ... low=1)
 *   + 15% trend     (percent change vs the previous equal-length period)
 *
 * Density is what separates a short street with 20 crimes from a long one with
 * the same count — the short one is the denser, more concentrated hotspot.
 *
 * The forecast is a least-squares linear regression over weekly incident
 * counts, fitted per street — a transparent trend projection, NOT a
 * machine-learning model. It reports a 95% prediction interval alongside every
 * point estimate, and a separate heuristic "confidence" index derived from the
 * regression fit (R²), sample size and how far ahead the week sits. That index
 * is not an accuracy figure: nothing here is validated against held-out data.
 */
class HotspotAnalyticsService
{
    private const SEVERITY_WEIGHTS = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

    /** Trend comparison window (days) when the user selects "All Time". */
    private const DEFAULT_TREND_WINDOW = 90;

    /** Length of the peak activity window reported per hotspot, in hours. */
    private const PEAK_WINDOW_HOURS = 4;

    /**
     * Shortest street we will quote a density for. Clipping the street geojson
     * to the barangay leaves a handful of 1-20 m stubs; dividing by those turns
     * a couple of crimes into a nonsense "500 per 100 m" and lets a stub
     * outrank a genuinely busy road.
     */
    private const MIN_DENSITY_LENGTH_M = 60;

    public function analyze(string $timePeriod, string $crimeType, string $barangay, string $caseStatus = '', array $streets = []): array
    {
        $incidents = $this->baseQuery($timePeriod, $crimeType, $barangay, $caseStatus)->get();
        $streetKeys = collect($streets)
            ->map(fn ($street) => mb_strtolower(trim((string) $street)))
            ->filter()
            ->unique()
            ->all();

        if ($streetKeys !== []) {
            $incidents = $incidents
                ->filter(fn ($incident) => in_array(mb_strtolower($this->streetOf($incident) ?? ''), $streetKeys, true))
                ->values();
        }

        $hotspots = $this->rankStreets($incidents, $timePeriod, $crimeType, $caseStatus);

        return [
            'crimes' => $this->crimePayload($incidents),
            'hotspots' => $hotspots,
            'summary' => $this->summary($incidents, $hotspots, $timePeriod, $crimeType, $barangay, $caseStatus),
            'monthly_trends' => $this->monthlyTrends($crimeType, $barangay, $caseStatus),
            'type_distribution' => $this->typeDistribution($incidents),
            'day_night' => $this->dayNightSplit($incidents),
            'hourly' => $this->hourlyDistribution($incidents),
            'weekday' => $this->weekdayDistribution($incidents),
        ];
    }

    /**
     * Windows are measured back from the most recent recorded incident rather
     * than today, so trend figures still mean something when the dataset lags
     * the calendar.
     */
    protected function referenceDate(): Carbon
    {
        $latest = CrimeIncident::max('incident_date');

        return $latest ? Carbon::parse($latest) : Carbon::now();
    }

    protected function baseQuery(string $timePeriod, string $crimeType, string $barangay, string $caseStatus)
    {
        $query = CrimeIncident::with(['category', 'barangay'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($timePeriod !== 'all') {
            $query->where('incident_date', '>=', $this->referenceDate()->subDays((int) $timePeriod)->toDateString());
        }
        if ($crimeType !== '') {
            $query->where('crime_category_id', $crimeType);
        }
        if ($barangay !== '') {
            $query->where('barangay_id', $barangay);
        }
        if ($caseStatus !== '') {
            $query->where('status', $caseStatus);
        }

        return $query;
    }

    /** The street an incident happened on, or null when it cannot be read */
    protected function streetOf($incident): ?string
    {
        $street = trim(explode(',', (string) $incident->address_details)[0] ?? '');

        if ($street === '' || str_starts_with($street, 'Purok')) {
            return null;
        }

        return $street;
    }

    protected function categoryOf($incident): string
    {
        // The row's own category_name is the source of truth on this table; the
        // relation is only a fallback, and is null for imported rows whose
        // crime_category_id was never matched.
        return $incident->category_name ?: ($incident->category?->category_name ?? 'Unknown');
    }

    protected function crimePayload(Collection $incidents): array
    {
        return $incidents->map(fn ($crime) => [
            'id' => $crime->id,
            'incident_title' => $crime->incident_title,
            'incident_date' => $crime->incident_date?->format('Y-m-d'),
            'incident_time' => $crime->incident_time ? substr((string) $crime->incident_time, 0, 5) : null,
            'latitude' => (float) $crime->latitude,
            'longitude' => (float) $crime->longitude,
            'barangay_name' => $crime->barangay?->barangay_name ?? 'Unknown',
            'barangay_id' => $crime->barangay_id,
            'crime_category_id' => $crime->crime_category_id,
            'category_name' => $this->categoryOf($crime),
            'color_code' => $crime->category?->color_code ?? '#274d4c',
            'clearance_status' => $crime->clearance_status,
            'street' => $this->streetOf($crime),
            'location' => $crime->address_details,
        ])->values()->toArray();
    }

    // ------------------------------------------------------------------
    // Street hotspots
    // ------------------------------------------------------------------

    protected function rankStreets(Collection $incidents, string $timePeriod, string $crimeType, string $caseStatus): array
    {
        $groups = $incidents
            ->filter(fn ($i) => $this->streetOf($i) !== null)
            ->groupBy(fn ($i) => $this->streetOf($i));

        if ($groups->isEmpty()) {
            return [];
        }

        $lengths = $this->streetLengths();
        $trends = $this->streetTrends($timePeriod, $crimeType, $caseStatus);
        $colors = $this->categoryColors();

        $rows = [];
        foreach ($groups as $street => $group) {
            $count = $group->count();

            // Full category breakdown — the hotspot card lists every type, not
            // just the leading one.
            $categoryCounts = $group->countBy(fn ($i) => $this->categoryOf($i))->sortDesc();
            $categories = [];
            foreach ($categoryCounts as $name => $n) {
                $categories[] = [
                    'name' => $name,
                    'count' => $n,
                    'share' => (int) round($n / $count * 100),
                    'color' => $colors[mb_strtolower(trim($name))] ?? '#6b7280',
                ];
            }

            $severityIndex = $group->avg(
                fn ($i) => self::SEVERITY_WEIGHTS[$i->category->severity_level ?? 'medium'] ?? 2
            );

            $cleared = $group->where('clearance_status', 'cleared')->count();
            $night = $group->filter(fn ($i) => $this->isNight($i))->count();
            $peak = $this->peakWindow($group);
            $lengthM = $lengths[mb_strtolower(trim($street))] ?? null;
            $trend = $trends[mb_strtolower(trim($street))] ?? ['direction' => 'stable', 'percent' => 0];

            // Distinct spots along the street: coordinates rounded to ~11 m, so
            // repeat crimes at one address count once.
            $spots = $group->map(fn ($i) => round((float) $i->latitude, 4) . ',' . round((float) $i->longitude, 4))
                ->unique()->count();

            $sorted = $group->sortByDesc(fn ($i) => ($i->incident_date?->format('Y-m-d') ?? '') . ' ' . ($i->incident_time ?? ''));

            $rows[] = [
                'area_name' => $street,
                'street' => $street,
                'barangay_name' => $group->first()->barangay_name ?? 'San Agustin',
                'incident_count' => $count,
                'categories' => $categories,
                'top_category' => $categories[0]['name'] ?? null,
                'top_category_count' => $categories[0]['count'] ?? 0,
                'top_category_color' => $categories[0]['color'] ?? '#6b7280',
                'severity_index' => round($severityIndex, 2),
                'peak_period' => $peak['label'],
                'peak_count' => $peak['count'],
                'peak_share' => $peak['share'],
                'trend_direction' => $trend['direction'],
                'trend_percent' => $trend['percent'],
                'cleared' => $cleared,
                'uncleared' => $count - $cleared,
                'clearance_rate' => (int) round($cleared / $count * 100),
                'night_percent' => (int) round($night / $count * 100),
                'street_length_m' => $lengthM ? (int) round($lengthM) : null,
                'density_per_100m' => ($lengthM !== null && $lengthM >= self::MIN_DENSITY_LENGTH_M)
                    ? round($count / ($lengthM / 100), 2)
                    : null,
                'affected_locations' => $spots,
                'last_incident' => $sorted->first()?->incident_date?->format('Y-m-d'),
                'recent_incidents' => $sorted->take(5)->map(fn ($i) => [
                    'title' => $i->incident_title,
                    'category' => $this->categoryOf($i),
                    'date' => $i->incident_date?->format('Y-m-d'),
                    'time' => $i->incident_time ? $this->hour12((int) substr((string) $i->incident_time, 0, 2), (int) substr((string) $i->incident_time, 3, 2)) : null,
                    'cleared' => $i->clearance_status === 'cleared',
                ])->values()->toArray(),
                'latitude' => round($group->avg('latitude'), 6),
                'longitude' => round($group->avg('longitude'), 6),
                'bounds' => [
                    [round($group->min('latitude'), 6), round($group->min('longitude'), 6)],
                    [round($group->max('latitude'), 6), round($group->max('longitude'), 6)],
                ],
            ];
        }

        // Normalise against this result set, then score
        $maxCount = max(array_column($rows, 'incident_count'));
        $densities = array_filter(array_column($rows, 'density_per_100m'), fn ($d) => $d !== null);
        $maxDensity = $densities ? max($densities) : 0;

        foreach ($rows as &$row) {
            $volumeScore = $maxCount > 0 ? $row['incident_count'] / $maxCount * 100 : 0;
            $densityScore = ($row['density_per_100m'] !== null && $maxDensity > 0)
                ? $row['density_per_100m'] / $maxDensity * 100
                : $volumeScore;   // no street geometry: fall back to volume
            $severityScore = ($row['severity_index'] - 1) / 3 * 100;
            $trendScore = max(0, min(100, 50 + $row['trend_percent']));

            $row['risk_score'] = round(
                $volumeScore * 0.40 + $densityScore * 0.20 + $severityScore * 0.25 + $trendScore * 0.15,
                1
            );
            $row['risk_level'] = $this->riskLevel($row['risk_score']);
        }
        unset($row);

        usort($rows, fn ($a, $b) => $b['risk_score'] <=> $a['risk_score']);

        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }
        unset($row);

        return $rows;
    }

    protected function riskLevel(float $score): string
    {
        return match (true) {
            $score >= 70 => 'CRITICAL',
            $score >= 45 => 'HIGH',
            $score >= 25 => 'MEDIUM',
            default => 'LOW',
        };
    }

    /**
     * The busiest contiguous block of hours on a street, e.g. "8 PM - 12 AM".
     * A rolling window over the 24-hour histogram, so the answer is the real
     * peak rather than a fixed morning/afternoon/evening bucket.
     */
    protected function peakWindow(Collection $group): array
    {
        $hours = array_fill(0, 24, 0);
        $withTime = 0;

        foreach ($group as $incident) {
            if (empty($incident->incident_time)) {
                continue;
            }
            $hour = (int) substr((string) $incident->incident_time, 0, 2);
            if ($hour >= 0 && $hour <= 23) {
                $hours[$hour]++;
                $withTime++;
            }
        }

        if ($withTime === 0) {
            return ['label' => null, 'count' => 0, 'share' => 0];
        }

        $bestStart = 0;
        $bestSum = -1;
        for ($start = 0; $start < 24; $start++) {
            $sum = 0;
            for ($h = 0; $h < self::PEAK_WINDOW_HOURS; $h++) {
                $sum += $hours[($start + $h) % 24];
            }
            if ($sum > $bestSum) {
                $bestSum = $sum;
                $bestStart = $start;
            }
        }

        $end = ($bestStart + self::PEAK_WINDOW_HOURS) % 24;

        return [
            'label' => $this->hour12($bestStart) . ' - ' . $this->hour12($end),
            'count' => $bestSum,
            'share' => (int) round($bestSum / $withTime * 100),
        ];
    }

    protected function hour12(int $hour, ?int $minute = null): string
    {
        $suffix = $hour >= 12 ? 'PM' : 'AM';
        $display = $hour % 12 === 0 ? 12 : $hour % 12;

        return $minute === null
            ? $display . ' ' . $suffix
            : sprintf('%d:%02d %s', $display, $minute, $suffix);
    }

    /**
     * Percent change per street: the most recent window against the equally
     * long window before it, under the same filters as the ranking.
     */
    protected function streetTrends(string $timePeriod, string $crimeType, string $caseStatus): array
    {
        $days = $timePeriod !== 'all' ? (int) $timePeriod : self::DEFAULT_TREND_WINDOW;
        $reference = $this->referenceDate();

        $currentStart = $reference->copy()->subDays($days)->toDateString();
        $previousStart = $reference->copy()->subDays($days * 2)->toDateString();

        $rows = CrimeIncident::query()
            ->where('incident_date', '>=', $previousStart)
            ->when($crimeType !== '', fn ($q) => $q->where('crime_category_id', $crimeType))
            ->when($caseStatus !== '', fn ($q) => $q->where('status', $caseStatus))
            ->get(['address_details', 'incident_date']);

        $tally = [];
        foreach ($rows as $row) {
            $street = $this->streetOf($row);
            if ($street === null || ! $row->incident_date) {
                continue;
            }

            $key = mb_strtolower(trim($street));
            $tally[$key] ??= ['current' => 0, 'previous' => 0];
            $date = $row->incident_date->format('Y-m-d');

            if ($date >= $currentStart) {
                $tally[$key]['current']++;
            } else {
                $tally[$key]['previous']++;
            }
        }

        $out = [];
        foreach ($tally as $key => $counts) {
            if ($counts['previous'] === 0) {
                $percent = $counts['current'] > 0 ? 100 : 0;
            } else {
                $percent = (int) round(($counts['current'] - $counts['previous']) / $counts['previous'] * 100);
            }

            $out[$key] = [
                'direction' => $percent > 10 ? 'increasing' : ($percent < -10 ? 'decreasing' : 'stable'),
                'percent' => $percent,
                'current' => $counts['current'],
                'previous' => $counts['previous'],
            ];
        }

        return $out;
    }

    /**
     * Length in metres of every San Agustin street, summed over its polyline
     * segments. Cached — the geojson is a static file.
     */
    protected function streetLengths(): array
    {
        return Cache::remember('sa_street_lengths_v1', now()->addHours(6), function () {
            $path = public_path('data/san_agustin_streets.geojson');
            if (! is_file($path)) {
                return [];
            }

            $geo = json_decode((string) file_get_contents($path), true);
            $lengths = [];

            foreach (($geo['features'] ?? []) as $feature) {
                $name = trim((string) ($feature['properties']['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $coords = $feature['geometry']['coordinates'] ?? [];
                $total = 0.0;
                for ($i = 0; $i < count($coords) - 1; $i++) {
                    $total += $this->haversine(
                        (float) $coords[$i][1], (float) $coords[$i][0],
                        (float) $coords[$i + 1][1], (float) $coords[$i + 1][0]
                    );
                }

                $key = mb_strtolower($name);
                $lengths[$key] = ($lengths[$key] ?? 0) + $total;
            }

            return $lengths;
        });
    }

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** ['theft' => '#f59e0b', ...] for colouring the category breakdown */
    protected function categoryColors(): array
    {
        return Cache::remember('hotspot_category_colors_v1', now()->addMinutes(30), function () {
            try {
                return \App\Models\CrimeCategory::get(['category_name', 'color_code'])
                    ->mapWithKeys(fn ($c) => [mb_strtolower(trim($c->category_name)) => $c->color_code ?: '#6b7280'])
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    protected function summary(Collection $incidents, array $hotspots, string $timePeriod, string $crimeType, string $barangay, string $caseStatus): array
    {
        $cleared = $incidents->where('clearance_status', 'cleared')->count();
        $total = $incidents->count();

        $days = $timePeriod !== 'all' ? (int) $timePeriod : self::DEFAULT_TREND_WINDOW;
        $reference = $this->referenceDate();

        $filters = function ($query) use ($crimeType, $barangay, $caseStatus) {
            if ($crimeType !== '') {
                $query->where('crime_category_id', $crimeType);
            }
            if ($barangay !== '') {
                $query->where('barangay_id', $barangay);
            }
            if ($caseStatus !== '') {
                $query->where('status', $caseStatus);
            }
        };

        $currentPeriod = CrimeIncident::where('incident_date', '>=', $reference->copy()->subDays($days)->toDateString())
            ->tap($filters)->count();
        $previousPeriod = CrimeIncident::whereBetween('incident_date', [
            $reference->copy()->subDays($days * 2)->toDateString(),
            $reference->copy()->subDays($days)->toDateString(),
        ])->tap($filters)->count();

        if ($previousPeriod > 0) {
            $trendPercent = (int) round(($currentPeriod - $previousPeriod) / $previousPeriod * 100);
        } else {
            $trendPercent = $currentPeriod > 0 ? 100 : 0;
        }

        $riskCounts = array_count_values(array_column($hotspots, 'risk_level'));
        $densities = array_filter(array_column($hotspots, 'density_per_100m'), fn ($d) => $d !== null);

        return [
            'total_incidents' => $total,
            'affected_streets' => count($hotspots),
            // Kept for any caller still reading the old key
            'affected_barangays' => count($hotspots),
            'affected_locations' => array_sum(array_column($hotspots, 'affected_locations')),
            'risk_counts' => [
                'critical' => $riskCounts['CRITICAL'] ?? 0,
                'high' => $riskCounts['HIGH'] ?? 0,
                'medium' => $riskCounts['MEDIUM'] ?? 0,
                'low' => $riskCounts['LOW'] ?? 0,
            ],
            'highest_risk' => $hotspots[0] ?? null,
            'peak_period' => $this->peakWindow($incidents)['label'],
            'avg_density_per_100m' => $densities ? round(array_sum($densities) / count($densities), 2) : null,
            'citywide_trend' => [
                'direction' => $trendPercent > 10 ? 'increasing' : ($trendPercent < -10 ? 'decreasing' : 'stable'),
                'percent' => $trendPercent,
                'window_days' => $days,
            ],
            'clearance_rate' => $total > 0 ? round($cleared / $total * 100) : 0,
            'unsolved_count' => $total - $cleared,
        ];
    }

    /**
     * Real 12-month incident counts (the previous implementation's timePeriod
     * filter zeroed out older months; a 12-month chart defines its own window).
     */
    protected function monthlyTrends(string $crimeType, string $barangay, string $caseStatus): array
    {
        $labels = [];
        $values = [];
        $reference = $this->referenceDate();

        for ($i = 11; $i >= 0; $i--) {
            $date = $reference->copy()->subMonths($i);
            $labels[] = $date->format('M Y');

            $query = CrimeIncident::whereYear('incident_date', $date->year)
                ->whereMonth('incident_date', $date->month);

            if ($crimeType !== '') {
                $query->where('crime_category_id', $crimeType);
            }
            if ($barangay !== '') {
                $query->where('barangay_id', $barangay);
            }
            if ($caseStatus !== '') {
                $query->where('status', $caseStatus);
            }

            $values[] = $query->count();
        }

        return ['labels' => $labels, 'values' => $values];
    }

    protected function typeDistribution(Collection $incidents): array
    {
        $counts = $incidents->countBy(fn ($i) => $this->categoryOf($i))->sortDesc();
        $colors = $this->categoryColors();

        return [
            'labels' => $counts->keys()->values()->toArray(),
            'values' => $counts->values()->toArray(),
            'colors' => $counts->keys()
                ->map(fn ($name) => $colors[mb_strtolower(trim($name))] ?? '#6b7280')
                ->values()->toArray(),
        ];
    }

    /** Incidents per hour of day, for the time-of-day chart */
    protected function hourlyDistribution(Collection $incidents): array
    {
        $hours = array_fill(0, 24, 0);

        foreach ($incidents as $incident) {
            if (empty($incident->incident_time)) {
                continue;
            }
            $hour = (int) substr((string) $incident->incident_time, 0, 2);
            if ($hour >= 0 && $hour <= 23) {
                $hours[$hour]++;
            }
        }

        return [
            'labels' => array_map(fn ($h) => $this->hour12($h), range(0, 23)),
            'values' => array_values($hours),
            'peak_period' => $this->peakWindow($incidents)['label'],
        ];
    }

    /** Incidents per day of the week, Monday first. */
    protected function weekdayDistribution(Collection $incidents): array
    {
        $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $values = array_fill(0, 7, 0);

        foreach ($incidents as $incident) {
            if (empty($incident->incident_date)) {
                continue;
            }
            $dow = (int) Carbon::parse($incident->incident_date)->format('N');   // 1 = Monday
            $values[$dow - 1]++;
        }

        $peak = array_search(max($values), $values, true);

        return [
            'labels' => $labels,
            'values' => $values,
            'peak_day' => max($values) > 0 ? $labels[$peak] : null,
        ];
    }

    protected function dayNightSplit(Collection $incidents): array
    {
        $withTime = $incidents->filter(fn ($i) => ! empty($i->incident_time));
        $night = $withTime->filter(fn ($i) => $this->isNight($i))->count();
        $day = $withTime->count() - $night;

        return [
            'day' => $day,
            'night' => $night,
            'unknown_time' => $incidents->count() - $withTime->count(),
            'night_percent' => $withTime->count() > 0 ? round($night / $withTime->count() * 100) : 0,
        ];
    }

    protected function isNight($incident): bool
    {
        if (empty($incident->incident_time)) {
            return false;
        }

        $hour = (int) Carbon::parse($incident->incident_time)->format('H');

        return $hour >= 18 || $hour < 6;
    }

    // ------------------------------------------------------------------
    // Forecast: least-squares linear regression over weekly incident counts
    // ------------------------------------------------------------------

    /**
     * Smallest historical sample a street needs before we will project it. A
     * line fitted through one or two incidents is noise in the costume of a
     * trend, and it would still print a confident-looking number.
     */
    private const MIN_FORECAST_SAMPLE = 3;

    /** Weeks of recent history shown beside the six-month mean. */
    private const TREND_COMPARISON_WEEKS = 6;

    /**
     * Trend forecast over weekly incident counts.
     *
     * The unit of analysis is the STREET, for the same reason as the ranking:
     * every incident sits in one barangay, so grouping by barangay returns a
     * single row whose risk level can only ever come out "highest".
     *
     * Windows are anchored on the most recent recorded incident rather than on
     * today, so a dataset that lags the calendar still yields a fit worth
     * reading instead of a slope dragged to zero by empty trailing weeks.
     */
    public function forecast(
        int $historicalDays,
        int $forecastDays,
        string $crimeType = '',
        string $barangay = '',
        string $caseStatus = ''
    ): array {
        $anchor = $this->referenceDate()->startOfDay();
        $weeks = max(4, (int) floor($historicalDays / 7));
        $forecastWeeks = max(1, (int) ceil($forecastDays / 7));

        // Whole weeks ending on the anchor, so the newest bucket is never a stub.
        $start = $anchor->copy()->subDays($weeks * 7 - 1);

        $incidents = CrimeIncident::with(['category', 'barangay'])
            ->where('incident_date', '>=', $start->toDateString())
            ->where('incident_date', '<=', $anchor->copy()->endOfDay()->toDateTimeString())
            ->when($crimeType !== '', fn ($q) => $q->where('crime_category_id', $crimeType))
            ->when($barangay !== '', fn ($q) => $q->where('barangay_id', $barangay))
            ->when($caseStatus !== '', fn ($q) => $q->where('status', $caseStatus))
            ->get();

        $series = $this->weeklySeries($incidents, $start, $weeks);
        $fit = $this->linearFit($series);
        $weeklyAverage = array_sum($series) / $weeks;
        $leading = $this->leadingCategory($incidents);
        $baseConfidence = $this->forecastConfidence($fit['r_squared'], $weeks, $incidents->count());

        $projection = $this->projectWeeks(
            $fit,
            $weeks,
            $forecastWeeks,
            $forecastDays,
            $anchor,
            $weeklyAverage,
            $baseConfidence,
            $leading['name'] ?? null
        );

        $predictedTotal = array_sum(array_column($projection, 'predicted'));
        $baseline = $weeklyAverage * $forecastDays / 7;
        $changePercent = $baseline > 0
            ? (int) round(($predictedTotal - $baseline) / $baseline * 100)
            : 0;

        [$streets, $excludedStreets, $streetsConsidered, $riskCounts] =
            $this->forecastStreets($incidents, $start, $weeks, $forecastWeeks, $forecastDays);

        $validation = $this->backtestForecast($series);

        return [
            'method' => 'Least-squares linear regression over weekly incident counts, projected per street (short-term trend projection, not machine learning)',
            'confidence_note' => 'Confidence is a heuristic index built from the regression fit (R²), sample size and forecast distance — it is not a statistical confidence level and it is not an accuracy figure. The shaded band on the chart is the 95% prediction interval from the same fit.',
            'anchor_date' => $anchor->toDateString(),
            'anchor_is_stale' => $anchor->lt(Carbon::now()->subDays(14)),
            'days_behind_today' => max(0, (int) $anchor->diffInDays(Carbon::now()->startOfDay())),
            'historical_days' => $weeks * 7,
            'forecast_days' => $forecastDays,
            'weeks_observed' => $weeks,
            'weeks_projected' => count($projection),
            'citywide' => [
                'historical_count' => $incidents->count(),
                'weekly_average' => round($weeklyAverage, 2),
                'baseline_count' => round($baseline, 1),
                'predicted_count' => (int) round($predictedTotal),
                'change_percent' => $changePercent,
                'trend' => $fit['slope'] > 0.05 ? 'rising' : ($fit['slope'] < -0.05 ? 'falling' : 'flat'),
                'slope_per_week' => round($fit['slope'], 3),
                'r_squared' => round($fit['r_squared'], 3),
                'confidence' => $baseConfidence,
                'risk_level' => $this->forecastRiskLevel($changePercent),
            ],
            'top_category' => $leading,
            'timeline' => [
                'history' => $this->historyBuckets($series, $start),
                'projection' => $projection,
            ],
            'streets' => $streets,
            'streets_considered' => $streetsConsidered,
            'streets_excluded' => $excludedStreets,
            'risk_counts' => $riskCounts,
            'validation' => $validation,
            'planning' => $this->planningPriorities($streets, $forecastDays, $anchor),
            'comparison' => [
                'months' => $this->monthComparison($crimeType, $barangay, $caseStatus, $anchor),
                'trend_vs_average' => $this->trendVersusAverage($series, $start, $weeks, $crimeType, $barangay, $caseStatus, $anchor),
            ],
            'notes' => $this->forecastNotes($incidents->count(), $weeks, $fit, $excludedStreets, $anchor),
        ];
    }

    /**
     * Tests the same forecasting method against the latest recorded weeks.
     * This is a holdout check, not a claim that future results are guaranteed.
     */
    protected function backtestForecast(array $series): array
    {
        $weeks = count($series);
        $holdoutWeeks = min(4, max(0, (int) floor($weeks / 4)));
        $trainingWeeks = $weeks - $holdoutWeeks;

        if ($trainingWeeks < 8 || $holdoutWeeks < 2) {
            return [
                'available' => false,
                'message' => 'At least 10 weeks of history are needed for an out-of-sample validation check.',
            ];
        }

        $training = array_slice($series, 0, $trainingWeeks);
        $actual = array_slice($series, $trainingWeeks);
        $fit = $this->linearFit($training);
        $baseline = array_sum($training) / $trainingWeeks;
        $modelErrors = [];
        $baselineErrors = [];
        $insideInterval = 0;

        foreach ($actual as $index => $observed) {
            $point = $this->predictAt($fit, $trainingWeeks + $index);
            $modelErrors[] = abs($observed - $point['value']);
            $baselineErrors[] = abs($observed - $baseline);
            if ($observed >= $point['lower'] && $observed <= $point['upper']) {
                $insideInterval++;
            }
        }

        $modelMae = array_sum($modelErrors) / $holdoutWeeks;
        $baselineMae = array_sum($baselineErrors) / $holdoutWeeks;

        return [
            'available' => true,
            'training_weeks' => $trainingWeeks,
            'tested_weeks' => $holdoutWeeks,
            'model_mae' => round($modelMae, 2),
            'baseline_mae' => round($baselineMae, 2),
            'better_than_baseline' => $modelMae < $baselineMae,
            'interval_coverage_percent' => (int) round($insideInterval / $holdoutWeeks * 100),
            'message' => $modelMae < $baselineMae
                ? 'On the latest recorded weeks, the trend projection was closer than a flat historical-average baseline.'
                : 'On the latest recorded weeks, a flat historical-average baseline was as good as or better than the trend projection.',
        ];
    }

    /** Practical planning prompts based on the projected streets. */
    protected function planningPriorities(array $streets, int $forecastDays, Carbon $anchor): array
    {
        $priorities = [];
        foreach (array_slice($streets, 0, 3) as $index => $street) {
            $reason = $street['change_percent'] > 0
                ? abs($street['change_percent']) . '% above its own baseline'
                : 'the largest projected volume in the selected area';

            $priorities[] = [
                'rank' => $index + 1,
                'street' => $street['street'],
                'risk_level' => $street['risk_level'],
                'reason' => $reason,
                'action' => 'Use this street as a patrol and visibility priority; review the recorded ' . ($street['top_category'] ?? 'incident') . ' pattern with the local team.',
            ];
        }

        return [
            'purpose' => 'Use the projection to prioritize short-term patrol, visibility and prevention planning. It does not predict an individual incident.',
            'recommended_horizon' => min($forecastDays, 30),
            'anchor_date' => $anchor->toDateString(),
            'priorities' => $priorities,
        ];
    }

    /**
     * Risk band for a projection, defined exactly as the page states it:
     * projected volume against that same period's own historical baseline.
     */
    protected function forecastRiskLevel(float $changePercent): string
    {
        return match (true) {
            $changePercent >= 50 => 'HIGH',
            $changePercent >= 20 => 'MODERATE',
            default => 'LOW',
        };
    }

    /** Observed weekly counts, labelled with the dates each bucket covers. */
    protected function historyBuckets(array $series, Carbon $start): array
    {
        $rows = [];

        foreach ($series as $index => $count) {
            $bucketStart = $start->copy()->addDays($index * 7);
            $bucketEnd = $bucketStart->copy()->addDays(6);

            $rows[] = [
                'week' => $index + 1,
                'label' => $bucketStart->format('M j'),
                'range' => $bucketStart->format('M j') . ' – ' . $bucketEnd->format('M j'),
                'start' => $bucketStart->toDateString(),
                'end' => $bucketEnd->toDateString(),
                'count' => $count,
            ];
        }

        return $rows;
    }

    /**
     * Point estimate and 95% prediction interval at week index $x.
     *
     * The interval carries the leverage term, so it widens the further the week
     * sits from the middle of the observed window. That is the honest shape of
     * a projection: week 12 is a wider claim than week 1.
     */
    protected function predictAt(array $fit, float $x): array
    {
        $value = max(0.0, $fit['intercept'] + $fit['slope'] * $x);

        $margin = 0.0;
        if ($fit['n'] > 2 && $fit['std_error'] > 0) {
            $leverage = 1 + 1 / $fit['n']
                + ($fit['ss_xx'] > 0 ? (($x - $fit['x_mean']) ** 2) / $fit['ss_xx'] : 0);
            $margin = 1.96 * $fit['std_error'] * sqrt($leverage);
        }

        return [
            'value' => $value,
            'lower' => max(0.0, $value - $margin),
            'upper' => $value + $margin,
        ];
    }

    /**
     * One row per forecast week. A horizon that is not a whole number of weeks
     * leaves a short final bucket, which is scaled to the days it actually
     * covers rather than quietly rounded up to seven.
     */
    protected function projectWeeks(
        array $fit,
        int $weeks,
        int $forecastWeeks,
        int $forecastDays,
        Carbon $anchor,
        float $weeklyAverage,
        int $baseConfidence,
        ?string $topCategory
    ): array {
        $rows = [];
        $remaining = $forecastDays;

        for ($k = 1; $k <= $forecastWeeks && $remaining > 0; $k++) {
            $days = min(7, $remaining);
            $remaining -= $days;
            $share = $days / 7;

            $point = $this->predictAt($fit, $weeks - 1 + $k);
            $predicted = $point['value'] * $share;
            $baseline = $weeklyAverage * $share;
            $change = $baseline > 0 ? ($predicted - $baseline) / $baseline * 100 : 0.0;

            $bucketStart = $anchor->copy()->addDays(($k - 1) * 7 + 1);
            $bucketEnd = $bucketStart->copy()->addDays($days - 1);

            $rows[] = [
                'week' => $k,
                'label' => $bucketStart->format('M j'),
                'range' => $bucketStart->format('M j') . ' – ' . $bucketEnd->format('M j'),
                'start' => $bucketStart->toDateString(),
                'end' => $bucketEnd->toDateString(),
                'days' => $days,
                'predicted' => round($predicted, 1),
                'lower' => round($point['lower'] * $share, 1),
                'upper' => round($point['upper'] * $share, 1),
                'baseline' => round($baseline, 1),
                'change_percent' => (int) round($change),
                'risk_level' => $this->forecastRiskLevel($change),
                'confidence' => $this->horizonConfidence($baseConfidence, $k),
                'top_category' => $topCategory,
            ];
        }

        return $rows;
    }

    /**
     * Confidence decays with distance from the observed data: three points per
     * week past the first, floored at 30. A far-out week should never read as
     * confidently as the one starting tomorrow.
     */
    protected function horizonConfidence(int $base, int $week): int
    {
        return (int) max(30, $base - 3 * ($week - 1));
    }

    /**
     * Per-street projections. Streets below MIN_FORECAST_SAMPLE are dropped and
     * counted, so the caller can report how much was left out instead of
     * presenting a filtered list as the whole picture.
     *
     * Risk band counts are tallied over every projected street, not just the
     * ten returned for display — otherwise the bands would silently describe a
     * truncated list.
     *
     * @return array{0: array<int, array>, 1: int, 2: int, 3: array<string, int>} [top streets, excluded, considered, risk counts]
     */
    protected function forecastStreets(Collection $incidents, Carbon $start, int $weeks, int $forecastWeeks, int $forecastDays): array
    {
        $groups = $incidents
            ->filter(fn ($i) => $this->streetOf($i) !== null)
            ->groupBy(fn ($i) => $this->streetOf($i));

        $rows = [];
        $excluded = 0;

        foreach ($groups as $street => $group) {
            if ($group->count() < self::MIN_FORECAST_SAMPLE) {
                $excluded++;
                continue;
            }

            $series = $this->weeklySeries($group, $start, $weeks);
            $fit = $this->linearFit($series);
            $weeklyAverage = array_sum($series) / $weeks;

            $projected = 0.0;
            $lower = 0.0;
            $upper = 0.0;
            $remaining = $forecastDays;

            for ($k = 1; $k <= $forecastWeeks && $remaining > 0; $k++) {
                $days = min(7, $remaining);
                $remaining -= $days;
                $share = $days / 7;

                $point = $this->predictAt($fit, $weeks - 1 + $k);
                $projected += $point['value'] * $share;
                $lower += $point['lower'] * $share;
                $upper += $point['upper'] * $share;
            }

            $baseline = $weeklyAverage * $forecastDays / 7;
            $change = $baseline > 0 ? ($projected - $baseline) / $baseline * 100 : 0.0;
            $leading = $this->leadingCategory($group);

            $rows[] = [
                'street' => $street,
                'area_name' => $street,
                'barangay_name' => $group->first()->barangay_name ?? 'San Agustin',
                'historical_count' => $group->count(),
                'weekly_average' => round($weeklyAverage, 2),
                'baseline_count' => round($baseline, 1),
                'predicted_count' => (int) round($projected),
                'predicted_exact' => round($projected, 1),
                'lower' => round($lower, 1),
                'upper' => round($upper, 1),
                'change_percent' => (int) round($change),
                'trend' => $fit['slope'] > 0.05 ? 'rising' : ($fit['slope'] < -0.05 ? 'falling' : 'flat'),
                'r_squared' => round($fit['r_squared'], 3),
                'confidence' => $this->forecastConfidence($fit['r_squared'], $weeks, $group->count()),
                'risk_level' => $this->forecastRiskLevel($change),
                'top_category' => $leading['name'] ?? null,
                'latitude' => round($group->avg('latitude'), 6),
                'longitude' => round($group->avg('longitude'), 6),
            ];
        }

        $considered = count($rows);
        $tally = array_count_values(array_column($rows, 'risk_level'));
        $riskCounts = [
            'high' => $tally['HIGH'] ?? 0,
            'moderate' => $tally['MODERATE'] ?? 0,
            'low' => $tally['LOW'] ?? 0,
        ];

        usort(
            $rows,
            fn ($a, $b) => [$b['predicted_count'], $b['historical_count']] <=> [$a['predicted_count'], $a['historical_count']]
        );

        return [array_slice($rows, 0, 10), $excluded, $considered, $riskCounts];
    }

    /** The most frequent category in a set, with its share. */
    protected function leadingCategory(Collection $incidents): ?array
    {
        if ($incidents->isEmpty()) {
            return null;
        }

        $counts = $incidents->countBy(fn ($i) => $this->categoryOf($i))->sortDesc();

        return [
            'name' => (string) $counts->keys()->first(),
            'count' => (int) $counts->first(),
            'share' => (int) round($counts->first() / $incidents->count() * 100),
        ];
    }

    /**
     * The anchor month against the one before it, in week-of-month buckets. The
     * anchor month is usually incomplete, which is flagged rather than left for
     * the reader to mistake for a drop.
     */
    protected function monthComparison(string $crimeType, string $barangay, string $caseStatus, Carbon $anchor): array
    {
        $current = $anchor->copy()->startOfMonth();
        $previous = $current->copy()->subMonth();

        $bucketsFor = function (Carbon $month) use ($crimeType, $barangay, $caseStatus) {
            $rows = CrimeIncident::whereYear('incident_date', $month->year)
                ->whereMonth('incident_date', $month->month)
                ->when($crimeType !== '', fn ($q) => $q->where('crime_category_id', $crimeType))
                ->when($barangay !== '', fn ($q) => $q->where('barangay_id', $barangay))
                ->when($caseStatus !== '', fn ($q) => $q->where('status', $caseStatus))
                ->get(['incident_date']);

            $buckets = [0, 0, 0, 0];
            foreach ($rows as $row) {
                if (! $row->incident_date) {
                    continue;
                }
                $buckets[min(3, intdiv((int) $row->incident_date->format('j') - 1, 7))]++;
            }

            return $buckets;
        };

        return [
            'labels' => ['Days 1–7', 'Days 8–14', 'Days 15–21', 'Days 22+'],
            'previous_label' => $previous->format('M Y'),
            'current_label' => $current->format('M Y'),
            'previous' => $bucketsFor($previous),
            'current' => $bucketsFor($current),
            'current_is_partial' => $anchor->day < $anchor->daysInMonth,
            'current_through' => $anchor->toDateString(),
        ];
    }

    /**
     * Recent weekly counts against the six-month weekly mean, so "are we above
     * our own normal" is answerable at a glance.
     */
    protected function trendVersusAverage(array $series, Carbon $start, int $weeks, string $crimeType, string $barangay, string $caseStatus, Carbon $anchor): array
    {
        $take = max(1, min(self::TREND_COMPARISON_WEEKS, $weeks));
        $recent = array_slice($series, $weeks - $take);

        $labels = [];
        for ($i = 0; $i < $take; $i++) {
            $labels[] = $start->copy()->addDays(($weeks - $take + $i) * 7)->format('M j');
        }

        $windowDays = 180;
        $total = CrimeIncident::where('incident_date', '>=', $anchor->copy()->subDays($windowDays)->toDateString())
            ->where('incident_date', '<=', $anchor->copy()->endOfDay()->toDateTimeString())
            ->when($crimeType !== '', fn ($q) => $q->where('crime_category_id', $crimeType))
            ->when($barangay !== '', fn ($q) => $q->where('barangay_id', $barangay))
            ->when($caseStatus !== '', fn ($q) => $q->where('status', $caseStatus))
            ->count();

        $average = round($total / ($windowDays / 7), 2);

        return [
            'labels' => $labels,
            'current' => array_map('intval', array_values($recent)),
            'average' => array_fill(0, $take, $average),
            'average_value' => $average,
            'window_days' => $windowDays,
        ];
    }

    /** Plain-language caveats that belong on screen, not in a footnote. */
    protected function forecastNotes(int $sampleSize, int $weeks, array $fit, int $excludedStreets, Carbon $anchor): array
    {
        $notes = [];

        if ($sampleSize < 30) {
            $notes[] = "Small sample: {$sampleSize} incidents across {$weeks} weeks. Read the direction as indicative and the exact counts as rough.";
        }
        if ($fit['r_squared'] < 0.3) {
            $notes[] = 'Weak linear fit (R² ' . round($fit['r_squared'], 2) . '): the weekly counts scatter more than they trend, so the projection largely restates the recent average.';
        }
        if ($excludedStreets > 0) {
            $notes[] = $excludedStreets . ' street(s) had fewer than ' . self::MIN_FORECAST_SAMPLE . ' incidents in the window and were left out of the street projections.';
        }
        if ($anchor->lt(Carbon::now()->subDays(14))) {
            $notes[] = 'The newest incident on record is ' . $anchor->format('M j, Y') . '. The forecast runs forward from that date, not from today.';
        }

        return $notes;
    }

    /**
     * @return array<int, int> weekly incident counts, oldest week first
     */
    protected function weeklySeries(Collection $incidents, Carbon $start, int $weeks): array
    {
        $series = array_fill(0, $weeks, 0);
        $startDay = $start->copy()->startOfDay();

        foreach ($incidents as $incident) {
            if (! $incident->incident_date) {
                continue;
            }

            $offset = (int) floor(
                $startDay->diffInDays(Carbon::parse($incident->incident_date)->startOfDay(), false)
            );
            if ($offset < 0) {
                continue;
            }

            $weekIndex = intdiv($offset, 7);
            if ($weekIndex < $weeks) {
                $series[$weekIndex]++;
            }
        }

        return $series;
    }

    /**
     * Ordinary least squares over y = a + bx.
     *
     * Also returns the pieces a prediction interval needs — sample size, the
     * mean of x, the spread of x, and the residual standard error — so callers
     * can quote a band instead of a bare point estimate.
     *
     * @return array{slope: float, intercept: float, r_squared: float, n: int, x_mean: float, ss_xx: float, std_error: float}
     */
    protected function linearFit(array $series): array
    {
        $n = count($series);
        if ($n < 2) {
            return [
                'slope' => 0.0,
                'intercept' => (float) ($series[0] ?? 0),
                'r_squared' => 0.0,
                'n' => $n,
                'x_mean' => 0.0,
                'ss_xx' => 0.0,
                'std_error' => 0.0,
            ];
        }

        $xMean = ($n - 1) / 2;
        $yMean = array_sum($series) / $n;

        $ssXY = 0.0;
        $ssXX = 0.0;
        foreach ($series as $x => $y) {
            $ssXY += ($x - $xMean) * ($y - $yMean);
            $ssXX += ($x - $xMean) ** 2;
        }

        $slope = $ssXX > 0 ? $ssXY / $ssXX : 0;
        $intercept = $yMean - $slope * $xMean;

        $ssRes = 0.0;
        $ssTot = 0.0;
        foreach ($series as $x => $y) {
            $ssRes += ($y - ($intercept + $slope * $x)) ** 2;
            $ssTot += ($y - $yMean) ** 2;
        }

        $rSquared = $ssTot > 0 ? max(0.0, 1 - $ssRes / $ssTot) : 0.0;
        $stdError = $n > 2 ? sqrt($ssRes / ($n - 2)) : 0.0;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r_squared' => $rSquared,
            'n' => $n,
            'x_mean' => $xMean,
            'ss_xx' => $ssXX,
            'std_error' => $stdError,
        ];
    }

    /**
     * Honest confidence: grows with regression fit quality and sample size,
     * capped well below 100% because a linear trend is a simplification.
     *
     * Sample credit needs 50 incidents to max out — ten was cheap enough that
     * a street with a fortnight of data scored the same as one with a year.
     */
    protected function forecastConfidence(float $rSquared, int $weeks, int $sampleSize): int
    {
        $confidence = 35
            + $rSquared * 40
            + min(10, $weeks)
            + min(10, $sampleSize / 5);

        return (int) round(max(35, min(90, $confidence)));
    }
}
