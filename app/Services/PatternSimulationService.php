<?php

namespace App\Services;

use App\Models\CrimeIncident;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Evidence-based "what-if" crime pattern simulator.
 *
 * There is no before/after intervention data in this system (no CCTV inventory,
 * no controlled trials), so this cannot be a trained predictive model. Instead it
 * projects REAL baseline incident data (from crime_department_crime_incidents)
 * forward using average effect sizes published in crime-prevention research.
 * Every coefficient below cites its source so results stay explainable.
 */
class PatternSimulationService
{
    public const INTERVENTIONS = [
        'cctv' => [
            'label' => 'CCTV Infrastructure',
            'levels' => ['none' => 0, 'partial' => 8, 'full' => 18],
            'categories' => ['LE_THEFT', 'LE_ROBBERY', 'LE_BURGLARY', 'LE_VEHICLE', 'LE_FRAUD'],
            'source' => 'Welsh & Farrington CCTV meta-analysis - avg ~16-19% reduction in property crime',
        ],
        'lighting' => [
            'label' => 'Street Lighting',
            'flat' => 20,
            'categories' => ['LE_THEFT', 'LE_ROBBERY', 'LE_ASSAULT', 'LE_BURGLARY', 'LE_SEXUAL'],
            'night_only' => true,
            'source' => 'Farrington & Welsh street lighting meta-analysis - ~21% reduction',
        ],
        'patrol' => [
            'label' => 'Police Patrol',
            'levels' => [0 => 0, 1 => 10, 2 => 15],
            'categories' => ['LE_ROBBERY', 'LE_ASSAULT', 'LE_DRUGS', 'LE_DOMESTIC'],
            'source' => 'Hotspot policing studies (Braga et al.) - ~10-15% reduction in patrolled areas',
        ],
        'community' => [
            'label' => 'Community Watch',
            'flat' => 9,
            'categories' => ['LE_THEFT', 'LE_BURGLARY', 'LE_FRAUD'],
            'source' => 'Neighborhood watch program studies - ~8-10% average reduction',
        ],
        'checkpoints' => [
            'label' => 'Checkpoints / Access Control',
            'flat' => 13,
            'categories' => ['LE_VEHICLE', 'LE_DRUGS', 'LE_ROBBERY'],
            'source' => 'Access-control intervention studies - ~10-15% reduction',
        ],
    ];

    public function run(array $params): array
    {
        $mode = $params['mode'] ?? 'predictive';
        $days = (int) ($params['time_period_days'] ?? 90);
        $barangayId = ! empty($params['barangay_id']) ? (int) $params['barangay_id'] : null;
        $categoryId = ! empty($params['category_id']) ? (int) $params['category_id'] : null;

        $baseline = $this->getBaseline($days, $barangayId, $categoryId);

        return match ($mode) {
            'historical' => $this->buildHistoricalResult($baseline),
            'randomized' => $this->buildStressTestResult($baseline, $params),
            default => $this->buildPredictiveResult($baseline, $params),
        };
    }

