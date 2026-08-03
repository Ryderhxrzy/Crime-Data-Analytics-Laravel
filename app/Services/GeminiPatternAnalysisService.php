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
        $cacheKey = 'street_rules_v7_' . md5(implode('|', $sorted) . '|' . $days . '|' . $this->tableFingerprint());

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($streets, $days) {
            // ALL records, no date cutoff — the street modal lists every crime
            // on the street, so the suggestion counts must match it exactly.
            $all = $this->loadAllIncidents();
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

            $summary = 'Reviewed all ' . $totalAll . ' recorded crime' . ($totalAll === 1 ? '' : 's')
                . ' across ' . count($streets) . ' street' . (count($streets) === 1 ? '' : 's')
                . '. Suggestions are per street, matched to the crime categories most'
                . ' frequently committed there and their peak hours.';

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

    /**
     * SYSTEM-generated barangay-wide analysis for the pattern-detection page —
     * no AI call at all, mirroring the street modal's rule engine. Instant,
     * free, immune to Gemini limits.
     *
     * Output is shaped like analyze() (forecast + key_findings +
     * recommendations, so Save/Download keep working) PLUS analysis.streets[]:
     * one section per crime-carrying street, and under each street one block
     * per crime CATEGORY with its own counts, peak hours and a detailed
     * suggestion.
     */
    public function analyzeRuleBased(int $days = 180): array
    {
        $days = max(30, min(730, $days));

        $cacheKey = 'pattern_rules_v3_' . md5($days . '|' . $this->tableFingerprint());

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($days) {
            // ALL records — street sections must match the street modal lists
            $all = $this->loadAllIncidents();

            if ($all->isEmpty()) {
                return [
                    'success' => false,
                    'error'   => 'No San Agustin crime records found, so there is nothing to analyze.',
                ];
            }

            $periodStart = now()->subDays($days)->toDateString();
            $midpoint = now()->subDays((int) floor($days / 2))->toDateString();

            // Trend from the selected period only; street sections use all rows
            $period = $all->filter(fn ($i) => $i['date'] >= $periodStart)->values();
            $trendBase = $period->isNotEmpty() ? $period : $all;
            $recent = $trendBase->filter(fn ($i) => $i['date'] >= $midpoint)->count();
            $earlier = $trendBase->count() - $recent;

            $pct = $earlier > 0
                ? (int) round(($recent - $earlier) / $earlier * 100)
                : ($recent > 0 ? 100 : 0);
            $pct = max(-60, min(60, $pct));
            $direction = $pct >= 10 ? 'increase' : ($pct <= -10 ? 'decrease' : 'stable');
            $confidence = $trendBase->count() >= 150 ? 'high' : ($trendBase->count() >= 60 ? 'medium' : 'low');

            $forecast = [
                'direction'               => $direction,
                'expected_change_percent' => $pct,
                'confidence'              => $confidence,
                'summary'                 => 'Recorded crimes went from ' . $earlier . ' in the earlier half to '
                    . $recent . ' in the recent half of the last ' . $days . ' days ('
                    . ($pct > 0 ? '+' : '') . $pct . '%). If nothing changes, crime is likely to '
                    . ($direction === 'increase' ? 'keep rising' : ($direction === 'decrease' ? 'keep falling' : 'stay at about this level'))
                    . ' in the coming months. The street-by-street suggestions below target the exact'
                    . ' crime categories and hours behind these numbers.',
            ];

            // Per-street sections (per-category blocks inside), busiest first
            $sections = [];
            foreach ($all->whereNotNull('street')->groupBy('street')
                         ->sortByDesc(fn ($g) => $g->count())->take(15) as $street => $group) {
                $sections[] = $this->buildStreetSection((string) $street, $group->values(), $midpoint);
            }

            $findings = $this->buildKeyFindings($all, $recent, $earlier, $sections);

            // Flat copy — Save writes one row per recommendation, and the
            // download/report path reads this same list
            $flat = [];
            foreach ($sections as $sec) {
                foreach ($sec['suggestions'] as $s) {
                    $flat[] = $s + [
                        'street'   => $sec['street'],
                        'location' => $sec['street'] . ($s['time_window'] ? ', ' . $s['time_window'] : ''),
                    ];
                }
            }
            $prioRank = ['high' => 0, 'medium' => 1, 'low' => 2];
            usort($flat, fn ($a, $b) => ($prioRank[$a['priority']] ?? 3) <=> ($prioRank[$b['priority']] ?? 3));

            return [
                'success' => true,
                'meta' => [
                    'barangay'     => 'San Agustin',
                    'data_source'  => 'real',
                    'engine'       => 'rules',
                    'model'        => 'system-rules',
                    'period_days'  => $days,
                    'period_start' => $periodStart,
                    'period_end'   => now()->toDateString(),
                    'records_used' => $all->count(),
                    'generated_at' => now()->toIso8601String(),
                    'from_cache'   => false,
                ],
                'analysis' => [
                    'forecast'        => $forecast,
                    'key_findings'    => $findings,
                    'streets'         => $sections,
                    'recommendations' => array_slice($flat, 0, 24),
                ],
            ];
        });
    }

    /** Computed, number-citing findings for the rule-based barangay analysis */
    private function buildKeyFindings(Collection $all, int $recent, int $earlier, array $sections): array
    {
        $total = $all->count();
        $findings = [];

        $streetCount = $all->whereNotNull('street')->groupBy('street')->count();
        if (! empty($sections)) {
            $top = $sections[0];
            $findings[] = $total . ' crimes are recorded across ' . $streetCount . ' streets; the busiest is '
                . $top['street'] . ' with ' . $top['total'] . ' ('
                . (int) round($top['total'] / max(1, $total) * 100) . '% of all recorded crimes).';
        }

        $cats = $all->countBy('category')->sortDesc();
        $topCat = $cats->keys()->first();
        if ($topCat) {
            $catHours = $all->where('category', $topCat)->whereNotNull('hour')->countBy('hour')->sortDesc();
            $peak = $catHours->keys()->first();
            $findings[] = $topCat . ' is the most common crime (' . $cats->first() . ' of ' . $total . ')'
                . ($peak !== null ? ', typically happening around ' . $this->hour12((int) $peak) : '') . '.';
        }

        $hours = $all->whereNotNull('hour');
        if ($hours->isNotEmpty()) {
            $evening = $hours->filter(fn ($i) => $i['hour'] >= 18)->count();
            $peakAll = $hours->countBy('hour')->sortDesc()->keys()->first();
            $findings[] = (int) round($evening / max(1, $hours->count()) * 100)
                . '% of crimes happen between 6:00 PM and 11:59 PM'
                . ($peakAll !== null ? ', with the single busiest hour around ' . $this->hour12((int) $peakAll) : '') . '.';
        }

        $dows = $all->countBy('dow')->sortDesc();
        if ($dows->isNotEmpty()) {
            $findings[] = $dows->keys()->first() . ' is the busiest day, with ' . $dows->first()
                . ' recorded crimes — schedule extra patrol coverage accordingly.';
        }

        $unresolved = $all->whereNotIn('status', ['solved', 'resolved', 'closed', 'cleared'])->count();
        $findings[] = $unresolved . ' of ' . $total . ' crimes ('
            . (int) round($unresolved / max(1, $total) * 100) . '%) are still unresolved.';

        $findings[] = 'The recent half of the period logged ' . $recent . ' crimes versus ' . $earlier
            . ' in the earlier half — the trend is '
            . ($recent > $earlier ? 'RISING' : ($recent < $earlier ? 'FALLING' : 'FLAT')) . '.';

        return $findings;
    }

    /** 21 → "9:00 PM" — every user-facing hour is 12-hour format */
    private function hour12(int $h, int $m = 0): string
    {
        $ampm = $h >= 12 ? 'PM' : 'AM';
        $hh = $h % 12 ?: 12;

        return $hh . ':' . sprintf('%02d', $m) . ' ' . $ampm;
    }

    /** One per-street section: risk, profile summary, and rule-based suggestions */
    private function buildStreetSection(string $street, Collection $inc, string $midpoint): array
    {
        $total = $inc->count();

        if ($total === 0) {
            return [
                'street' => $street, 'risk_level' => 'low', 'total' => 0,
                'top_categories' => [], 'peak_hours' => [], 'categories' => [],
                'summary' => 'No crimes are recorded on ' . $street . ' — the street is cleared.',
                'suggestions' => [[
                    'action'      => 'Maintain routine patrol pass-through',
                    'time_window' => '6:00 PM - 11:00 PM',
                    'rationale'   => 'No recorded crimes here; an occasional evening pass keeps it that way.',
                    'details'     => [
                        'coverage'  => 'Light-touch coverage of ' . $street . ' during the evening.',
                        'steps'     => [
                            'Include this street in the regular ronda route once per shift.',
                            'Ask residents to report anything suspicious to the barangay hotline.',
                        ],
                        'resources' => self::ACTION_INFO['Maintain routine patrol pass-through']['resources'],
                        'lead'      => self::ACTION_INFO['Maintain routine patrol pass-through']['lead'],
                        'timeline'  => self::ACTION_INFO['Maintain routine patrol pass-through']['timeline'],
                        'tips'      => [
                            'Keep porch and gate lights on through the night.',
                            'Report anything suspicious to the barangay hotline right away.',
                        ],
                        'kpi'       => 'Target: keep this street at zero recorded crimes.',
                    ],
                    'expected_impact' => [
                        'direction' => 'stable',
                        'estimated_change_percent' => 0,
                        'explanation' => 'Preventive presence sustains the current cleared state.',
                    ],
                    'priority' => 'low',
                ]],
            ];
        }

        $cats = $inc->countBy('category')->sortDesc();
        $hours = $inc->whereNotNull('hour')->countBy('hour')->sortDesc();
        $peakHour = $hours->keys()->first();
        $peaks = $hours->take(2)->keys()->map(fn ($h) => $this->hour12((int) $h))->values()->all();

        $peakDay = $inc->countBy('dow')->sortDesc()->keys()->first();
        $recent = $inc->filter(fn ($i) => $i['date'] >= $midpoint)->count();
        $rising = $recent > ($total - $recent);

        $risk = $total >= 8 ? 'high' : ($total >= 4 ? 'medium' : 'low');
        if ($rising && $risk !== 'high') {
            $risk = $risk === 'medium' ? 'high' : 'medium';
        }

        $topCat = $cats->keys()->first();
        $summary = $total . ' crime' . ($total === 1 ? '' : 's') . ' recorded, mostly ' . $topCat
            . ' (' . $cats->first() . ' of ' . $total . ')'
            . ($peakHour !== null ? ', peaking around ' . $this->hour12((int) $peakHour) : '')
            . ($peakDay ? ' and busiest on ' . $peakDay . 's' : '')
            . '. The count is ' . ($rising ? 'RISING' : 'steady or falling')
            . ' versus the earlier half of the period.';

        // EVERY crime type on the street gets its own block + suggestion, so
        // the per-type counts always add up to the street total (the merged
        // top-3 rationale used to leave types unaccounted — user caught it).
        $categories = [];
        $suggestions = [];
        foreach ($cats as $cat => $n) {
            $catInc = $inc->filter(fn ($i) => $i['category'] === $cat);
            $catHours = $catInc->whereNotNull('hour')->countBy('hour')->sortDesc();
            $catPeak = $catHours->keys()->first();
            $catWindow = $catPeak !== null
                ? $this->hour12((int) $catPeak) . ' - ' . $this->hour12(((int) $catPeak + 3) % 24)
                : '6:00 PM - 11:00 PM';
            $catNight = $catPeak !== null && ($catPeak >= 18 || $catPeak <= 4);
            $share = (int) round($n / max(1, $total) * 100);

            // Evidence pulled from the ACTUAL recorded cases, so the barangay /
            // police can study what really happens here, how, and how bad it is
            $catUnresolved = $catInc->whereNotIn('status', ['solved', 'resolved', 'closed', 'cleared'])->count();
            $catDay = $catInc->countBy('dow')->sortDesc()->keys()->first();
            $latest = $catInc->max('date');
            $latestLabel = $latest ? Carbon::parse($latest)->format('M j, Y') : null;
            $modusCounts = $catInc->pluck('modus')
                ->filter(fn ($m) => is_string($m) && $m !== '')
                ->countBy()
                ->sortDesc();
            $modus = $modusCounts->take(3)
                ->map(fn ($c, $m) => $c . '× ' . $m)
                ->values()
                ->all();
            $modusNames = $modusCounts->take(3)->keys()->values()->all();

            // Every recorded case with its date, day and exact time — the raw
            // material the barangay / police study, and what the steps and
            // tips below are derived from
            $doneStatuses = ['solved', 'resolved', 'closed', 'cleared'];
            $casesList = $catInc->sortByDesc('date')->values()->map(fn ($i) => [
                'date'     => Carbon::parse($i['date'])->format('M j, Y'),
                'day'      => $i['dow'],
                'time'     => $i['time12'] ?? null,
                'modus'    => ($i['modus'] ?? '') !== '' ? $i['modus'] : null,
                'resolved' => in_array(mb_strtolower((string) $i['status']), $doneStatuses, true),
            ])->all();

            $severity = ($n >= 8 || ($n >= 5 && $share >= 40)) ? 'critical'
                : (($n >= 5 || $share >= 35) ? 'high'
                : (($n >= 3 || $share >= 20) ? 'moderate' : 'low'));

            $sugg = $this->interventionForCategory((string) $cat, (int) $n, $share, $catWindow, $catNight, $street, $peakDay, $total, $modusNames);
            $sugg['severity'] = $severity;
            if ($severity === 'critical') {
                $sugg['priority'] = 'high';
            }
            $sugg['details']['evidence'] = [
                'cases'       => (int) $n,
                'share'       => $share,
                'unresolved'  => $catUnresolved,
                'modus'       => $modus,
                'busiest_day' => $catDay,
                'latest'      => $latestLabel,
                'severity'    => $severity,
                'cases_list'  => $casesList,
            ];

            $categories[] = [
                'category'        => (string) $cat,
                'count'           => (int) $n,
                'share'           => $share,
                'severity'        => $severity,
                'unresolved'      => $catUnresolved,
                'modus_breakdown' => $modus,
                'busiest_day'     => $catDay,
                'latest_date'     => $latestLabel,
                'peak_hours'      => $catHours->take(2)->keys()->map(fn ($h) => $this->hour12((int) $h))->values()->all(),
                'suggestion'      => $sugg,
            ];
            $suggestions[] = $sugg + ['category' => (string) $cat];
        }

        return [
            'street'         => $street,
            'risk_level'     => $risk,
            'total'          => $total,
            'top_categories' => $cats->take(3)->keys()->values()->all(),
            'peak_hours'     => $peaks,
            'summary'        => $summary,
            'categories'     => $categories,
            'suggestions'    => $suggestions,
        ];
    }

    /** Concrete implementation steps per intervention (shown under each suggestion) */
    private const ACTION_STEPS = [
        'Deploy roving tanod patrol (ronda)' => [
            'Assign at least 2 tanods per shift to cover the peak window.',
            'Pass through every 30-45 minutes at slightly varying times so the pattern is unpredictable.',
            'Log every pass and observation in the patrol logbook for weekly review.',
        ],
        'Install CCTV' => [
            'Mount cameras at both ends of the street and at mid-block chokepoints.',
            'Angle one camera at each entry/exit so every vehicle and pedestrian is captured.',
            'Post CCTV warning signage and review footage after every reported case.',
        ],
        'Install streetlights' => [
            'Walk the street after 6:00 PM and list every dark segment, corner and alley mouth.',
            'Prioritize lamppost requests for those spots with the city engineering office.',
            'Replace busted bulbs within a week; assign a resident to report outages.',
        ],
        'Organize community watch' => [
            'Recruit 5-10 resident volunteers from this street.',
            'Set up a group chat with the tanod team for instant incident reports.',
            'Hold a short monthly coordination meeting at the barangay hall.',
        ],
        'Set up checkpoint' => [
            'Position the checkpoint at the street entry during the peak window.',
            'Rotate the schedule so it stays unpredictable.',
            'Coordinate with the local police station for joint spot checks.',
        ],
        'Run crime-awareness drive' => [
            'Distribute advisories to every household on this street.',
            'Announce reminders through the barangay PA system and social media page.',
            'Hold a short seminar at the barangay hall with a PNP resource speaker.',
        ],
        'Maintain routine patrol pass-through' => [
            'Include this street in the regular ronda route once per shift.',
            'Ask residents to report anything suspicious to the barangay hotline.',
        ],
    ];

    /** Who leads, what it needs, and how fast it can start — per intervention */
    private const ACTION_INFO = [
        'Deploy roving tanod patrol (ronda)' => [
            'resources' => '2-4 tanods per shift, patrol logbook, flashlights, and two-way radios or a patrol group chat.',
            'lead'      => 'Barangay Peace and Order Committee (tanod team).',
            'timeline'  => 'Can start within the week; review the logbook and crime counts after 30 days.',
        ],
        'Install CCTV' => [
            'resources' => '2-4 night-vision CCTV units, a DVR with at least 30 days of storage, and warning signage.',
            'lead'      => 'Barangay council, coordinated with the city CCTV program.',
            'timeline'  => 'Procure and mount within 4-6 weeks; set the footage-review protocol from day one.',
        ],
        'Install streetlights' => [
            'resources' => 'LED lampposts or replacement bulbs for every dark segment listed in the night walk-through.',
            'lead'      => 'Barangay council with the City Engineering Office.',
            'timeline'  => 'File the request within the week; bulb replacements within days, new lampposts within 1-2 months.',
        ],
        'Organize community watch' => [
            'resources' => '5-10 resident volunteers, ID lanyards, whistles or alarms, and a group chat linked to the tanod team.',
            'lead'      => 'Barangay captain with the homeowners/residents association.',
            'timeline'  => 'Recruit and orient within 2 weeks; first coordination meeting within the month.',
        ],
        'Set up checkpoint' => [
            'resources' => 'Checkpoint table and signage, early-warning devices, at least 2 tanods plus 1 police officer per shift.',
            'lead'      => 'Barangay tanod team in coordination with the local PNP station.',
            'timeline'  => 'Coordinate with the PNP this week; run on randomized nights thereafter.',
        ],
        'Run crime-awareness drive' => [
            'resources' => 'Printed advisories, the barangay PA system and social media page, and a PNP resource speaker.',
            'lead'      => 'Barangay information officer with the Peace and Order Committee.',
            'timeline'  => 'First advisory within the week; seminar within the month; repeat quarterly.',
        ],
        'Maintain routine patrol pass-through' => [
            'resources' => '1-2 tanods on the existing ronda route.',
            'lead'      => 'Barangay tanod team.',
            'timeline'  => 'Ongoing — fold this street into the existing patrol route.',
        ],
    ];

    /**
     * ONE tailored intervention per crime type (fixed menu shared with pattern
     * detection, research effect sizes), with steps, coverage and a target.
     */
    private function interventionForCategory(string $cat, int $n, int $share, string $window, bool $night, string $street, ?string $peakDay, int $total, array $modusNames = []): array
    {
        $c = mb_strtolower($cat);

        if (str_contains($c, 'vehicle') || str_contains($c, 'carnap')) {
            $action = 'Set up checkpoint';
            $pct = -13;
            $explanation = 'Access control and checkpoints cut vehicle crime by ~13% (research).';
            $tips = [
                'Use a steering, wheel or disc lock and an alarm on parked motorcycles and cars.',
                'Park in lighted, visible spots — avoid leaving vehicles on the roadside overnight.',
                'Ask the barangay to list your plate number in the vehicle watch list.',
            ];
        } elseif (str_contains($c, 'theft')) {
            $action = 'Deploy roving tanod patrol (ronda)';
            $pct = -20;
            $explanation = 'Hot-spot patrols cut street crime by ~15-25% (research).';
            $tips = [
                'Keep phones and bags away from the roadside edge; use crossbody bags with zippers.',
                'Avoid using your phone while walking near the road, especially at the peak hours above.',
                'Store owners: keep small valuables away from entrances and counters near the door.',
            ];
        } elseif (str_contains($c, 'robbery') || str_contains($c, 'holdap')) {
            $action = $night ? 'Install streetlights' : 'Deploy roving tanod patrol (ronda)';
            $pct = $night ? -20 : -22;
            $explanation = $night
                ? 'Improved lighting cuts night street crime by ~20% (research).'
                : 'Visible patrols deter robbery during its peak hours.';
            $tips = [
                'Avoid walking alone during the peak hold-up hours; take the lighted main routes.',
                'Do not display jewelry, phones or cash late at night.',
                'If held up, do not resist — note the suspects\' faces, clothing and any motorcycle plate, then report immediately.',
            ];
        } elseif (str_contains($c, 'burglary') || str_contains($c, 'akyat')) {
            $action = 'Organize community watch';
            $pct = -16;
            $explanation = 'Neighborhood watch reduces burglary by ~16% (research).';
            $tips = [
                'Double-lock doors and secure window grills before sleeping or leaving the house.',
                'Ask a trusted neighbor to watch the house and receive deliveries when you are away.',
                'Add lighting over entry points and trim shrubs that hide doors and windows.',
            ];
        } elseif (str_contains($c, 'assault') || str_contains($c, 'injur') || str_contains($c, 'homicide') || str_contains($c, 'murder')) {
            $action = 'Deploy roving tanod patrol (ronda)';
            $pct = -18;
            $explanation = 'Patrol presence at peak hours interrupts violent confrontations.';
            $tips = [
                'Report heated disputes to the barangay early — most injuries here start as arguments.',
                'Store and videoke owners: stop serving obviously intoxicated customers and close on time.',
                'Walk away from provocations; call the tanod hotline instead of confronting.',
            ];
        } elseif (str_contains($c, 'sexual') || str_contains($c, 'rape')) {
            $action = 'Install streetlights';
            $pct = -20;
            $explanation = 'Lighting removes the dark segments where offenders strike.';
            $tips = [
                'Travel in pairs or groups at night where possible; share your location with family.',
                'Report harassment at once — the barangay VAW desk handles complaints confidentially.',
                'Note and report dark or hidden spots along this street to the barangay.',
            ];
        } elseif (str_contains($c, 'fraud') || str_contains($c, 'estafa') || str_contains($c, 'scam')) {
            $action = 'Run crime-awareness drive';
            $pct = -10;
            $explanation = 'Scam-awareness campaigns reduce fraud victimization.';
            $tips = [
                'Verify sellers and "investment" offers before paying — meet at the barangay hall for big transactions.',
                'Never share OTPs, PINs or bank details, even with callers claiming to be your bank.',
                'If an offer sounds too good to be true, it is — ask the barangay desk before sending money.',
            ];
        } elseif (str_contains($c, 'domestic') || str_contains($c, 'vawc')) {
            $action = 'Run crime-awareness drive';
            $pct = -10;
            $explanation = 'VAWC awareness plus barangay protection-order info reaches victims early.';
            $tips = [
                'Victims can get a Barangay Protection Order the same day — the VAW desk assists for free.',
                'Neighbors: call the tanod hotline while a disturbance is ongoing, not after.',
                'Keep the VAW desk and PNP women\'s desk numbers saved on your phone.',
            ];
        } elseif (str_contains($c, 'drug')) {
            $action = 'Set up checkpoint';
            $pct = -13;
            $explanation = 'Checkpoints disrupt the transport of illegal drugs.';
            $tips = [
                'Report suspected drug activity anonymously through the barangay drop box or hotline.',
                'Parents: know where your children are during the peak hours above.',
            ];
        } else {
            $action = 'Deploy roving tanod patrol (ronda)';
            $pct = -15;
            $explanation = 'Hot-spot patrols reduce most street crime types.';
            $tips = [
                'Report anything suspicious to the barangay hotline right away.',
                'Keep porch and gate lights on through the night.',
            ];
        }

        $prevented = max(1, (int) round($n * abs($pct) / 100));
        $info = self::ACTION_INFO[$action] ?? [];

        return [
            'action'      => $action,
            'time_window' => $window,
            'rationale'   => $n . ' of the ' . $total . ' crimes here (' . $share . '%) are ' . $cat
                . ($window ? ', concentrated around ' . $window : '') . ' on ' . $street . '.',
            'details'     => [
                'coverage'  => 'Cover ' . $street . ' during ' . $window
                    . ($peakDay ? ', with extra attention on ' . $peakDay . 's (its busiest day)' : '') . '.',
                'steps'     => $this->stepsFor($action, $street, $window, $peakDay, $cat, $modusNames),
                'resources' => $info['resources'] ?? null,
                'lead'      => $info['lead'] ?? null,
                'timeline'  => $info['timeline'] ?? null,
                'tips'      => $this->tipsFor($cat, $modusNames, $window, $peakDay, $street, $tips),
                'kpi'       => 'Target: roughly ' . $prevented . ' fewer ' . $cat . ' case' . ($prevented === 1 ? '' : 's')
                    . ' on this street over the same period if sustained.',
            ],
            'expected_impact' => [
                'direction' => 'decrease',
                'estimated_change_percent' => $pct,
                'explanation' => $explanation,
            ],
            'priority' => $share >= 40 ? 'high' : ($share >= 20 ? 'medium' : 'low'),
        ];
    }

    /**
     * Modus keyword → concrete resident tip. Only tips whose keyword matches a
     * modus ACTUALLY RECORDED on the street/category make it into the output,
     * so every tip answers something that really happened there.
     */
    private const MODUS_TIP_RULES = [
        'snatch'            => 'Hold phones and bags on the building side, away from the road edge — snatchers here strike from the roadside.',
        'pickpocket'        => 'In crowds, keep wallets and phones in front pockets or zipped inner compartments — pickpocketing was recorded here.',
        'slash'             => 'Carry bags in front of the body along this stretch — bag-slashing cases were recorded here.',
        'shoplift'          => 'Store owners: keep small items visible from the counter and add mirrors at blind corners — shoplifting was recorded here.',
        'riding in tandem'  => 'Watch for motorcycles slowing beside pedestrians — riding-in-tandem robbery was recorded here; step away from the curb toward lit, busy spots.',
        'hold-up'           => 'If held up, do not resist — hand over valuables, then note faces, clothing and escape direction and call the barangay/PNP at once.',
        'knife'             => 'If threatened with a weapon, do not fight back — distance first, details later, report immediately.',
        'forced door'       => 'Reinforce door jambs and use deadbolts — recorded break-ins here forced the main door.',
        'window'            => 'Install window grills and locks — recorded break-ins here entered through windows.',
        'roof'              => 'Check and secure roof and ceiling access points — a recorded break-in here came through the roof.',
        'hotwiring'         => 'Use a steering lock or ignition kill-switch — motorcycles here were stolen by hotwiring.',
        'street parking'    => 'Avoid leaving motorcycles in open street parking overnight — that is exactly how vehicles were stolen here; use a locked or guarded area.',
        'carnap'            => 'Install a GPS tracker and alarm, and park only in lit, visible spots — carnapping was recorded here.',
        'online'            => 'Verify online sellers (profiles, reviews, cash-on-delivery only) — online-selling scams were recorded here.',
        'investment'        => 'Check any investment offer against SEC advisories before paying — investment scams were recorded here.',
        'budol'             => 'Ignore strangers offering instant winnings or too-good deals — budol-budol scams were recorded here.',
        'drunken'           => 'Walk away from heated arguments where drinking sessions run late — recorded injuries here started as drunken altercations.',
        'dispute'           => 'Bring neighbor disputes to the barangay Lupon early for mediation — recorded violence here escalated from disputes.',
        'intimate partner'  => 'Victims can request a Barangay Protection Order the same day at the VAW desk — it is free and confidential.',
        'lascivious'        => 'Report harassment immediately to the VAW desk, and walk in pairs along this stretch at night.',
        'harass'            => 'Report harassment immediately to the VAW desk, and walk in pairs along this stretch at night.',
        'stab'              => 'Report armed individuals or brewing confrontations straight to the PNP hotline — never intervene personally.',
        'gunshot'           => 'Report armed individuals or gunfire straight to the PNP hotline and stay indoors — never investigate personally.',
    ];

    /**
     * Implementation steps written around THIS street's recorded cases: the
     * exact hours, the busiest day, and the modus actually used — not a
     * generic checklist.
     */
    private function stepsFor(string $action, string $street, string $window, ?string $peakDay, string $cat, array $modusNames): array
    {
        $modusBrief = implode('; ', array_slice($modusNames, 0, 3));
        $dayClause = $peakDay ? ', with extra passes on ' . $peakDay . 's (when most cases here fall)' : '';

        switch ($action) {
            case 'Deploy roving tanod patrol (ronda)':
                return array_values(array_filter([
                    'Assign at least 2 tanods to pass through ' . $street . ' every 30-45 minutes during ' . $window . $dayClause . '.',
                    $modusBrief !== ''
                        ? 'Brief every shift on the modus recorded here — ' . $modusBrief . ' — so patrols know exactly what to watch for.'
                        : 'Vary pass times slightly so the pattern stays unpredictable.',
                    'Log every pass and observation, and review the logbook weekly against new ' . $cat . ' reports on this street.',
                ]));

            case 'Install streetlights':
                return array_values(array_filter([
                    'Walk ' . $street . ' during ' . $window . ' and list every dark segment, corner and alley mouth near where the recorded ' . $cat . ' cases happened.',
                    'File lamppost and bulb-replacement requests for those exact spots with the City Engineering Office; replace busted bulbs within the week.',
                    $modusBrief !== ''
                        ? 'Prioritize the spots the recorded modus (' . $modusBrief . ') depends on darkness to work, and assign a resident per block to report outages.'
                        : 'Assign a resident per block to report new outages to the barangay.',
                ]));

            case 'Install CCTV':
                return array_values(array_filter([
                    'Mount cameras covering both ends of ' . $street . ' and the mid-block spots where the recorded ' . $cat . ' cases cluster.',
                    'Angle entry/exit cameras to capture faces and plate numbers clearly during ' . $window . ' — the hours these cases happen.',
                    'Post CCTV signage, review footage after every new report, and turn over clips to the PNP handling the unresolved cases here.',
                ]));

            case 'Organize community watch':
                return array_values(array_filter([
                    'Recruit 5-10 volunteers living on ' . $street . ' and link them with the tanod team in one group chat.',
                    $modusBrief !== ''
                        ? 'Orient members on the modus recorded here — ' . $modusBrief . ' — and the ' . $window . ' window when cases happen.'
                        : 'Orient members on the ' . $window . ' window when cases happen here.',
                    'Schedule member look-outs around ' . $window . ($peakDay ? ' on ' . $peakDay . 's' : '') . ' and report sightings straight to the tanod chat.',
                ]));

            case 'Set up checkpoint':
                return array_values(array_filter([
                    'Position the checkpoint at the ' . $street . ' entry during ' . $window . ', when the recorded ' . $cat . ' cases happen.',
                    $modusBrief !== ''
                        ? 'Brief personnel to flag vehicles and behavior matching the recorded modus: ' . $modusBrief . '.'
                        : 'Rotate the schedule so it stays unpredictable.',
                    'Rotate nights unpredictably and coordinate joint spot checks with the local PNP station.',
                ]));

            case 'Run crime-awareness drive':
                return array_values(array_filter([
                    'Distribute advisories to every household and store on ' . $street . ' describing the actual recorded cases — '
                        . ($modusBrief !== '' ? $modusBrief : $cat) . ' — and the ' . $window . ' risk window.',
                    'Announce reminders through the barangay PA system and social media page' . ($peakDay ? ', timed before ' . $peakDay . 's when cases peak' : '') . '.',
                    'Hold a short seminar with a PNP resource speaker focused on ' . $cat . ' prevention and invite the residents of this street.',
                ]));

            default:
                return self::ACTION_STEPS[$action] ?? [];
        }
    }

    /**
     * Resident tips grounded in this street's cases: a timing tip built from
     * the real peak window/day, then modus-matched tips, topped up from the
     * category fallback pool.
     */
    private function tipsFor(string $cat, array $modusNames, string $window, ?string $peakDay, string $street, array $fallback): array
    {
        $tips = [
            'Be extra alert on ' . $street . ' around ' . $window
                . ($peakDay ? ', especially on ' . $peakDay . 's' : '')
                . ' — the recorded ' . $cat . ' cases here cluster in that window.',
        ];

        $modusLower = array_map('mb_strtolower', $modusNames);
        foreach (self::MODUS_TIP_RULES as $needle => $tip) {
            if (count($tips) >= 4) {
                break;
            }
            foreach ($modusLower as $m) {
                if (str_contains($m, $needle) && ! in_array($tip, $tips, true)) {
                    $tips[] = $tip;
                    break;
                }
            }
        }

        foreach ($fallback as $tip) {
            if (count($tips) >= 4) {
                break;
            }
            if (! in_array($tip, $tips, true)) {
                $tips[] = $tip;
            }
        }

        return $tips;
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

    /**
     * EVERY record with no date cutoff — used by the street rule engine so its
     * counts always equal what the street modal lists.
     */
    private function loadAllIncidents(): Collection
    {
        return $this->loadIncidents(0);
    }

    private function loadIncidents(int $days): Collection
    {
        return SanAgustinIncident::query()
            ->when($days > 0, fn ($q) => $q->where('incident_date', '>=', now()->subDays($days)))
            ->get([
                'incident_date', 'incident_time', 'category_name', 'record_type',
                'address_details', 'status', 'clearance_status', 'modus_operandi',
            ])
            ->map(function ($i) {
                $date = $i->incident_date instanceof Carbon
                    ? $i->incident_date
                    : Carbon::parse($i->incident_date);

                $hour = null;
                $time12 = null;
                if ($i->incident_time && preg_match('/^(\d{1,2}):(\d{2})/', (string) $i->incident_time, $m)) {
                    $hour = (int) $m[1];
                    $time12 = $this->hour12($hour, (int) $m[2]);
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
                    'modus'    => trim((string) $i->modus_operandi),
                    'time12'   => $time12,
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
