<?php

namespace App\Services;

use App\Models\SanAgustinIncident;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI crime-pattern analysis for Barangay San Agustin, powered by Gemini.
 *
 * Designed to stay well inside the Gemini free-tier limits:
 *   - Results are cached for CACHE_HOURS keyed by period + a data fingerprint,
 *     so re-running the same analysis never re-calls the API.
 *   - Only compact aggregates (counts, not raw rows) are sent, keeping input
 *     tokens small.
 *   - Output is capped via maxOutputTokens and thinking is disabled.
 */
class GeminiPatternAnalysisService
{
    private const CACHE_HOURS = 6;

    /** Hard cap on generated tokens (thinking included) — keeps every call cheap */
    private const MAX_OUTPUT_TOKENS = 4096;

    /**
     * Street suggestions are deliberately small (3 suggestions, terse fields)
     * and cached for a whole day — users click street after street on the map,
     * so this is the endpoint most likely to burn the free-tier quota.
     */
    private const STREET_MAX_OUTPUT_TOKENS = 1024;
    private const STREET_CACHE_HOURS = 24;

    public function analyze(int $days = 180): array
    {
        $days = max(30, min(730, $days));

        $incidents = $this->loadIncidents($days);

        if ($incidents->isEmpty()) {
            return [
                'success' => false,
                'error'   => 'No San Agustin crime records found in the selected period, so there is nothing to analyze.',
            ];
        }

        $aggregates = $this->buildAggregates($incidents, $days);

        // Fingerprint = same data + same period → serve the cached verdict and
        // spend zero API quota.
        $fingerprint = md5($days . '|' . $incidents->count() . '|' . ($incidents->max('date') ?? ''));
        $cacheKey = 'gemini_pattern_analysis_v1_' . $fingerprint;

        $cached = Cache::get($cacheKey);
        if ($cached) {
            $cached['meta']['from_cache'] = true;
            return $cached;
        }

        $aiResult = $this->callGemini($this->buildPrompt($aggregates));

        if (isset($aiResult['error'])) {
            return ['success' => false, 'error' => $aiResult['error']];
        }

        $result = [
            'success' => true,
            'meta' => [
                'barangay'      => 'San Agustin',
                'data_source'   => 'real',
                'model'         => config('services.gemini.model'),
                'period_days'   => $days,
                'period_start'  => now()->subDays($days)->toDateString(),
                'period_end'    => now()->toDateString(),
                'records_used'  => $incidents->count(),
                'generated_at'  => now()->toIso8601String(),
                'from_cache'    => false,
            ],
            'analysis' => $aiResult,
        ];

        Cache::put($cacheKey, $result, now()->addHours(self::CACHE_HOURS));

        return $result;
    }

    /**
     * What-if simulation analysis. Same output shape as analyze(), but the AI
     * reasons about a scenario instead of the raw baseline:
     *   - scenario_type 'risk'        → safeguards are ABSENT; project how much
     *     HIGHER crime gets, plus the prevention needed to avoid it.
     *   - scenario_type 'prevention'  → measures are DEPLOYED; project whether
     *     crime goes UP or DOWN and by how much.
     *
     * @param array $scenario scenario_type, missing_safeguards[], prevention_measures[],
     *                        crime_types[], focus, streets[]
     */
    public function analyzeSimulation(int $days, array $scenario): array
    {
        $days = max(30, min(730, $days));

        $incidents = $this->loadIncidents($days);

        if ($incidents->isEmpty()) {
            return [
                'success' => false,
                'error'   => 'No San Agustin crime records found in the selected period, so there is no baseline to simulate from.',
            ];
        }

        // Narrow the baseline to the scenario focus so the AI reasons about the
        // relevant slice (falls back to the full set if a filter empties it).
        $scoped = $this->applyScenarioFocus($incidents, $scenario);

        $aggregates = $this->buildAggregates($scoped, $days);
        $aggregates['scenario'] = $this->scenarioForPrompt($scenario);

        $scenarioFingerprint = md5(json_encode($scenario) . '|' . $days . '|' . $scoped->count() . '|' . ($scoped->max('date') ?? ''));
        $cacheKey = 'gemini_pattern_sim_v1_' . $scenarioFingerprint;

        $cached = Cache::get($cacheKey);
        if ($cached) {
            $cached['meta']['from_cache'] = true;
            return $cached;
        }

        $aiResult = $this->callGemini($this->buildSimulationPrompt($aggregates, $scenario));

        if (isset($aiResult['error'])) {
            return ['success' => false, 'error' => $aiResult['error']];
        }

        $result = [
            'success' => true,
            'meta' => [
                'barangay'      => 'San Agustin',
                'data_source'   => 'simulation',
                'scenario'      => $this->scenarioForPrompt($scenario),
                'model'         => config('services.gemini.model'),
                'period_days'   => $days,
                'period_start'  => now()->subDays($days)->toDateString(),
                'period_end'    => now()->toDateString(),
                'records_used'  => $scoped->count(),
                'generated_at'  => now()->toIso8601String(),
                'from_cache'    => false,
            ],
            'analysis' => $aiResult,
        ];

        Cache::put($cacheKey, $result, now()->addHours(self::CACHE_HOURS));

        return $result;
    }

