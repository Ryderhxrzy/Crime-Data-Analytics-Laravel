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
 * counts — a transparent trend projection, NOT a machine-learning model.
 * Confidence is derived from the regression fit (R²) and sample size.
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

    public function analyze(string $timePeriod, string $crimeType, string $barangay, string $caseStatus = ''): array
    {
        $incidents = $this->baseQuery($timePeriod, $crimeType, $barangay, $caseStatus)->get();

        $hotspots = $this->rankStreets($incidents, $timePeriod, $crimeType, $caseStatus);

        return [
            'crimes' => $this->crimePayload($incidents),
            'hotspots' => $hotspots,
            'summary' => $this->summary($incidents, $hotspots, $timePeriod, $crimeType, $barangay, $caseStatus),
            'monthly_trends' => $this->monthlyTrends($crimeType, $barangay, $caseStatus),
            'type_distribution' => $this->typeDistribution($incidents),
            'day_night' => $this->dayNightSplit($incidents),
            'hourly' => $this->hourlyDistribution($incidents),
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

    public function forecast(int $historicalDays, int $forecastDays, string $crimeType = '', string $barangay = ''): array
    {
        $start = Carbon::now()->subDays($historicalDays)->startOfDay();
        $weeks = max(4, (int) floor($historicalDays / 7));
        $forecastWeeks = max(1, (int) ceil($forecastDays / 7));

        $query = CrimeIncident::with('barangay')
            ->where('incident_date', '>=', $start->toDateString());

        if ($crimeType !== '') {
            $query->where('crime_category_id', $crimeType);
        }
        if ($barangay !== '') {
            $query->where('barangay_id', $barangay);
        }

        $incidents = $query->get();

        $predictions = [];
        foreach ($incidents->filter(fn ($i) => $i->barangay_id)->groupBy('barangay_id') as $barangayId => $group) {
            $series = $this->weeklySeries($group, $start, $weeks);
            $fit = $this->linearFit($series);

            $projected = 0.0;
            for ($k = 1; $k <= $forecastWeeks; $k++) {
                $projected += max(0, $fit['intercept'] + $fit['slope'] * ($weeks - 1 + $k));
            }
            // Scale down if the forecast horizon is a partial week
            $projected = $projected * min(1, $forecastDays / ($forecastWeeks * 7));

            $currentTotal = array_sum($series);
            $currentWeeklyAvg = $currentTotal / $weeks;
            $expectedBaseline = $currentWeeklyAvg * $forecastDays / 7;

            $barangayModel = $group->first()->barangay;

            $predictions[] = [
                'barangay_id' => (int) $barangayId,
                'area_name' => $barangayModel->barangay_name ?? 'Unknown',
                'latitude' => (float) ($barangayModel->latitude ?: round($group->avg('latitude'), 6)),
                'longitude' => (float) ($barangayModel->longitude ?: round($group->avg('longitude'), 6)),
                'historical_count' => $currentTotal,
                'weekly_average' => round($currentWeeklyAvg, 2),
                'predicted_count' => (int) round($projected),
                'change_percent' => $expectedBaseline > 0
                    ? round(($projected - $expectedBaseline) / $expectedBaseline * 100)
                    : 0,
                'trend' => $fit['slope'] > 0.05 ? 'rising' : ($fit['slope'] < -0.05 ? 'falling' : 'flat'),
                'confidence' => $this->forecastConfidence($fit['r_squared'], $weeks, $currentTotal),
            ];
        }

        usort($predictions, fn ($a, $b) => $b['predicted_count'] <=> $a['predicted_count']);

        // Relative risk levels within this forecast batch
        $maxPredicted = $predictions ? max(array_column($predictions, 'predicted_count')) : 0;
        foreach ($predictions as &$p) {
            $ratio = $maxPredicted > 0 ? $p['predicted_count'] / $maxPredicted : 0;
            $p['risk_level'] = $ratio >= 0.66 ? 'HIGH' : ($ratio >= 0.33 ? 'MEDIUM' : 'LOW');
        }
        unset($p);

        return [
            'method' => 'Least-squares linear regression over weekly incident counts (trend projection, not machine learning)',
            'historical_days' => $historicalDays,
            'forecast_days' => $forecastDays,
            'citywide' => [
                'historical_count' => $incidents->count(),
                'predicted_count' => array_sum(array_column($predictions, 'predicted_count')),
            ],
            'predictions' => array_slice($predictions, 0, 15),
        ];
    }

    /**
     * @return array<int, int> weekly incident counts, oldest week first
     */
    protected function weeklySeries(Collection $incidents, Carbon $start, int $weeks): array
    {
        $series = array_fill(0, $weeks, 0);

        foreach ($incidents as $incident) {
            if (! $incident->incident_date) {
                continue;
            }
            $weekIndex = (int) floor($start->diffInDays(Carbon::parse($incident->incident_date)) / 7);
            if ($weekIndex >= 0 && $weekIndex < $weeks) {
                $series[$weekIndex]++;
            }
        }

        return $series;
    }

    /**
     * Ordinary least squares over y = a + bx.
     *
     * @return array{slope: float, intercept: float, r_squared: float}
     */
    protected function linearFit(array $series): array
    {
        $n = count($series);
        if ($n < 2) {
            return ['slope' => 0, 'intercept' => $series[0] ?? 0, 'r_squared' => 0];
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

        $rSquared = $ssTot > 0 ? max(0, 1 - $ssRes / $ssTot) : 0;

        return ['slope' => $slope, 'intercept' => $intercept, 'r_squared' => $rSquared];
    }

    /**
     * Honest confidence: grows with regression fit quality and sample size,
     * capped well below 100% because a linear trend is a simplification.
     */
    protected function forecastConfidence(float $rSquared, int $weeks, int $sampleSize): int
    {
        $confidence = 35
            + $rSquared * 40
            + min(10, $weeks)
            + min(10, $sampleSize);

        return (int) round(max(35, min(90, $confidence)));
    }
}