    protected function getBaseline(int $days, ?int $barangayId, ?int $categoryId): Collection
    {
        $query = CrimeIncident::with(['category', 'barangay'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if ($days > 0) {
            $query->where('incident_date', '>=', Carbon::now()->subDays($days)->toDateString());
        }

        if ($barangayId) {
            $query->where('barangay_id', $barangayId);
        }

        if ($categoryId) {
            $query->where('crime_category_id', $categoryId);
        }

        return $query->get();
    }

    protected function buildHistoricalResult(Collection $baseline): array
    {
        $hotspots = $this->buildHotspots($baseline);

        return [
            'mode' => 'historical',
            'baseline' => [
                'total_incidents' => $baseline->count(),
                'markers' => $this->markersFor($baseline),
            ],
            'simulated' => [
                'total_incidents' => $baseline->count(),
                'prevented_incidents' => 0,
                'reduction_percent' => 0,
                'markers' => $this->markersFor($baseline),
                'hotspots' => $hotspots,
            ],
            'metrics' => [
                'confidence_percent' => 100,
                'displacement_risk' => 'N/A',
                'displacement_note' => 'Historical mode shows actual recorded incidents - no intervention projected.',
                'high_risk_coverage_percent' => $this->highRiskCoveragePercent($baseline, $hotspots),
            ],
            'analysis_text' => "Showing {$baseline->count()} actual recorded incidents for the selected filters, with no simulated intervention applied.",
            'recommendations' => $this->recommendationsFor($baseline),
            'settings_applied' => null,
        ];
    }

    protected function buildPredictiveResult(Collection $baseline, array $params): array
    {
        $settings = $this->normalizeSettings($params);

        $simulated = collect();
        $prevented = collect();

        foreach ($baseline as $incident) {
            $survival = $this->incidentSurvivalProbability($incident, $settings);
            if ($this->stableRandom((int) $incident->id) < $survival) {
                $simulated->push($incident);
            } else {
                $prevented->push($incident);
            }
        }

        $reductionPercent = $baseline->count() > 0
            ? round($prevented->count() / $baseline->count() * 100, 1)
            : 0;

        $displacement = $this->displacementRisk($settings);
        $hotspots = $this->buildHotspots($simulated);

        return [
            'mode' => 'predictive',
            'baseline' => [
                'total_incidents' => $baseline->count(),
                'markers' => $this->markersFor($baseline),
            ],
            'simulated' => [
                'total_incidents' => $simulated->count(),
                'prevented_incidents' => $prevented->count(),
                'reduction_percent' => $reductionPercent,
                'markers' => $this->markersFor($simulated),
                'hotspots' => $hotspots,
            ],
            'metrics' => [
                'confidence_percent' => $this->confidenceScore($baseline->count()),
                'displacement_risk' => $displacement['level'],
                'displacement_note' => $displacement['note'],
                'high_risk_coverage_percent' => $this->highRiskCoveragePercent($simulated, $hotspots),
            ],
            'analysis_text' => $this->analysisText($settings, $reductionPercent, $baseline->count(), $prevented->count()),
            'recommendations' => $this->recommendationsFor($baseline),
            'settings_applied' => $settings,
        ];
    }

    protected function buildStressTestResult(Collection $baseline, array $params): array
    {
        $multiplier = max(1.1, min(3.0, (float) ($params['stress_multiplier'] ?? 1.5)));

        if ($baseline->isEmpty()) {
            return [
                'mode' => 'randomized',
                'baseline' => ['total_incidents' => 0, 'markers' => []],
                'simulated' => ['total_incidents' => 0, 'prevented_incidents' => 0, 'reduction_percent' => 0, 'markers' => [], 'hotspots' => []],
                'metrics' => ['confidence_percent' => 0, 'displacement_risk' => 'N/A', 'displacement_note' => 'No baseline data for the selected filters.'],
                'analysis_text' => 'No historical incidents in the selected period/area to project a surge from.',
                'recommendations' => [],
                'settings_applied' => ['stress_multiplier' => $multiplier],
            ];
        }

        $targetCount = (int) round($baseline->count() * $multiplier);
        $extraNeeded = max(0, $targetCount - $baseline->count());

        $synthetic = collect();
        for ($i = 0; $i < $extraNeeded; $i++) {
            $sourceIndex = (int) ($this->stableRandom($i * 7919 + 13) * $baseline->count());
            $source = $baseline[$sourceIndex] ?? $baseline->first();
            $synthetic->push($this->jitteredClone($source, $i));
        }

        $projected = $baseline->concat($synthetic);
        $hotspots = $this->buildHotspots($projected);

        return [
            'mode' => 'randomized',
            'baseline' => [
                'total_incidents' => $baseline->count(),
                'markers' => $this->markersFor($baseline),
            ],
            'simulated' => [
                'total_incidents' => $projected->count(),
                'prevented_incidents' => -$extraNeeded,
                'reduction_percent' => $baseline->count() > 0 ? -round(($extraNeeded / $baseline->count()) * 100, 1) : 0,
                'markers' => $this->markersFor($projected),
                'hotspots' => $hotspots,
            ],
            'metrics' => [
                'confidence_percent' => $this->confidenceScore($baseline->count()),
                'displacement_risk' => 'N/A',
                'displacement_note' => 'Stress test resamples the real category/location distribution to project a surge - not an intervention scenario.',
                'high_risk_coverage_percent' => $this->highRiskCoveragePercent($projected, $hotspots),
            ],
            'analysis_text' => "Stress test projects incidents rising from {$baseline->count()} to ~{$projected->count()} (+{$extraNeeded}) if the current crime pattern intensifies by ".round(($multiplier - 1) * 100).'%, following the same category/location proportions seen in real data.',
            'recommendations' => $this->recommendationsFor($projected),
            'settings_applied' => ['stress_multiplier' => $multiplier],
        ];
    }

    protected function normalizeSettings(array $params): array
    {
        return [
            'cctv' => in_array($params['cctv'] ?? 'none', ['none', 'partial', 'full', 'custom'], true) ? $params['cctv'] : 'none',
            'cctv_custom_units' => max(0, min(10, (int) ($params['cctv_custom_units'] ?? 0))),
            'lighting' => filter_var($params['lighting'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'patrol' => max(0, min(2, (int) ($params['patrol'] ?? 0))),
            'community' => filter_var($params['community'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'checkpoints' => filter_var($params['checkpoints'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    protected function cctvEffectPercent(array $settings): float
    {
        if ($settings['cctv'] === 'custom') {
            return min(25, $settings['cctv_custom_units'] * 2);
        }

        return self::INTERVENTIONS['cctv']['levels'][$settings['cctv']] ?? 0;
    }

    protected function incidentSurvivalProbability(CrimeIncident $incident, array $settings): float
    {
        $categoryCode = $incident->category->category_code ?? null;
        $isNight = $this->isNightIncident($incident);
        $survival = 1.0;

        $cctvEffect = $this->cctvEffectPercent($settings);
        if ($cctvEffect > 0 && in_array($categoryCode, self::INTERVENTIONS['cctv']['categories'], true)) {
            $survival *= (1 - $cctvEffect / 100);
        }

        if ($settings['lighting'] && $isNight && in_array($categoryCode, self::INTERVENTIONS['lighting']['categories'], true)) {
            $survival *= (1 - self::INTERVENTIONS['lighting']['flat'] / 100);
        }

        $patrolEffect = self::INTERVENTIONS['patrol']['levels'][$settings['patrol']] ?? 0;
        if ($patrolEffect > 0 && in_array($categoryCode, self::INTERVENTIONS['patrol']['categories'], true)) {
            $survival *= (1 - $patrolEffect / 100);
        }

        if ($settings['community'] && in_array($categoryCode, self::INTERVENTIONS['community']['categories'], true)) {
            $survival *= (1 - self::INTERVENTIONS['community']['flat'] / 100);
        }

        if ($settings['checkpoints'] && in_array($categoryCode, self::INTERVENTIONS['checkpoints']['categories'], true)) {
            $survival *= (1 - self::INTERVENTIONS['checkpoints']['flat'] / 100);
        }

        // Floor: no realistic combination of interventions is modeled as eliminating
        // more than ~85% of a crime category - keeps projections defensible.
        return max(0.15, $survival);
    }

    protected function isNightIncident($incident): bool
    {
        if (empty($incident->incident_time)) {
            return false;
        }

        $hour = (int) Carbon::parse($incident->incident_time)->format('H');

        return $hour >= 18 || $hour < 6;
    }

    /**
     * Deterministic pseudo-random float in [0, 1) seeded by an integer, so the
     * same settings always produce the same simulated outcome (reproducible,
     * not Math.random() noise).
     */
    protected function stableRandom(int $seed): float
    {
        return (($seed * 2654435761) % 1000) / 1000;
    }

    protected function markersFor(Collection $incidents): array
    {
        return $incidents->map(fn ($i) => [
            'id' => $i->id,
            'lat' => (float) $i->latitude,
            'lng' => (float) $i->longitude,
            'category' => $i->category->category_name ?? 'Unknown',
            'barangay' => $i->barangay->barangay_name ?? 'Unknown',
            'severity' => $i->category->severity_level ?? 'medium',
        ])->values()->toArray();
    }

    protected function buildHotspots(Collection $incidents): array
    {
        return $incidents->groupBy('barangay_id')
            ->map(function ($group, $barangayId) {
                $barangay = $group->first()->barangay ?? null;

                return [
                    'barangay_id' => $barangayId,
                    'barangay_name' => $barangay->barangay_name ?? 'Unknown',
                    'lat' => round($group->avg('latitude'), 6),
                    'lng' => round($group->avg('longitude'), 6),
                    'incident_count' => $group->count(),
                ];
            })
            ->filter(fn ($h) => $h['incident_count'] >= 2)
            ->sortByDesc('incident_count')
            ->values()
            ->take(8)
            ->toArray();
    }

    protected function confidenceScore(int $sampleSize): float
    {
        return round(min(90, 50 + min(35, sqrt($sampleSize) * 4)), 1);
    }

    /**
     * Share of affected barangays that qualify as hotspots (2+ incidents).
     */
    protected function highRiskCoveragePercent(Collection $incidents, array $hotspots): float
    {
        $distinctBarangays = $incidents->pluck('barangay_id')->filter()->unique()->count();

        if ($distinctBarangays === 0) {
            return 0;
        }

        return round(min(100, count($hotspots) / $distinctBarangays * 100), 1);
    }

    protected function displacementRisk(array $settings): array
    {
        $hardening = $settings['checkpoints'] || $settings['patrol'] === 2;
        $softMeasures = $settings['lighting'] || $settings['community'] || in_array($settings['cctv'], ['partial', 'full'], true);

        if ($hardening && ! $softMeasures) {
            return ['level' => 'Medium-High', 'note' => 'Access control alone tends to displace crime nearby rather than reduce it (target-hardening effect).'];
        }

        if ($hardening) {
            return ['level' => 'Medium', 'note' => 'Some displacement risk from checkpoints, partially offset by softer measures.'];
        }

        return ['level' => 'Low', 'note' => 'CCTV/lighting-led interventions are more associated with diffusion of benefit than displacement (Weisburd et al.).'];
    }

    protected function analysisText(array $settings, float $reductionPercent, int $baselineCount, int $preventedCount): string
    {
        $active = [];
        if ($settings['patrol'] > 0) {
            $active[] = 'Police Patrol';
        }
        if ($settings['cctv'] !== 'none') {
            $active[] = 'CCTV';
        }
        if ($settings['lighting']) {
            $active[] = 'Street Lighting';
        }
        if ($settings['community']) {
            $active[] = 'Community Watch';
        }
        if ($settings['checkpoints']) {
            $active[] = 'Checkpoints';
        }

        if (empty($active)) {
            return "No interventions applied. Baseline shows {$baselineCount} incidents in the selected period with no projected change.";
        }

        $list = implode(', ', $active);

        return "Applying {$list} to the real baseline of {$baselineCount} incidents is projected to prevent approximately {$preventedCount} incidents ({$reductionPercent}% reduction), based on published crime-prevention research averages for the applicable crime categories.";
    }

    protected function recommendationsFor(Collection $incidents): array
    {
        $recommendations = [];

        $byBarangay = $incidents->filter(fn ($i) => $i->barangay_id)->groupBy('barangay_id');

        foreach ($byBarangay->sortByDesc(fn ($g) => $g->count())->take(5) as $barangayId => $group) {
            $barangay = $group->first()->barangay ?? null;
            $nightCount = $group->filter(fn ($i) => $this->isNightIncident($i))->count();
            $nightRatio = $group->count() ? $nightCount / $group->count() : 0;

            $scored = [];
            foreach (self::INTERVENTIONS as $key => $def) {
                $applicable = $group->filter(function ($i) use ($def) {
                    $code = $i->category->category_code ?? null;
                    if (! in_array($code, $def['categories'], true)) {
                        return false;
                    }

                    return empty($def['night_only']) || $this->isNightIncident($i);
                })->count();

                if ($applicable > 0) {
                    $effect = $def['flat'] ?? ($def['levels'][array_key_last($def['levels'])] ?? 15);
                    $scored[$key] = [
                        'label' => $def['label'],
                        'addressable_incidents' => $applicable,
                        'estimated_reduction' => round($applicable * $effect / 100, 1),
                        'source' => $def['source'],
                    ];
                }
            }

            uasort($scored, fn ($a, $b) => $b['estimated_reduction'] <=> $a['estimated_reduction']);
            $top = array_slice($scored, 0, 2, true);

            $dominantCategory = $group->countBy(fn ($i) => $i->category->category_name ?? 'Unknown')
                ->sortDesc()->keys()->first();

            $recommendations[] = [
                'barangay_id' => $barangayId,
                'barangay_name' => $barangay->barangay_name ?? 'Unknown',
                'incident_count' => $group->count(),
                'dominant_category' => $dominantCategory,
                'night_ratio_percent' => round($nightRatio * 100),
                'recommended_interventions' => array_values($top),
            ];
        }

        return $recommendations;
    }

    protected function jitteredClone($source, int $seed): object
    {
        $jitterLat = $source->latitude + ($this->stableRandom((int) $source->id + $seed * 31 + 1) - 0.5) * 0.004;
        $jitterLng = $source->longitude + ($this->stableRandom((int) $source->id + $seed * 37 + 2) - 0.5) * 0.004;

        return (object) [
            'id' => 'sim-'.$source->id.'-'.$seed,
            'latitude' => $jitterLat,
            'longitude' => $jitterLng,
            'incident_time' => $source->incident_time,
            'barangay_id' => $source->barangay_id,
            'barangay' => $source->barangay,
            'category' => $source->category,
        ];
    }
}