    /**
     * AI suggestions for one OR MORE streets analyzed together: what should
     * the barangay do there, given the combined crime profile. Output shape:
     *   { risk_level, summary, suggestions: [{action, street, time_window, rationale,
     *     expected_impact{direction, estimated_change_percent, explanation}, priority}] }
     */
    public function analyzeStreets(array $streets, int $days = 365): array
    {
        $days = max(30, min(730, $days));

        $streets = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $streets
        ))));
        if (empty($streets)) {
            return ['success' => false, 'error' => 'No street selected.'];
        }

        $wanted = array_map('mb_strtolower', $streets);
        $incidents = $this->loadIncidents($days)
            ->filter(fn ($i) => $i['street'] !== null && in_array(mb_strtolower($i['street']), $wanted, true))
            ->values();

        $label = implode(', ', $streets);

        if ($incidents->isEmpty()) {
            // No history on these streets — a sane static answer, no API call.
            return [
                'success' => true,
                'meta' => [
                    'barangay'     => 'San Agustin',
                    'street'       => $label,
                    'streets'      => $streets,
                    'model'        => null,
                    'period_days'  => $days,
                    'period_start' => now()->subDays($days)->toDateString(),
                    'period_end'   => now()->toDateString(),
                    'records_used' => 0,
                    'generated_at' => now()->toIso8601String(),
                    'from_cache'   => false,
                ],
                'analysis' => [
                    'risk_level' => 'low',
                    'summary'    => 'No crimes were recorded on ' . $label . ' in the selected period. Keep routine barangay patrol coverage and encourage residents to report suspicious activity.',
                    'suggestions' => [[
                        'action'      => 'Maintain routine patrol pass-through',
                        'street'      => $label,
                        'time_window' => '18:00-23:00',
                        'rationale'   => 'No recorded crimes here; an occasional evening pass keeps it that way.',
                        'expected_impact' => [
                            'direction' => 'stable',
                            'estimated_change_percent' => 0,
                            'explanation' => 'Preventive presence sustains the current low-crime state.',
                        ],
                        'priority' => 'low',
                    ]],
                ],
            ];
        }

        $sorted = $wanted;
        sort($sorted);
        $fingerprint = md5(implode('|', $sorted) . '|' . $days . '|' . $incidents->count() . '|' . ($incidents->max('date') ?? '') . '|' . $this->tableFingerprint());
        $cacheKey = 'gemini_street_suggest_v3_' . $fingerprint;

        $cached = Cache::get($cacheKey);
        if ($cached) {
            $cached['meta']['from_cache'] = true;
            return $cached;
        }

        $aiResult = $this->callGemini(
            $this->buildStreetPrompt($streets, $incidents, $days),
            'risk_level',
            self::STREET_MAX_OUTPUT_TOKENS
        );

        if (isset($aiResult['error'])) {
            return ['success' => false, 'error' => $aiResult['error']];
        }

        $result = [
            'success' => true,
            'meta' => [
                'barangay'     => 'San Agustin',
                'street'       => $label,
                'streets'      => $streets,
                'model'        => config('services.gemini.model'),
                'period_days'  => $days,
                'period_start' => now()->subDays($days)->toDateString(),
                'period_end'   => now()->toDateString(),
                'records_used' => $incidents->count(),
                'generated_at' => now()->toIso8601String(),
                'from_cache'   => false,
            ],
            'analysis' => $aiResult,
        ];

        Cache::put($cacheKey, $result, now()->addHours(self::STREET_CACHE_HOURS));

        return $result;
    }

    /**
     * SYSTEM-generated street suggestions — no AI call at all. Instant, free,
     * and immune to Gemini rate limits / gateway timeouts. This is the DEFAULT
     * engine for the street modal; Gemini (analyzeStreets) stays available as
     * a fallback via ?ai=1.
     *
     * Output shape matches analyzeStreets(), plus analysis.streets[] — one
     * separate section per selected street, each with suggestions driven by
     * that street's most frequent crime categories and peak hours.
     */
    public function suggestStreetsRuleBased(array $streets, int $days = 365): array
    {
        $days = max(30, min(730, $days));

        $streets = array_values(array_unique(array_filter(array_map(
            fn ($s) => trim((string) $s),
            $streets
        ))));
        if (empty($streets)) {
            return ['success' => false, 'error' => 'No street selected.'];
        }

        $sorted = array_map('mb_strtolower', $streets);
        sort($sorted);
        // Data fingerprint in the key → the cache refreshes the moment the
        // incident table changes (migrations, new records), never serving
        // stale street counts.
        $cacheKey = 'street_rules_v2_' . md5(implode('|', $sorted) . '|' . $days . '|' . $this->tableFingerprint());

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($streets, $days) {
            $all = $this->loadIncidents($days);
            $midpoint = now()->subDays((int) floor($days / 2))->toDateString();

            $riskRank = ['low' => 0, 'medium' => 1, 'high' => 2];
            $sections = [];
            $flat = [];
            $totalAll = 0;
            $worst = 'low';

            foreach ($streets as $street) {
                $inc = $all->filter(fn ($i) => $i['street'] !== null
                    && mb_strtolower($i['street']) === mb_strtolower($street))->values();

                $section = $this->buildStreetSection($street, $inc, $midpoint);
                $sections[] = $section;
                $totalAll += $section['total'];
                if ($riskRank[$section['risk_level']] > $riskRank[$worst]) {
                    $worst = $section['risk_level'];
                }
                foreach ($section['suggestions'] as $s) {
                    $flat[] = $s + ['street' => $street];
                }
            }

            $summary = 'Reviewed ' . $totalAll . ' recorded crime' . ($totalAll === 1 ? '' : 's')
                . ' across ' . count($streets) . ' street' . (count($streets) === 1 ? '' : 's')
                . ' in the last ' . $days . ' days. Suggestions are per street, matched to the crime'
                . ' categories most frequently committed there and their peak hours.';

            return [
                'success' => true,
                'meta' => [
                    'barangay'     => 'San Agustin',
                    'street'       => implode(', ', $streets),
                    'streets'      => $streets,
                    'engine'       => 'rules',
                    'model'        => 'system-rules',
                    'period_days'  => $days,
                    'period_start' => now()->subDays($days)->toDateString(),
                    'period_end'   => now()->toDateString(),
                    'records_used' => $totalAll,
                    'generated_at' => now()->toIso8601String(),
                    'from_cache'   => false,
                ],
                'analysis' => [
                    'risk_level' => $worst,
                    'summary'    => $summary,
                    'streets'    => $sections,
                    // Flattened copy (with street names) so saving works the
                    // same way as every other AI report
                    'suggestions' => $flat,
                ],
            ];
        });
    }

    /** One per-street section: risk, profile summary, and rule-based suggestions */
    private function buildStreetSection(string $street, Collection $inc, string $midpoint): array
    {
        $total = $inc->count();

        if ($total === 0) {
            return [
                'street' => $street, 'risk_level' => 'low', 'total' => 0,
                'top_categories' => [], 'peak_hours' => [],
                'summary' => 'No crimes were recorded on ' . $street . ' in the selected period.',
                'suggestions' => [[
                    'action'      => 'Maintain routine patrol pass-through',
                    'time_window' => '18:00-23:00',
                    'rationale'   => 'No recorded crimes here; an occasional evening pass keeps it that way.',
                    'expected_impact' => [
                        'direction' => 'stable',
                        'estimated_change_percent' => 0,
                        'explanation' => 'Preventive presence sustains the current low-crime state.',
                    ],
                    'priority' => 'low',
                ]],
            ];
        }

        $cats = $inc->countBy('category')->sortDesc();
        $hours = $inc->whereNotNull('hour')->countBy('hour')->sortDesc();
        $peakHour = $hours->keys()->first();
        $peaks = $hours->take(2)->keys()->map(fn ($h) => sprintf('%02d:00', $h))->values()->all();
        $window = $peakHour !== null
            ? sprintf('%02d:00-%02d:00', $peakHour, ($peakHour + 3) % 24)
            : '18:00-23:00';
        $night = $peakHour !== null && ($peakHour >= 18 || $peakHour <= 4);

        $recent = $inc->filter(fn ($i) => $i['date'] >= $midpoint)->count();
        $rising = $recent > ($total - $recent);

        $risk = $total >= 8 ? 'high' : ($total >= 4 ? 'medium' : 'low');
        if ($rising && $risk !== 'high') {
            $risk = $risk === 'medium' ? 'high' : 'medium';
        }

        $topCat = $cats->keys()->first();
        $summary = $total . ' crime' . ($total === 1 ? '' : 's') . ' recorded, mostly ' . $topCat
            . ($peakHour !== null ? ', peaking around ' . sprintf('%02d:00', $peakHour) : '')
            . '. The count is ' . ($rising ? 'RISING' : 'steady or falling')
            . ' versus the earlier half of the period.';

        return [
            'street'         => $street,
            'risk_level'     => $risk,
            'total'          => $total,
            'top_categories' => $cats->take(3)->keys()->values()->all(),
            'peak_hours'     => $peaks,
            'summary'        => $summary,
            'suggestions'    => $this->interventionsForCategories($cats, $window, $night, $street, $risk),
        ];
    }

    /**
     * Category → intervention playbook (same fixed menu as pattern detection,
     * effect sizes from published crime-prevention research). Max 3 per street.
     */
    private function interventionsForCategories(Collection $cats, string $window, bool $night, string $street, string $risk): array
    {
        $picks = [];   // action => ['pct', 'explanation', 'because' => []]

        $add = function (string $action, int $pct, string $explanation, string $because) use (&$picks) {
            if (! isset($picks[$action])) {
                $picks[$action] = ['pct' => $pct, 'explanation' => $explanation, 'because' => []];
            }
            $picks[$action]['because'][] = $because;
        };

        foreach ($cats->take(3)->keys() as $cat) {
            $c = mb_strtolower((string) $cat);
            $n = $cats[$cat];
            $because = $n . ' ' . $cat . ' case' . ($n === 1 ? '' : 's');

            if (str_contains($c, 'vehicle') || str_contains($c, 'carnap')) {
                $add('Set up checkpoint', -13, 'Access control and checkpoints cut vehicle crime by ~13%.', $because);
                $add('Install CCTV', -13, 'Cameras on entry/exit points deter and trace vehicle theft.', $because);
            } elseif (str_contains($c, 'theft')) {
                $add('Deploy roving tanod patrol (ronda)', -20, 'Hot-spot patrols cut street crime by ~15-25%.', $because);
                $add('Install CCTV', -13, 'CCTV reduces area crime by ~13% and helps identify suspects.', $because);
            } elseif (str_contains($c, 'robbery') || str_contains($c, 'holdap')) {
                $add('Deploy roving tanod patrol (ronda)', -22, 'Visible patrols deter robbery during its peak hours.', $because);
                $add('Install streetlights', -20, 'Improved lighting cuts night street crime by ~20%.', $because);
            } elseif (str_contains($c, 'burglary') || str_contains($c, 'akyat')) {
                $add('Organize community watch', -16, 'Neighborhood watch reduces burglary by ~16%.', $because);
                $add('Run crime-awareness drive', -10, 'Target-hardening advice (locks, lighting, timers) lowers break-ins.', $because);
            } elseif (str_contains($c, 'assault') || str_contains($c, 'injur') || str_contains($c, 'homicide') || str_contains($c, 'murder')) {
                $add('Deploy roving tanod patrol (ronda)', -18, 'Patrol presence at peak hours interrupts violent confrontations.', $because);
                if ($night) {
                    $add('Install streetlights', -20, 'Improved lighting cuts night street crime by ~20%.', $because);
                }
            } elseif (str_contains($c, 'sexual') || str_contains($c, 'rape')) {
                $add('Install streetlights', -20, 'Lighting removes the dark segments where offenders strike.', $because);
                $add('Deploy roving tanod patrol (ronda)', -18, 'Patrol presence protects the peak-hour window.', $because);
            } elseif (str_contains($c, 'fraud') || str_contains($c, 'estafa') || str_contains($c, 'scam')) {
                $add('Run crime-awareness drive', -10, 'Scam-awareness campaigns reduce fraud victimization.', $because);
            } elseif (str_contains($c, 'domestic') || str_contains($c, 'vawc')) {
                $add('Run crime-awareness drive', -10, 'VAWC awareness plus barangay protection-order info reaches victims early.', $because);
                $add('Organize community watch', -16, 'Trained neighbors spot and report abuse earlier.', $because);
            } elseif (str_contains($c, 'drug')) {
                $add('Set up checkpoint', -13, 'Checkpoints disrupt the transport of illegal drugs.', $because);
                $add('Deploy roving tanod patrol (ronda)', -18, 'Patrols displace open drug activity.', $because);
            } else {
                $add('Deploy roving tanod patrol (ronda)', -15, 'Hot-spot patrols reduce most street crime types.', $because);
            }
        }

        if ($night) {
            $add('Install streetlights', -20, 'Improved lighting cuts night crime by ~20%.', 'crimes here peak at night');
        }

        $out = [];
        $i = 0;
        $prioForFirst = $risk === 'high' ? 'high' : ($risk === 'medium' ? 'medium' : 'low');
        foreach ($picks as $action => $p) {
            if ($i >= 3) {
                break;
            }
            $out[] = [
                'action'      => $action,
                'time_window' => $window,
                'rationale'   => 'Driven by ' . implode(', ', array_unique($p['because'])) . ' on ' . $street . '.',
                'expected_impact' => [
                    'direction' => 'decrease',
                    'estimated_change_percent' => $p['pct'],
                    'explanation' => $p['explanation'],
                ],
                'priority' => $i === 0 ? $prioForFirst : ($i === 1 ? 'medium' : 'low'),
            ];
            $i++;
        }

        return $out;
    }

    /**
     * Compact street prompt, built for MINIMUM token spend: top-N aggregates
     * only (no full 24h/monthly tables), single-line JSON, a fixed intervention
     * menu (same interventions as the pattern-detection page), and exactly 3
     * terse suggestions out. Works for one street or several together.
     */
    private function buildStreetPrompt(array $streets, Collection $incidents, int $days): string
    {
        // Trend signal without the full monthly table: first half vs second half
        $half = (int) floor($days / 2);
        $midpoint = now()->subDays($half)->toDateString();
        $recent = $incidents->filter(fn ($i) => $i['date'] >= $midpoint)->count();

        $aggregates = [
            'streets'     => implode(', ', $streets),
            'days'        => $days,
            'total'       => $incidents->count(),
            'top_crimes'  => $incidents->countBy('category')->sortDesc()->take(4)->all(),
            'peak_hours'  => $incidents->whereNotNull('hour')->countBy('hour')->sortDesc()->take(3)
                ->mapWithKeys(fn ($c, $h) => [sprintf('%02d:00', $h) => $c])->all(),
            'peak_days'   => $incidents->countBy('dow')->sortDesc()->take(2)->all(),
            'earlier_half'=> $incidents->count() - $recent,
            'recent_half' => $recent,
            'unresolved'  => $incidents->whereNotIn('status', ['solved', 'resolved', 'closed', 'cleared'])->count(),
        ];

        // With several streets selected, one compact per-street line lets the
        // AI aim each suggestion at the street that needs it most.
        if (count($streets) > 1) {
            $perStreet = [];
            foreach ($incidents->groupBy('street')->sortByDesc(fn ($g) => $g->count()) as $name => $group) {
                $perStreet[$name] = $group->count() . 'x, top: ' . $group->countBy('category')->sortDesc()->keys()->first();
            }
            $aggregates['per_street'] = $perStreet;
        }

        $data = json_encode($aggregates, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Crime-prevention adviser for Barangay San Agustin, Quezon City. Aggregated crime data for the selected street(s):
{$data}

Respond with ONLY this JSON (no markdown):
{"risk_level":"low|medium|high","summary":"<max 2 short sentences: dominant crimes, peak time, trend>","suggestions":[{"action":"<from the menu>","street":"<which selected street(s) it targets>","time_window":"<peak hours, e.g. 21:00-02:00>","rationale":"<1 short sentence>","expected_impact":{"direction":"decrease|stable","estimated_change_percent":<number>,"explanation":"<1 short sentence>"},"priority":"high|medium|low"}]}

Rules:
- Exactly 3 suggestions, each a DIFFERENT action from this menu ONLY: Install streetlights, Deploy roving tanod patrol (ronda), Install CCTV, Organize community watch, Set up checkpoint, Run crime-awareness drive.
- risk_level from total + trend (recent_half vs earlier_half). time_window from peak_hours. If per_street is given, target each suggestion at the street(s) that need it most.
- estimated_change_percent per research: lighting -20, CCTV -13, patrol -15 to -25, community watch -16, checkpoint -13.
- Say "crimes", never "incidents". Be terse.
PROMPT;
    }

    /** Filter the baseline to the scenario's crime types / target streets */
    private function applyScenarioFocus(Collection $incidents, array $scenario): Collection
    {
        $types = array_filter(array_map('strval', (array) ($scenario['crime_types'] ?? [])));
        if (! empty($types)) {
            $lower = array_map('mb_strtolower', $types);
            $filtered = $incidents->filter(fn ($i) => in_array(mb_strtolower((string) $i['category']), $lower, true));
            if ($filtered->isNotEmpty()) {
                $incidents = $filtered->values();
            }
        }

        if (($scenario['focus'] ?? '') === 'streets') {
            $streets = array_filter(array_map(fn ($s) => mb_strtolower(trim((string) $s)), (array) ($scenario['streets'] ?? [])));
            if (! empty($streets)) {
                $filtered = $incidents->filter(fn ($i) => $i['street'] && in_array(mb_strtolower($i['street']), $streets, true));
                if ($filtered->isNotEmpty()) {
                    $incidents = $filtered->values();
                }
            }
        }

        return $incidents;
    }

    /** Normalised scenario description echoed to the client and the prompt */
    private function scenarioForPrompt(array $scenario): array
    {
        return [
            'scenario_type'       => ($scenario['scenario_type'] ?? '') === 'prevention' ? 'prevention' : 'risk',
            'missing_safeguards'  => array_values(array_filter(array_map('strval', (array) ($scenario['missing_safeguards'] ?? [])))),
            'prevention_measures' => array_values(array_filter(array_map('strval', (array) ($scenario['prevention_measures'] ?? [])))),
            'crime_types'         => array_values(array_filter(array_map('strval', (array) ($scenario['crime_types'] ?? [])))),
            'focus'               => ($scenario['focus'] ?? '') === 'streets' ? 'streets' : 'barangay',
            'streets'             => array_values(array_filter(array_map('strval', (array) ($scenario['streets'] ?? [])))),
        ];
    }

    // ------------------------------------------------------------- data prep

    /**
     * Cheap fingerprint of the incident table (count + last write + max id).
     * Any data change — including migrations that relocate rows without
     * changing the row count — produces a new fingerprint, so caches keyed
     * by it can never serve stale street data.
     */
    private function tableFingerprint(): string
    {
        try {
            $row = SanAgustinIncident::query()
                ->selectRaw('COUNT(*) AS c, MAX(updated_at) AS u, MAX(id) AS m')
                ->first();

            return md5(($row->c ?? 0) . '|' . ($row->u ?? '') . '|' . ($row->m ?? 0));
        } catch (\Exception $e) {
            return 'nofp';
        }
    }

    private function loadIncidents(int $days): Collection
    {
        return SanAgustinIncident::query()
            ->where('incident_date', '>=', now()->subDays($days))
            ->get([
                'incident_date', 'incident_time', 'category_name', 'record_type',
                'address_details', 'status', 'clearance_status',
            ])
            ->map(function ($i) {
                $date = $i->incident_date instanceof Carbon
                    ? $i->incident_date
                    : Carbon::parse($i->incident_date);

                $hour = null;
                if ($i->incident_time && preg_match('/^(\d{1,2}):/', (string) $i->incident_time, $m)) {
                    $hour = (int) $m[1];
                }

                // "Susano Road, Barangay San Agustin, Quezon City" -> "Susano Road"
                $street = trim(explode(',', (string) $i->address_details)[0] ?? '');

                return [
                    'date'     => $date->toDateString(),
                    'month'    => $date->format('Y-m'),
                    'dow'      => $date->format('l'),
                    'hour'     => $hour,
                    'category' => $i->category_name ?: 'Uncategorized',
                    'street'   => $street !== '' ? $street : null,
                    'status'   => $i->status,
                ];
            })
            ->values();
    }

    /**
     * Compact aggregates only — a few hundred input tokens regardless of how
     * many records exist.
     */
    private function buildAggregates(Collection $incidents, int $days): array
    {
        $half = (int) floor($days / 2);
        $midpoint = now()->subDays($half)->toDateString();

        $recent = $incidents->filter(fn ($i) => $i['date'] >= $midpoint)->count();
        $earlier = $incidents->count() - $recent;

        $timeBuckets = ['00:00-05:59' => 0, '06:00-11:59' => 0, '12:00-17:59' => 0, '18:00-23:59' => 0, 'unknown' => 0];
        foreach ($incidents as $i) {
            if ($i['hour'] === null) {
                $timeBuckets['unknown']++;
            } elseif ($i['hour'] < 6) {
                $timeBuckets['00:00-05:59']++;
            } elseif ($i['hour'] < 12) {
                $timeBuckets['06:00-11:59']++;
            } elseif ($i['hour'] < 18) {
                $timeBuckets['12:00-17:59']++;
            } else {
                $timeBuckets['18:00-23:59']++;
            }
        }

        // Hour-by-hour distribution (00-23) — the strongest timing signal
        $hourly = array_fill(0, 24, 0);
        foreach ($incidents as $i) {
            if ($i['hour'] !== null) {
                $hourly[$i['hour']]++;
            }
        }
        $hourlyLabeled = [];
        foreach ($hourly as $h => $c) {
            $hourlyLabeled[sprintf('%02d:00', $h)] = $c;
        }

        // Peak hours per top category, so recommendations can cite exact times
        $categoryPeaks = [];
        foreach ($incidents->countBy('category')->sortDesc()->take(8)->keys() as $cat) {
            $hours = $incidents->where('category', $cat)->whereNotNull('hour')
                ->countBy('hour')->sortDesc()->take(3);
            $categoryPeaks[$cat] = [
                'count'      => $incidents->where('category', $cat)->count(),
                'peak_hours' => $hours->keys()->map(fn ($h) => sprintf('%02d:00', $h))->values()->all(),
            ];
        }

        // Street-level profile: count, dominant crime, peak hours per street
        $streetProfiles = [];
        foreach ($incidents->whereNotNull('street')->groupBy('street')
                     ->sortByDesc(fn ($g) => $g->count())->take(15) as $street => $group) {
            $peak = $group->whereNotNull('hour')->countBy('hour')->sortDesc()->take(2);
            $streetProfiles[$street] = [
                'count'      => $group->count(),
                'top_crime'  => $group->countBy('category')->sortDesc()->keys()->first(),
                'peak_hours' => $peak->keys()->map(fn ($h) => sprintf('%02d:00', $h))->values()->all(),
            ];
        }

        return [
            'barangay'            => 'San Agustin, Quezon City',
            'period_days'         => $days,
            'total_incidents'     => $incidents->count(),
            'first_half_count'    => $earlier,
            'second_half_count'   => $recent,
            'monthly_counts'      => $incidents->countBy('month')->sortKeys()->all(),
            'by_category'         => $incidents->countBy('category')->sortDesc()->take(12)->all(),
            'category_peak_hours' => $categoryPeaks,
            'street_profiles'     => $streetProfiles,
            'by_day_of_week'      => $incidents->countBy('dow')->all(),
            'by_time_of_day'      => $timeBuckets,
            'hourly_counts'       => $hourlyLabeled,
            'unresolved_count'    => $incidents->whereNotIn('status', ['solved', 'resolved', 'closed', 'cleared'])->count(),
        ];
    }

    // ------------------------------------------------------------ Gemini call

    private function callGemini(string $prompt, string $requiredKey = 'forecast', ?int $maxOutputTokens = null): array
    {
        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return ['error' => 'Gemini API key is not configured.'];
        }

        $model = config('services.gemini.model', 'gemini-flash-latest');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'      => 0.3,
                'maxOutputTokens'  => $maxOutputTokens ?? self::MAX_OUTPUT_TOKENS,
                'responseMimeType' => 'application/json',
                // Flash models spend "thinking" tokens by default; the LOW
                // level keeps each call cheap for the free-tier quota.
                'thinkingConfig'   => ['thinkingLevel' => 'LOW'],
            ],
        ];

        $response = $this->post($url, $apiKey, $payload);

        // Some model versions reject thinkingConfig — retry once without it.
        if ($response !== null && $response->status() === 400) {
            unset($payload['generationConfig']['thinkingConfig']);
            $response = $this->post($url, $apiKey, $payload);
        }

        if ($response === null) {
            return ['error' => 'Could not reach the Gemini API. Check the internet connection and try again.'];
        }

        if ($response->status() === 429) {
            return ['error' => 'Gemini API rate limit reached. Please wait a minute and try again — results are cached for ' . self::CACHE_HOURS . ' hours once generated.'];
        }

        if (!$response->successful()) {
            Log::error('Gemini pattern analysis failed', ['status' => $response->status(), 'body' => $response->body()]);
            return ['error' => 'Gemini API error (HTTP ' . $response->status() . ').'];
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');
        if (!$text) {
            Log::error('Gemini returned no candidates', ['body' => $response->body()]);
            return ['error' => 'Gemini returned an empty response. Try again.'];
        }

        $parsed = json_decode($text, true);
        if (!is_array($parsed) || !isset($parsed[$requiredKey])) {
            Log::error('Gemini returned unparseable JSON', ['text' => $text]);
            return ['error' => 'Gemini returned an unexpected format. Try running the analysis again.'];
        }

        return $parsed;
    }

    private function post(string $url, string $apiKey, array $payload)
    {
        try {
            return Http::withHeaders([
                    'Content-Type'   => 'application/json',
                    'X-goog-api-key' => $apiKey,
                ])
                ->timeout(60)
                ->post($url, $payload);
        } catch (\Exception $e) {
            Log::error('Gemini request exception: ' . $e->getMessage());
            return null;
        }
    }

    private function buildPrompt(array $aggregates): string
    {
        $data = json_encode($aggregates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
You are a crime analyst assisting the barangay peace and order committee of Barangay San Agustin, Quezon City, Philippines. Below are aggregated statistics of recorded crime incidents in the barangay.

INCIDENT DATA (aggregated):
{$data}

Analyze the data and respond with ONLY a valid JSON object in exactly this shape:

{
  "forecast": {
    "direction": "increase" | "decrease" | "stable",
    "expected_change_percent": <signed number, e.g. -15 means crime is expected to go DOWN 15%>,
    "confidence": "low" | "medium" | "high",
    "summary": "<2-3 sentence plain-English explanation of whether crime in San Agustin is expected to go up or down in the coming months, and why, citing the data>"
  },
  "key_findings": [
    "<short finding citing specific numbers from the data>",
    "... 3 to 5 findings total"
  ],
  "recommendations": [
    {
      "action": "<specific intervention, e.g. Install streetlights, Deploy roving patrol (roronda), Install CCTV, Community watch, Anti-fraud awareness drive>",
      "location": "<a specific street from street_profiles PLUS the exact time window it should cover, e.g. 'Susano Road, 21:00-02:00'>",
      "rationale": "<1-2 sentences: why this intervention fits this street's dominant crime and peak hours>",
      "expected_impact": {
        "direction": "decrease" | "increase" | "stable",
        "estimated_change_percent": <signed number: projected change in crime IF this is implemented, e.g. -20>,
        "explanation": "<1 sentence: what happens to crime if this is implemented, based on established crime-prevention evidence>"
      },
      "priority": "high" | "medium" | "low"
    },
    "... 6 to 8 recommendations total, covering different streets and different intervention types"
  ]
}

Rules:
- Base the forecast on the actual trend in monthly_counts and the first_half_count vs second_half_count comparison.
- Be precise about TIMING: use hourly_counts and category_peak_hours to name the exact hours each intervention should run (e.g. night patrols where robbery peaks 21:00-02:00, daytime theft watch 10:00-14:00, anti-burglary checks in early-morning hours).
- Ground every recommendation in a REAL street from street_profiles, matched to that street's top_crime and peak_hours. Cover at least 5 different streets across all recommendations.
- estimated_change_percent must be realistic and grounded in published crime-prevention research effect sizes (e.g. improved street lighting ≈ -20% area crime, CCTV ≈ -13%, hot-spot patrols ≈ -15 to -25%, community watch ≈ -16%).
- In every text field, refer to the records as "crimes", never "incidents".
- Keep every text field concise. Output JSON only, no markdown.
PROMPT;
    }

    /**
     * Scenario-driven prompt. The JSON output shape is identical to buildPrompt()
     * so the frontend renders both the same way — only the reasoning differs.
     */
    private function buildSimulationPrompt(array $aggregates, array $scenario): string
    {
        $data = json_encode($aggregates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $sc = $this->scenarioForPrompt($scenario);
        $area = $sc['focus'] === 'streets' && ! empty($sc['streets'])
            ? 'these streets: ' . implode(', ', $sc['streets'])
            : 'the whole barangay';
        $types = ! empty($sc['crime_types'])
            ? 'Focus specifically on these crime types: ' . implode(', ', $sc['crime_types']) . '.'
            : 'Consider all crime types present in the data.';

        // Shared JSON schema block (same shape as the real-data prompt)
        $schema = <<<SCHEMA
Respond with ONLY a valid JSON object in exactly this shape:

{
  "forecast": {
    "direction": "increase" | "decrease" | "stable",
    "expected_change_percent": <signed number relative to the baseline; POSITIVE = crime rises, NEGATIVE = crime falls>,
    "confidence": "low" | "medium" | "high",
    "summary": "<2-3 sentence plain-English explanation of the projected change under this scenario, citing the data and the research effect sizes>"
  },
  "key_findings": [
    "<short finding citing specific numbers from the data>",
    "... 3 to 5 findings total"
  ],
  "recommendations": [
    {
      "action": "<specific intervention>",
      "location": "<a specific street from street_profiles PLUS the exact time window, e.g. 'Susano Road, 21:00-02:00'>",
      "rationale": "<1-2 sentences tying the intervention to this street's dominant crime and peak hours>",
      "expected_impact": {
        "direction": "decrease" | "increase" | "stable",
        "estimated_change_percent": <signed number: projected change in crime IF this is applied>,
        "explanation": "<1 sentence grounded in crime-prevention evidence>"
      },
      "priority": "high" | "medium" | "low"
    },
    "... 5 to 8 recommendations total"
  ]
}
SCHEMA;

        if ($sc['scenario_type'] === 'prevention') {
            $measures = ! empty($sc['prevention_measures'])
                ? implode(', ', $sc['prevention_measures'])
                : 'general crime-prevention measures';

            return <<<PROMPT
You are a crime analyst for Barangay San Agustin, Quezon City, Philippines, running a WHAT-IF PREVENTION SIMULATION. Below is the aggregated baseline of recorded incidents for {$area}.

BASELINE DATA (aggregated):
{$data}

SCENARIO: Assume these crime-prevention measures are now DEPLOYED in {$area}: {$measures}.
{$types}

Using the baseline and published crime-prevention research effect sizes (improved street lighting ≈ -20% area crime, CCTV ≈ -13%, hot-spot patrols ≈ -15 to -25%, community watch ≈ -16%, checkpoints/access control ≈ -13%), project WHETHER CRIME WILL GO UP OR DOWN under this scenario and by how much. The analysis must center on the DIRECTION and magnitude of change: forecast.direction should reflect the net effect of the deployed measures (usually 'decrease'), and expected_change_percent should be the net percentage (typically negative). Recommendations should explain how to deploy, target, and sustain these measures for maximum effect on the streets and hours that need them most.

{$schema}

Rules:
- Anchor expected_change_percent to the combined research effect sizes of the deployed measures, applied to the crime categories each measure addresses.
- Be precise about TIMING and STREETS: use hourly_counts, category_peak_hours and street_profiles.
- In every text field, refer to the records as "crimes", never "incidents".
- Keep every text field concise. Output JSON only, no markdown.
PROMPT;
        }

        // Risk scenario (safeguards absent)
        $missing = ! empty($sc['missing_safeguards'])
            ? implode(', ', $sc['missing_safeguards'])
            : 'basic crime-prevention safeguards';

        return <<<PROMPT
You are a crime analyst for Barangay San Agustin, Quezon City, Philippines, running a WHAT-IF RISK SIMULATION. Below is the aggregated baseline of recorded incidents for {$area}.

BASELINE DATA (aggregated):
{$data}

SCENARIO: Assume the following crime-prevention safeguards are ABSENT in {$area}: {$missing}.
{$types}

Using the baseline and published crime-prevention research effect sizes (absence of street lighting ≈ +20% area crime, no CCTV ≈ +13%, no hot-spot patrols ≈ +15 to +25%, no community watch ≈ +16%, no checkpoints ≈ +13%), project HOW MUCH HIGHER crime is likely to be under this scenario. forecast.direction should be 'increase' and expected_change_percent should be POSITIVE, reflecting the combined uplift from the missing safeguards applied to the crime categories they normally protect. Then provide concrete recommendations and prevention measures the barangay should put in place to AVOID this rise, targeted to the streets and hours most at risk.

{$schema}

Rules:
- Anchor expected_change_percent to the combined research effect sizes of the MISSING safeguards, applied to the crime categories each safeguard normally protects.
- Be precise about TIMING and STREETS: use hourly_counts, category_peak_hours and street_profiles to name exact hours and streets.
- Every recommendation must be a prevention measure that directly counters one of the missing safeguards.
- In every text field, refer to the records as "crimes", never "incidents".
- Keep every text field concise. Output JSON only, no markdown.
PROMPT;
    }
}
