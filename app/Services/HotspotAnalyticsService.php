<?php

namespace App\Services;

use App\Models\CrimeIncident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Hotspot analytics: ranks barangays by a composite risk score instead of raw
 * incident counts, so a small-but-dangerous area is not drowned out by a large
 * barangay that simply has more people.
 *
 * Composite Risk Score (0-100) =
 *     50% volume  (incident count blended with population-adjusted rate
 *                  per 1,000 residents, when population data exists)
 *   + 30% severity (average severity of the incidents: critical=4 ... low=1)
 *   + 20% trend    (percent change vs the previous equal-length period)
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

    public function analyze(string $timePeriod, string $crimeType, string $barangay, string $caseStatus = ''): array
    {
        $incidents = $this->baseQuery($timePeriod, $crimeType, $barangay, $caseStatus)->get();

        $hotspots = $this->rankBarangays($incidents, $timePeriod, $crimeType, $caseStatus);

        return [
            'crimes' => $this->crimePayload($incidents),
            'hotspots' => $hotspots,
            'summary' => $this->summary($incidents, $hotspots, $timePeriod, $crimeType, $barangay, $caseStatus),
            'monthly_trends' => $this->monthlyTrends($crimeType, $barangay, $caseStatus),
            'type_distribution' => $this->typeDistribution($incidents),
            'day_night' => $this->dayNightSplit($incidents),
        ];
    }

    protected function baseQuery(string $timePeriod, string $crimeType, string $barangay, string $caseStatus)
    {
        $query = CrimeIncident::with(['category', 'barangay'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($timePeriod !== 'all') {
            $query->where('incident_date', '>=', Carbon::now()->subDays((int) $timePeriod)->toDateString());
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

    protected function crimePayload(Collection $incidents): array
    {
        return $incidents->map(fn ($crime) => [
            'id' => $crime->id,
            'incident_title' => $crime->incident_title,
            'incident_date' => $crime->incident_date?->format('Y-m-d'),
            'latitude' => (float) $crime->latitude,
            'longitude' => (float) $crime->longitude,
            'barangay_name' => $crime->barangay?->barangay_name ?? 'Unknown',
            'barangay_id' => $crime->barangay_id,
            'crime_category_id' => $crime->crime_category_id,
            'category_name' => $crime->category?->category_name ?? 'Unknown',
            'color_code' => $crime->category?->color_code ?? '#274d4c',
            'clearance_status' => $crime->clearance_status,
            'location' => $crime->address_details,
        ])->values()->toArray();
    }

    protected function rankBarangays(Collection $incidents, string $timePeriod, string $crimeType, string $caseStatus): array
    {
        $groups = $incidents->filter(fn ($i) => $i->barangay_id)->groupBy('barangay_id');

        if ($groups->isEmpty()) {
            return [];
        }

        // First pass: raw metrics per barangay
        $rows = [];
        foreach ($groups as $barangayId => $group) {
            $barangayModel = $group->first()->barangay;
            $count = $group->count();
            $population = (int) ($barangayModel->population ?? 0);
            $areaSqkm = (float) ($barangayModel->area_sqkm ?? 0);

            $severityIndex = $group->avg(
                fn ($i) => self::SEVERITY_WEIGHTS[$i->category->severity_level ?? 'medium'] ?? 2
            );

            $trend = $this->trendChange((int) $barangayId, $timePeriod, $crimeType, $caseStatus);
            $cleared = $group->where('clearance_status', 'cleared')->count();
            $night = $group->filter(fn ($i) => $this->isNight($i))->count();
            $topCategory = $group->countBy(fn ($i) => $i->category->category_name ?? 'Unknown')
                ->sortDesc()->keys()->first();

            $rows[] = [
                'barangay_id' => (int) $barangayId,
                'area_name' => $barangayModel->barangay_name ?? 'Unknown',
                'incident_count' => $count,
                'population' => $population ?: null,
                'area_sqkm' => $areaSqkm ?: null,
                'crime_rate_per_1000' => $population > 0 ? round($count / $population * 1000, 2) : null,
                'density_per_sqkm' => $areaSqkm > 0 ? round($count / $areaSqkm, 2) : null,
                'severity_index' => round($severityIndex, 2),
                'trend_direction' => $trend['direction'],
                'trend_percent' => $trend['percent'],
                'cleared' => $cleared,
                'uncleared' => $count - $cleared,
                'clearance_rate' => round($cleared / $count * 100),
                'night_percent' => round($night / $count * 100),
                'top_category' => $topCategory,
                'latitude' => round($group->avg('latitude'), 6),
                'longitude' => round($group->avg('longitude'), 6),
            ];
        }

        // Second pass: normalize against the result set and compute composite score
        $maxCount = max(array_column($rows, 'incident_count'));
        $rates = array_filter(array_column($rows, 'crime_rate_per_1000'), fn ($r) => $r !== null);
        $maxRate = $rates ? max($rates) : 0;

        foreach ($rows as &$row) {
            $normCount = $maxCount > 0 ? $row['incident_count'] / $maxCount * 100 : 0;
            $normRate = ($row['crime_rate_per_1000'] !== null && $maxRate > 0)
                ? $row['crime_rate_per_1000'] / $maxRate * 100
                : null;

            $volumeScore = $normRate !== null ? ($normCount + $normRate) / 2 : $normCount;
            $severityScore = ($row['severity_index'] - 1) / 3 * 100;
            $trendScore = max(0, min(100, 50 + $row['trend_percent']));

            $row['risk_score'] = round($volumeScore * 0.5 + $severityScore * 0.3 + $trendScore * 0.2, 1);
            $row['risk_level'] = $this->riskLevel($row['risk_score']);
        }
        unset($row);

        usort($rows, fn ($a, $b) => $b['risk_score'] <=> $a['risk_score']);

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
     * Percent change of this barangay's incidents vs the previous equal-length period.
     */
    protected function trendChange(int $barangayId, string $timePeriod, string $crimeType, string $caseStatus): array
    {
        $days = $timePeriod !== 'all' ? (int) $timePeriod : self::DEFAULT_TREND_WINDOW;

        $filters = function ($query) use ($crimeType, $caseStatus) {
            if ($crimeType !== '') {
                $query->where('crime_category_id', $crimeType);
            }
            if ($caseStatus !== '') {
                $query->where('status', $caseStatus);
            }
        };

        $current = CrimeIncident::where('barangay_id', $barangayId)
            ->where('incident_date', '>=', Carbon::now()->subDays($days)->toDateString())
            ->tap($filters)
            ->count();

        $previous = CrimeIncident::where('barangay_id', $barangayId)
            ->whereBetween('incident_date', [
                Carbon::now()->subDays($days * 2)->toDateString(),
                Carbon::now()->subDays($days)->toDateString(),
            ])
            ->tap($filters)
            ->count();

        if ($previous === 0) {
            return $current > 0
                ? ['direction' => 'increasing', 'percent' => 100]
                : ['direction' => 'stable', 'percent' => 0];
        }

        $percent = round(($current - $previous) / $previous * 100);

        return [
            'direction' => $percent > 10 ? 'increasing' : ($percent < -10 ? 'decreasing' : 'stable'),
            'percent' => $percent,
        ];
    }

    protected function summary(Collection $incidents, array $hotspots, string $timePeriod, string $crimeType, string $barangay, string $caseStatus): array
    {
        $cleared = $incidents->where('clearance_status', 'cleared')->count();
        $total = $incidents->count();

        // Citywide trend: current vs previous equal-length period
        $days = $timePeriod !== 'all' ? (int) $timePeriod : self::DEFAULT_TREND_WINDOW;
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

        $currentPeriod = CrimeIncident::where('incident_date', '>=', Carbon::now()->subDays($days)->toDateString())
            ->tap($filters)->count();
        $previousPeriod = CrimeIncident::whereBetween('incident_date', [
            Carbon::now()->subDays($days * 2)->toDateString(),
            Carbon::now()->subDays($days)->toDateString(),
        ])->tap($filters)->count();

        if ($previousPeriod > 0) {
            $trendPercent = round(($currentPeriod - $previousPeriod) / $previousPeriod * 100);
        } else {
            $trendPercent = $currentPeriod > 0 ? 100 : 0;
        }

        $riskCounts = array_count_values(array_column($hotspots, 'risk_level'));

        return [
            'total_incidents' => $total,
            'affected_barangays' => count($hotspots),
            'risk_counts' => [
                'critical' => $riskCounts['CRITICAL'] ?? 0,
                'high' => $riskCounts['HIGH'] ?? 0,
                'medium' => $riskCounts['MEDIUM'] ?? 0,
                'low' => $riskCounts['LOW'] ?? 0,
            ],
            'highest_risk' => $hotspots[0] ?? null,
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

        for ($i = 11; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $labels[] = $date->format('M');

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
        $counts = $incidents->countBy(fn ($i) => $i->category->category_name ?? 'Unknown')->sortDesc();

        return [
            'labels' => $counts->keys()->values()->toArray(),
            'values' => $counts->values()->toArray(),
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
