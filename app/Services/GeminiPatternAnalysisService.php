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
        $cacheKey = 'street_rules_v8_' . md5(implode('|', $sorted) . '|' . $days . '|' . $this->tableFingerprint());

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($streets, $days) {
            // ALL records, no date cutoff — the street modal lists every crime
            // on the street, so the suggestion counts must match it exactly.
            $all = $this->loadAllIncidents();
            $midpoint = now()->subDays((int) floor($days / 2))->toDateString();

            $riskRank = ['low' => 0, 'medium' => 1, 'high' => 2];
            $sections = [];
            $sectionsTl = [];
            $flat = [];
            $totalAll = 0;
            $worst = 'low';

            foreach ($streets as $street) {
                $inc = $all->filter(fn ($i) => $i['street'] !== null
                    && mb_strtolower($i['street']) === mb_strtolower($street))->values();

                $section = $this->buildStreetSection($street, $inc, $midpoint);
                $sections[] = $section;
                $sectionsTl[] = $this->buildStreetSection($street, $inc, $midpoint, 'tl');
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
            $summaryTl = 'Sinuri ang lahat ng ' . $totalAll . ' naitalang krimen sa '
                . count($streets) . ' kalye. Ang mga suhestiyon ay per kalye, batay sa mga'
                . ' kategorya ng krimen na pinaka-madalas doon at sa kanilang peak hours.';

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
                    'summary_tl' => $summaryTl,
                    'streets'    => $sections,
                    'streets_tl' => $sectionsTl,
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

        $cacheKey = 'pattern_rules_v4_' . md5($days . '|' . $this->tableFingerprint());

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
                'summary_tl'              => 'Mula ' . $earlier . ' krimen sa unang kalahati, naging ' . $recent
                    . ' sa huling kalahati ng nakaraang ' . $days . ' araw ('
                    . ($pct > 0 ? '+' : '') . $pct . '%). Kung walang gagawin, malamang na '
                    . ($direction === 'increase' ? 'tumaas pa' : ($direction === 'decrease' ? 'bumaba pa' : 'manatili sa ganitong lebel'))
                    . ' ang krimen sa mga susunod na buwan. Ang mga suhestiyon per kalye sa ibaba ay'
                    . ' tumutok sa eksaktong kategorya at oras sa likod ng mga numerong ito.',
            ];

            // Per-street sections (per-category blocks inside), busiest first
            $sections = [];
            $sectionsTl = [];
            foreach ($all->whereNotNull('street')->groupBy('street')
                         ->sortByDesc(fn ($g) => $g->count())->take(15) as $street => $group) {
                $sections[] = $this->buildStreetSection((string) $street, $group->values(), $midpoint);
                $sectionsTl[] = $this->buildStreetSection((string) $street, $group->values(), $midpoint, 'tl');
            }

            $findings = $this->buildKeyFindings($all, $recent, $earlier, $sections);
            $findingsTl = $this->buildKeyFindings($all, $recent, $earlier, $sections, 'tl');

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
                    'key_findings_tl' => $findingsTl,
                    'streets'         => $sections,
                    'streets_tl'      => $sectionsTl,
                    'recommendations' => array_slice($flat, 0, 24),
                ],
            ];
        });
    }

    /** Computed, number-citing findings for the rule-based barangay analysis */
    private function buildKeyFindings(Collection $all, int $recent, int $earlier, array $sections, string $lang = 'en'): array
    {
        $tl = $lang === 'tl';
        $total = $all->count();
        $findings = [];

        $streetCount = $all->whereNotNull('street')->groupBy('street')->count();
        if (! empty($sections)) {
            $top = $sections[0];
            $topPct = (int) round($top['total'] / max(1, $total) * 100);
            $findings[] = $tl
                ? $total . ' krimen ang naitala sa ' . $streetCount . ' kalye; pinaka-marami sa '
                    . $top['street'] . ' na may ' . $top['total'] . ' (' . $topPct . '% ng lahat ng naitalang krimen).'
                : $total . ' crimes are recorded across ' . $streetCount . ' streets; the busiest is '
                    . $top['street'] . ' with ' . $top['total'] . ' (' . $topPct . '% of all recorded crimes).';
        }

        $cats = $all->countBy('category')->sortDesc();
        $topCat = $cats->keys()->first();
        if ($topCat) {
            $catHours = $all->where('category', $topCat)->whereNotNull('hour')->countBy('hour')->sortDesc();
            $peak = $catHours->keys()->first();
            $findings[] = $tl
                ? $this->catLabel((string) $topCat, 'tl') . ' ang pinaka-madalas na krimen (' . $cats->first() . ' sa ' . $total . ')'
                    . ($peak !== null ? ', kadalasang nangyayari bandang ' . $this->hour12((int) $peak) : '') . '.'
                : $topCat . ' is the most common crime (' . $cats->first() . ' of ' . $total . ')'
                    . ($peak !== null ? ', typically happening around ' . $this->hour12((int) $peak) : '') . '.';
        }

        $hours = $all->whereNotNull('hour');
        if ($hours->isNotEmpty()) {
            $evening = $hours->filter(fn ($i) => $i['hour'] >= 18)->count();
            $peakAll = $hours->countBy('hour')->sortDesc()->keys()->first();
            $evPct = (int) round($evening / max(1, $hours->count()) * 100);
            $findings[] = $tl
                ? $evPct . '% ng mga krimen ay nangyayari sa pagitan ng 6:00 PM at 11:59 PM'
                    . ($peakAll !== null ? ', at ang pinaka-abalang oras ay bandang ' . $this->hour12((int) $peakAll) : '') . '.'
                : $evPct . '% of crimes happen between 6:00 PM and 11:59 PM'
                    . ($peakAll !== null ? ', with the single busiest hour around ' . $this->hour12((int) $peakAll) : '') . '.';
        }

        $dows = $all->countBy('dow')->sortDesc();
        if ($dows->isNotEmpty()) {
            $findings[] = $tl
                ? $this->dayLabel($dows->keys()->first(), 'tl') . ' ang pinaka-abalang araw na may ' . $dows->first()
                    . ' naitalang krimen — dagdagan ang patrol coverage sa araw na iyon.'
                : $dows->keys()->first() . ' is the busiest day, with ' . $dows->first()
                    . ' recorded crimes — schedule extra patrol coverage accordingly.';
        }

        $unresolved = $all->whereNotIn('status', ['solved', 'resolved', 'closed', 'cleared'])->count();
        $unPct = (int) round($unresolved / max(1, $total) * 100);
        $findings[] = $tl
            ? $unresolved . ' sa ' . $total . ' krimen (' . $unPct . '%) ang hindi pa naresolba.'
            : $unresolved . ' of ' . $total . ' crimes (' . $unPct . '%) are still unresolved.';

        $findings[] = $tl
            ? 'Ang huling kalahati ng panahon ay may ' . $recent . ' krimen laban sa ' . $earlier
                . ' sa unang kalahati — ang trend ay '
                . ($recent > $earlier ? 'TUMATAAS' : ($recent < $earlier ? 'BUMABABA' : 'FLAT')) . '.'
            : 'The recent half of the period logged ' . $recent . ' crimes versus ' . $earlier
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
    private function buildStreetSection(string $street, Collection $inc, string $midpoint, string $lang = 'en'): array
    {
        $total = $inc->count();
        $tl = $lang === 'tl';

        if ($total === 0) {
            $routineInfo = $tl ? self::ACTION_INFO_TL['Maintain routine patrol pass-through'] : self::ACTION_INFO['Maintain routine patrol pass-through'];

            return [
                'street' => $street, 'risk_level' => 'low', 'total' => 0,
                'top_categories' => [], 'peak_hours' => [], 'categories' => [],
                'summary' => $tl
                    ? 'Walang naitalang krimen sa ' . $street . ' — cleared ang kalyeng ito.'
                    : 'No crimes are recorded on ' . $street . ' — the street is cleared.',
                'suggestions' => [[
                    'action'      => $tl ? self::ACTION_TL['Maintain routine patrol pass-through'] : 'Maintain routine patrol pass-through',
                    'time_window' => '6:00 PM - 11:00 PM',
                    'rationale'   => $tl
                        ? 'Walang naitalang krimen dito; ang paminsan-minsang pagdaan sa gabi ang magpapanatili nito.'
                        : 'No recorded crimes here; an occasional evening pass keeps it that way.',
                    'details'     => [
                        'coverage'  => $tl
                            ? 'Magaan na bantay sa ' . $street . ' tuwing gabi.'
                            : 'Light-touch coverage of ' . $street . ' during the evening.',
                        'steps'     => $tl ? [
                            'Isama ang kalyeng ito sa regular na ruta ng ronda isang beses kada shift.',
                            'Hikayatin ang mga residente na i-report sa barangay hotline ang kahit anong kahina-hinala.',
                        ] : [
                            'Include this street in the regular ronda route once per shift.',
                            'Ask residents to report anything suspicious to the barangay hotline.',
                        ],
                        'resources' => $routineInfo['resources'],
                        'lead'      => $routineInfo['lead'],
                        'timeline'  => $routineInfo['timeline'],
                        'tips'      => $tl ? [
                            'Panatilihing nakabukas ang ilaw sa harap ng bahay at gate buong gabi.',
                            'I-report agad sa barangay hotline ang kahit anong kahina-hinala.',
                        ] : [
                            'Keep porch and gate lights on through the night.',
                            'Report anything suspicious to the barangay hotline right away.',
                        ],
                        'kpi'       => $tl
                            ? 'Target: panatilihin sa zero ang naitalang krimen sa kalyeng ito.'
                            : 'Target: keep this street at zero recorded crimes.',
                    ],
                    'expected_impact' => [
                        'direction' => 'stable',
                        'estimated_change_percent' => 0,
                        'explanation' => $tl
                            ? 'Pinapanatili ng preventive presence ang kasalukuyang malinis na estado ng kalye.'
                            : 'Preventive presence sustains the current cleared state.',
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
        $summary = $tl
            ? $total . ' krimen ang naitala, karamihan ay ' . $this->catLabel((string) $topCat, 'tl')
                . ' (' . $cats->first() . ' sa ' . $total . ')'
                . ($peakHour !== null ? ', pumipeak bandang ' . $this->hour12((int) $peakHour) : '')
                . ($peakDay ? ' at pinaka-madalas tuwing ' . $this->dayLabel($peakDay, 'tl') : '')
                . '. Ang bilang ay ' . ($rising ? 'TUMATAAS' : 'steady o bumababa')
                . ' kumpara sa unang kalahati ng panahon.'
            : $total . ' crime' . ($total === 1 ? '' : 's') . ' recorded, mostly ' . $topCat
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
                ->map(fn ($c, $m) => $c . '× ' . $this->modusLabel((string) $m, $lang))
                ->values()
                ->all();
            $modusNames = $modusCounts->take(3)->keys()->values()->all();

            // Every recorded case with its date, day and exact time — the raw
            // material the barangay / police study, and what the steps and
            // tips below are derived from
            $doneStatuses = ['solved', 'resolved', 'closed', 'cleared'];
            $casesList = $catInc->sortByDesc('date')->values()->map(fn ($i) => [
                'date'     => Carbon::parse($i['date'])->format('M j, Y'),
                'day'      => $this->dayLabel($i['dow'], $lang),
                'time'     => $i['time12'] ?? null,
                'modus'    => ($i['modus'] ?? '') !== '' ? $this->modusLabel($i['modus'], $lang) : null,
                'resolved' => in_array(mb_strtolower((string) $i['status']), $doneStatuses, true),
            ])->all();

            $severity = ($n >= 8 || ($n >= 5 && $share >= 40)) ? 'critical'
                : (($n >= 5 || $share >= 35) ? 'high'
                : (($n >= 3 || $share >= 20) ? 'moderate' : 'low'));

            $sugg = $this->interventionForCategory((string) $cat, (int) $n, $share, $catWindow, $catNight, $street, $peakDay, $total, $modusNames, $lang);
            $sugg['severity'] = $severity;
            if ($severity === 'critical') {
                $sugg['priority'] = 'high';
            }
            $sugg['details']['evidence'] = [
                'cases'       => (int) $n,
                'share'       => $share,
                'unresolved'  => $catUnresolved,
                'modus'       => $modus,
                'busiest_day' => $this->dayLabel($catDay, $lang),
                'latest'      => $latestLabel,
                'severity'    => $severity,
                'cases_list'  => $casesList,
            ];

            $categories[] = [
                'category'        => (string) $cat,
                'category_label'  => $this->catLabel((string) $cat, $lang),
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
        // busiest_day inside each category block should match the language too
        foreach ($categories as &$cbRef) {
            $cbRef['busiest_day'] = $this->dayLabel($cbRef['busiest_day'], $lang);
        }
        unset($cbRef);

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
     * $lang 'en' or 'tl' (Taglish) — every text field comes out in that
     * language; the underlying data and structure are identical.
     */
    private function interventionForCategory(string $cat, int $n, int $share, string $window, bool $night, string $street, ?string $peakDay, int $total, array $modusNames = [], string $lang = 'en'): array
    {
        $c = mb_strtolower($cat);
        $tl = $lang === 'tl';

        if (str_contains($c, 'vehicle') || str_contains($c, 'carnap')) {
            $action = 'Set up checkpoint';
            $pct = -13;
            $explanation = $tl
                ? 'Napapababa ng checkpoint at access control ang vehicle crime nang ~13% (research).'
                : 'Access control and checkpoints cut vehicle crime by ~13% (research).';
            $tips = $tl ? [
                'Gumamit ng steering, wheel o disc lock at alarma sa mga nakaparadang motorsiklo at sasakyan.',
                'Sa maliwanag at kitang-kita na lugar magparada — huwag iwanang magdamag ang sasakyan sa tabing-kalsada.',
                'Ipalista sa barangay ang plaka ng sasakyan ninyo sa vehicle watch list.',
            ] : [
                'Use a steering, wheel or disc lock and an alarm on parked motorcycles and cars.',
                'Park in lighted, visible spots — avoid leaving vehicles on the roadside overnight.',
                'Ask the barangay to list your plate number in the vehicle watch list.',
            ];
        } elseif (str_contains($c, 'theft')) {
            $action = 'Deploy roving tanod patrol (ronda)';
            $pct = -20;
            $explanation = $tl
                ? 'Napapababa ng hot-spot patrol ang street crime nang ~15-25% (research).'
                : 'Hot-spot patrols cut street crime by ~15-25% (research).';
            $tips = $tl ? [
                'Ilayo ang phone at bag sa gilid ng kalsada; gumamit ng crossbody bag na may zipper.',
                'Iwasang gumamit ng phone habang naglalakad malapit sa kalsada, lalo na sa peak hours sa itaas.',
                'Mga may-ari ng tindahan: ilayo ang maliliit na valuables sa entrada at sa counter malapit sa pinto.',
            ] : [
                'Keep phones and bags away from the roadside edge; use crossbody bags with zippers.',
                'Avoid using your phone while walking near the road, especially at the peak hours above.',
                'Store owners: keep small valuables away from entrances and counters near the door.',
            ];
        } elseif (str_contains($c, 'robbery') || str_contains($c, 'holdap')) {
            $action = $night ? 'Install streetlights' : 'Deploy roving tanod patrol (ronda)';
            $pct = $night ? -20 : -22;
            $explanation = $tl
                ? ($night
                    ? 'Napapababa ng mas maayos na ilaw ang night street crime nang ~20% (research).'
                    : 'Naipipigil ng kitang-kitang patrolya ang holdap sa peak hours nito.')
                : ($night
                    ? 'Improved lighting cuts night street crime by ~20% (research).'
                    : 'Visible patrols deter robbery during its peak hours.');
            $tips = $tl ? [
                'Iwasang maglakad nang mag-isa sa mga oras na madalas ang holdap; sa maliwanag na pangunahing ruta dumaan.',
                'Huwag magsuot ng alahas o maglabas ng phone at pera sa gabi.',
                'Kapag hinoldap, huwag lumaban — tandaan ang mukha, damit at plaka ng motorsiklo, saka agad mag-report.',
            ] : [
                'Avoid walking alone during the peak hold-up hours; take the lighted main routes.',
                'Do not display jewelry, phones or cash late at night.',
                'If held up, do not resist — note the suspects\' faces, clothing and any motorcycle plate, then report immediately.',
            ];
        } elseif (str_contains($c, 'burglary') || str_contains($c, 'akyat')) {
            $action = 'Organize community watch';
            $pct = -16;
            $explanation = $tl
                ? 'Napapababa ng neighborhood watch ang akyat-bahay nang ~16% (research).'
                : 'Neighborhood watch reduces burglary by ~16% (research).';
            $tips = $tl ? [
                'I-doble-kandado ang pinto at siguruhin ang rehas ng bintana bago matulog o umalis ng bahay.',
                'Magpabantay sa pinagkakatiwalaang kapitbahay at ipa-receive ang deliveries kapag wala kayo.',
                'Lagyan ng ilaw ang mga pasukan at putulan ang mga halamang tumatakip sa pinto at bintana.',
            ] : [
                'Double-lock doors and secure window grills before sleeping or leaving the house.',
                'Ask a trusted neighbor to watch the house and receive deliveries when you are away.',
                'Add lighting over entry points and trim shrubs that hide doors and windows.',
            ];
        } elseif (str_contains($c, 'assault') || str_contains($c, 'injur') || str_contains($c, 'homicide') || str_contains($c, 'murder')) {
            $action = 'Deploy roving tanod patrol (ronda)';
            $pct = -18;
            $explanation = $tl
                ? 'Naaabala ng presensya ng patrol sa peak hours ang mga marahas na engkwentro.'
                : 'Patrol presence at peak hours interrupts violent confrontations.';
            $tips = $tl ? [
                'Idulog agad sa barangay ang mga mainit na alitan — karamihan ng pananakit dito ay nagsimula sa away.',
                'Mga may-ari ng tindahan at videoke: huwag nang pagbilhan ang halatang lasing at magsara sa tamang oras.',
                'Umiwas sa mga nang-aasar; tumawag sa tanod hotline imbes na makipagharap.',
            ] : [
                'Report heated disputes to the barangay early — most injuries here start as arguments.',
                'Store and videoke owners: stop serving obviously intoxicated customers and close on time.',
                'Walk away from provocations; call the tanod hotline instead of confronting.',
            ];
        } elseif (str_contains($c, 'sexual') || str_contains($c, 'rape')) {
            $action = 'Install streetlights';
            $pct = -20;
            $explanation = $tl
                ? 'Inaalis ng ilaw ang madidilim na bahaging pinagtataguan ng mga salarin.'
                : 'Lighting removes the dark segments where offenders strike.';
            $tips = $tl ? [
                'Magpares-pares o mag-grupo kung maglalakad sa gabi; i-share ang location sa pamilya.',
                'I-report agad ang panghaharas — kumpidensyal ang barangay VAW desk sa mga reklamo.',
                'Itala at i-report sa barangay ang madidilim o tagong bahagi ng kalyeng ito.',
            ] : [
                'Travel in pairs or groups at night where possible; share your location with family.',
                'Report harassment at once — the barangay VAW desk handles complaints confidentially.',
                'Note and report dark or hidden spots along this street to the barangay.',
            ];
        } elseif (str_contains($c, 'fraud') || str_contains($c, 'estafa') || str_contains($c, 'scam')) {
            $action = 'Run crime-awareness drive';
            $pct = -10;
            $explanation = $tl
                ? 'Napapababa ng scam-awareness campaign ang bilang ng nabibiktima ng panloloko.'
                : 'Scam-awareness campaigns reduce fraud victimization.';
            $tips = $tl ? [
                'I-verify muna ang seller at mga "investment" offer bago magbayad — sa barangay hall makipagkita para sa malalaking transaksyon.',
                'Huwag ibigay kailanman ang OTP, PIN o bank details, kahit sa tumatawag na nagpapakilalang taga-bangko.',
                'Kapag sobrang ganda ng alok para maging totoo, hindi iyon totoo — magtanong muna sa barangay desk bago magpadala ng pera.',
            ] : [
                'Verify sellers and "investment" offers before paying — meet at the barangay hall for big transactions.',
                'Never share OTPs, PINs or bank details, even with callers claiming to be your bank.',
                'If an offer sounds too good to be true, it is — ask the barangay desk before sending money.',
            ];
        } elseif (str_contains($c, 'domestic') || str_contains($c, 'vawc')) {
            $action = 'Run crime-awareness drive';
            $pct = -10;
            $explanation = $tl
                ? 'Naaabot nang maaga ang mga biktima sa pamamagitan ng VAWC awareness at protection-order info.'
                : 'VAWC awareness plus barangay protection-order info reaches victims early.';
            $tips = $tl ? [
                'Pwedeng makakuha ang biktima ng Barangay Protection Order sa mismong araw — libre ang tulong ng VAW desk.',
                'Mga kapitbahay: tumawag sa tanod hotline habang nangyayari ang gulo, hindi pagkatapos.',
                'I-save sa phone ang numero ng VAW desk at PNP women\'s desk.',
            ] : [
                'Victims can get a Barangay Protection Order the same day — the VAW desk assists for free.',
                'Neighbors: call the tanod hotline while a disturbance is ongoing, not after.',
                'Keep the VAW desk and PNP women\'s desk numbers saved on your phone.',
            ];
        } elseif (str_contains($c, 'drug')) {
            $action = 'Set up checkpoint';
            $pct = -13;
            $explanation = $tl
                ? 'Naaabala ng checkpoint ang transport ng iligal na droga.'
                : 'Checkpoints disrupt the transport of illegal drugs.';
            $tips = $tl ? [
                'I-report nang anonymous ang pinaghihinalaang drug activity sa barangay drop box o hotline.',
                'Mga magulang: alamin kung nasaan ang mga anak tuwing peak hours sa itaas.',
            ] : [
                'Report suspected drug activity anonymously through the barangay drop box or hotline.',
                'Parents: know where your children are during the peak hours above.',
            ];
        } else {
            $action = 'Deploy roving tanod patrol (ronda)';
            $pct = -15;
            $explanation = $tl
                ? 'Napapababa ng hot-spot patrol ang karamihan ng street crime.'
                : 'Hot-spot patrols reduce most street crime types.';
            $tips = $tl ? [
                'I-report agad sa barangay hotline ang kahit anong kahina-hinala.',
                'Panatilihing nakabukas ang ilaw sa harap ng bahay at gate buong gabi.',
            ] : [
                'Report anything suspicious to the barangay hotline right away.',
                'Keep porch and gate lights on through the night.',
            ];
        }

        $prevented = max(1, (int) round($n * abs($pct) / 100));
        $info = ($tl ? self::ACTION_INFO_TL[$action] ?? null : null) ?? self::ACTION_INFO[$action] ?? [];
        $catLabel = $tl ? (self::CATEGORY_TL[$cat] ?? $cat) : $cat;
        $dayLabel = $peakDay ? ($tl ? (self::DAY_TL[$peakDay] ?? $peakDay) : $peakDay) : null;

        return [
            'action'      => $tl ? (self::ACTION_TL[$action] ?? $action) : $action,
            'time_window' => $window,
            'rationale'   => $tl
                ? $n . ' sa ' . $total . ' krimen dito (' . $share . '%) ay ' . $catLabel
                    . ($window ? ', pumipeak bandang ' . $window : '') . ' sa ' . $street . '.'
                : $n . ' of the ' . $total . ' crimes here (' . $share . '%) are ' . $cat
                    . ($window ? ', concentrated around ' . $window : '') . ' on ' . $street . '.',
            'details'     => [
                'coverage'  => $tl
                    ? 'Bantayan ang ' . $street . ' tuwing ' . $window
                        . ($dayLabel ? ', lalo na tuwing ' . $dayLabel . ' (pinaka-madalas na araw)' : '') . '.'
                    : 'Cover ' . $street . ' during ' . $window
                        . ($peakDay ? ', with extra attention on ' . $peakDay . 's (its busiest day)' : '') . '.',
                'steps'     => $this->stepsFor($action, $street, $window, $peakDay, $cat, $modusNames, $lang),
                'resources' => $info['resources'] ?? null,
                'lead'      => $info['lead'] ?? null,
                'timeline'  => $info['timeline'] ?? null,
                'tips'      => $this->tipsFor($cat, $modusNames, $window, $peakDay, $street, $tips, $lang),
                'kpi'       => $tl
                    ? 'Target: humigit-kumulang ' . $prevented . ' na mas kaunting ' . $catLabel . ' case'
                        . ' sa kalyeng ito sa parehong panahon kung tuloy-tuloy.'
                    : 'Target: roughly ' . $prevented . ' fewer ' . $cat . ' case' . ($prevented === 1 ? '' : 's')
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

    /** Taglish twin of MODUS_TIP_RULES — same keys, same triggers */
    private const MODUS_TIP_RULES_TL = [
        'snatch'            => 'Hawakan ang phone at bag sa gawing gusali, malayo sa gilid ng kalsada — sa tabing-daan nang-aagaw ang mga snatcher dito.',
        'pickpocket'        => 'Sa matataong lugar, ilagay ang wallet at phone sa harap na bulsa o naka-zip na bag — may naitalang pandudukot dito.',
        'slash'             => 'Ibitbit ang bag sa harap ng katawan sa bahaging ito — may naitalang paglaslas ng bag dito.',
        'shoplift'          => 'Mga may-ari ng tindahan: ilagay ang maliliit na paninda kung saan kita mula sa counter at magdagdag ng salamin sa mga blind corner — may naitalang shoplifting dito.',
        'riding in tandem'  => 'Mag-ingat sa mga motorsiklong bumabagal sa tabi ng naglalakad — may naitalang riding-in-tandem dito; lumayo sa gilid ng kalsada papunta sa maliwanag at mataong lugar.',
        'hold-up'           => 'Kapag hinoldap, huwag lumaban — ibigay ang gamit, tandaan ang mukha, damit at direksyon ng pagtakas, saka agad tumawag sa barangay/PNP.',
        'knife'             => 'Kapag tinutukan ng patalim, huwag makipagbuno — lumayo muna, itala ang detalye pagkatapos, at agad mag-report.',
        'forced door'       => 'Palakasin ang hamba ng pinto at gumamit ng deadbolt — pilit na binuksan ang pinto sa mga naitalang akyat-bahay dito.',
        'window'            => 'Maglagay ng rehas at kandado sa bintana — sa bintana pumasok ang mga naitalang akyat-bahay dito.',
        'roof'              => 'Suriin at siguruhin ang bubong at kisame — may naitalang pagpasok sa bubong dito.',
        'hotwiring'         => 'Gumamit ng steering lock o ignition kill-switch — hinotwire ang mga motorsiklong ninakaw dito.',
        'street parking'    => 'Huwag iwanang magdamag ang motorsiklo sa open street parking — ganyan mismo ninakaw ang mga sasakyan dito; sa naka-kandado o may bantay na lugar magparada.',
        'carnap'            => 'Maglagay ng GPS tracker at alarma, at sa maliwanag at kitang-kita na lugar lang magparada — may naitalang carnapping dito.',
        'online'            => 'I-verify ang online seller (profile, reviews, cash-on-delivery lang) — may naitalang online-selling scam dito.',
        'investment'        => 'I-check muna sa SEC advisories ang kahit anong investment offer bago magbayad — may naitalang investment scam dito.',
        'budol'             => 'Huwag pansinin ang mga estranghero na nag-aalok ng instant na panalo o sobrang gandang deal — may naitalang budol-budol dito.',
        'drunken'           => 'Umiwas sa mainit na pagtatalo lalo na kung may inuman — nagsimula sa away ng lasing ang mga naitalang pananakit dito.',
        'dispute'           => 'Idulog agad sa Lupon ng barangay ang away-kapitbahay para sa mediation — umeskalada mula sa alitan ang naitalang karahasan dito.',
        'intimate partner'  => 'Pwedeng kumuha ang biktima ng Barangay Protection Order sa mismong araw sa VAW desk — libre at kumpidensyal.',
        'lascivious'        => 'I-report agad ang panghaharas sa VAW desk, at magpares-pares kung maglalakad dito sa gabi.',
        'harass'            => 'I-report agad ang panghaharas sa VAW desk, at magpares-pares kung maglalakad dito sa gabi.',
        'stab'              => 'I-report agad sa PNP hotline ang mga armadong tao o papalalang gulo — huwag makialam nang mag-isa.',
        'gunshot'           => 'I-report agad sa PNP hotline ang putok ng baril at manatili sa loob ng bahay — huwag mag-imbestiga nang mag-isa.',
    ];

    /** Taglish labels for the fixed intervention menu (display only — the EN
     * name stays the canonical value in flat/saved recommendations) */
    private const ACTION_TL = [
        'Deploy roving tanod patrol (ronda)'  => 'Mag-ronda ang mga tanod (roving patrol)',
        'Install CCTV'                        => 'Maglagay ng CCTV',
        'Install streetlights'                => 'Maglagay ng ilaw sa kalye (streetlights)',
        'Organize community watch'            => 'Mag-organisa ng community watch',
        'Set up checkpoint'                   => 'Maglagay ng checkpoint',
        'Run crime-awareness drive'           => 'Magsagawa ng crime-awareness drive',
        'Maintain routine patrol pass-through' => 'Ipagpatuloy ang regular na ronda',
    ];

    /** Taglish twin of ACTION_INFO */
    private const ACTION_INFO_TL = [
        'Deploy roving tanod patrol (ronda)' => [
            'resources' => '2-4 tanod kada shift, patrol logbook, flashlight, at two-way radio o group chat ng patrol.',
            'lead'      => 'Barangay Peace and Order Committee (tanod team).',
            'timeline'  => 'Pwedeng simulan ngayong linggo; i-review ang logbook at bilang ng krimen pagkatapos ng 30 araw.',
        ],
        'Install CCTV' => [
            'resources' => '2-4 CCTV na may night vision, DVR na may 30-araw na storage, at warning signage.',
            'lead'      => 'Barangay council, katuwang ang city CCTV program.',
            'timeline'  => 'Bili at kabit sa loob ng 4-6 na linggo; ayusin ang footage-review protocol mula unang araw.',
        ],
        'Install streetlights' => [
            'resources' => 'LED lamppost o kapalit na bombilya para sa bawat madilim na bahaging nakita sa night walk-through.',
            'lead'      => 'Barangay council kasama ang City Engineering Office.',
            'timeline'  => 'I-file ang request ngayong linggo; palit-bombilya sa loob ng ilang araw, bagong poste sa loob ng 1-2 buwan.',
        ],
        'Organize community watch' => [
            'resources' => '5-10 residenteng volunteer, ID lanyard, pito o alarma, at group chat na konektado sa tanod team.',
            'lead'      => 'Kapitan ng barangay kasama ang homeowners/residents association.',
            'timeline'  => 'Mag-recruit at mag-orient sa loob ng 2 linggo; unang coordination meeting sa loob ng buwan.',
        ],
        'Set up checkpoint' => [
            'resources' => 'Mesa at signage ng checkpoint, early-warning device, at least 2 tanod at 1 pulis kada shift.',
            'lead'      => 'Tanod team ng barangay, katuwang ang lokal na PNP station.',
            'timeline'  => 'Makipag-ugnayan sa PNP ngayong linggo; patakbuhin sa random na mga gabi pagkatapos.',
        ],
        'Run crime-awareness drive' => [
            'resources' => 'Printed na advisory, PA system at social media page ng barangay, at PNP resource speaker.',
            'lead'      => 'Barangay information officer kasama ang Peace and Order Committee.',
            'timeline'  => 'Unang advisory ngayong linggo; seminar sa loob ng buwan; ulitin kada quarter.',
        ],
        'Maintain routine patrol pass-through' => [
            'resources' => '1-2 tanod sa kasalukuyang ruta ng ronda.',
            'lead'      => 'Tanod team ng barangay.',
            'timeline'  => 'Tuloy-tuloy — isama ang kalyeng ito sa kasalukuyang ruta ng ronda.',
        ],
    ];

    /** Display labels for crime categories (raw DB value stays canonical) */
    private const CATEGORY_TL = [
        'Theft'             => 'Pagnanakaw',
        'Robbery'           => 'Holdap',
        'Assault'           => 'Pananakit',
        'Burglary'          => 'Akyat-Bahay',
        'Vehicle Theft'     => 'Carnapping / Nakaw na Sasakyan',
        'Domestic Violence' => 'Karahasan sa Tahanan',
        'Fraud'             => 'Panloloko / Scam',
        'Sexual Offense'    => 'Seksuwal na Pang-aabuso',
        'Homicide'          => 'Pagpatay',
    ];

    private const DAY_TL = [
        'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miyerkules',
        'Thursday' => 'Huwebes', 'Friday' => 'Biyernes', 'Saturday' => 'Sabado', 'Sunday' => 'Linggo',
    ];

    /** Known generated modus strings → Taglish (unknown text passes through) */
    private const MODUS_TL = [
        'Pickpocketing in a crowded area'              => 'Pandudukot sa mataong lugar',
        'Snatching from pedestrian'                    => 'Pag-agaw (snatching) sa naglalakad',
        'Shoplifting from a store'                     => 'Pagnanakaw sa tindahan (shoplifting)',
        'Bag slashing'                                 => 'Paglaslas ng bag',
        'Armed hold-up of pedestrian'                  => 'Armadong holdap sa naglalakad',
        'Motorcycle-riding suspects (riding in tandem)' => 'Riding-in-tandem na mga suspek',
        'Knife-point robbery'                          => 'Holdap sa tulis ng kutsilyo',
        'Store hold-up'                                => 'Holdap sa tindahan',
        'Drunken altercation escalated'                => 'Away ng lasing na umeskalada',
        'Neighborhood dispute turned physical'         => 'Away-kapitbahay na nauwi sa suntukan',
        'Street brawl'                                 => 'Bugbugan sa kalye',
        'Forced door entry while occupants away'       => 'Pilit na pagbukas ng pinto habang walang tao',
        'Window entry at unoccupied house'             => 'Pagpasok sa bintana ng bakanteng bahay',
        'Roof/ceiling entry'                           => 'Pagpasok sa bubong/kisame',
        'Motorcycle stolen from street parking'        => 'Motorsiklong ninakaw sa street parking',
        'Carnapping of parked vehicle'                 => 'Carnapping ng nakaparadang sasakyan',
        'Broken ignition / hotwiring'                  => 'Sinirang ignition / hotwiring',
        'Domestic dispute'                             => 'Away sa loob ng tahanan',
        'Intimate partner violence'                    => 'Karahasan mula sa kinakasama',
        'Online sale scam / bogus seller'              => 'Scam sa online selling / pekeng seller',
        'Investment scam'                              => 'Investment scam',
        'Budol-budol / confidence scam'                => 'Budol-budol',
        'Acts of lasciviousness against passerby'      => 'Pambabastos sa dumadaan',
        'Harassment of commuter'                       => 'Panghaharas sa commuter',
        'Stabbing during altercation'                  => 'Pananaksak habang may away',
        'Gunshot by unidentified assailant'            => 'Pamamaril ng di-kilalang salarin',
    ];

    private function modusLabel(string $modus, string $lang): string
    {
        return $lang === 'tl' ? (self::MODUS_TL[$modus] ?? $modus) : $modus;
    }

    private function dayLabel(?string $day, string $lang): ?string
    {
        return $day === null ? null : ($lang === 'tl' ? (self::DAY_TL[$day] ?? $day) : $day);
    }

    private function catLabel(string $cat, string $lang): string
    {
        return $lang === 'tl' ? (self::CATEGORY_TL[$cat] ?? $cat) : $cat;
    }

    /**
     * Implementation steps written around THIS street's recorded cases: the
     * exact hours, the busiest day, and the modus actually used — not a
     * generic checklist.
     */
    private function stepsFor(string $action, string $street, string $window, ?string $peakDay, string $cat, array $modusNames, string $lang = 'en'): array
    {
        $tl = $lang === 'tl';
        $modusBrief = implode('; ', array_map(
            fn ($m) => $this->modusLabel($m, $lang),
            array_slice($modusNames, 0, 3)
        ));
        $catLabel = $this->catLabel($cat, $lang);
        $day = $this->dayLabel($peakDay, $lang);

        if ($tl) {
            $dayClause = $day ? ', may dagdag na round tuwing ' . $day . ' (kung kailan madalas ang kaso)' : '';

            switch ($action) {
                case 'Deploy roving tanod patrol (ronda)':
                    return [
                        'Magtalaga ng hindi bababa sa 2 tanod na dadaan sa ' . $street . ' kada 30-45 minuto tuwing ' . $window . $dayClause . '.',
                        $modusBrief !== ''
                            ? 'I-brief ang bawat shift sa modus na naitala dito — ' . $modusBrief . ' — para alam ng patrol ang eksaktong babantayan.'
                            : 'Pag-ibahin nang bahagya ang oras ng pagdaan para hindi mahulaan ang pattern.',
                        'Itala ang bawat pagdaan at obserbasyon, at i-review ang logbook linggo-linggo laban sa mga bagong ' . $catLabel . ' na report sa kalyeng ito.',
                    ];

                case 'Install streetlights':
                    return [
                        'Lakarin ang ' . $street . ' tuwing ' . $window . ' at ilista ang bawat madilim na bahagi, kanto at bunganga ng eskinita malapit sa mga naitalang ' . $catLabel . ' case.',
                        'I-file sa City Engineering Office ang request ng poste at palit-bombilya para sa mga eksaktong lugar na iyon; palitan ang sirang bombilya sa loob ng isang linggo.',
                        $modusBrief !== ''
                            ? 'Unahin ang mga lugar na kailangan ng dilim ng naitalang modus (' . $modusBrief . '), at magtalaga ng residente kada block na mag-uulat ng sirang ilaw.'
                            : 'Magtalaga ng residente kada block na mag-uulat sa barangay ng bagong sirang ilaw.',
                    ];

                case 'Install CCTV':
                    return [
                        'Magkabit ng camera sa magkabilang dulo ng ' . $street . ' at sa mga gitnang bahagi kung saan nagkukumpulan ang mga naitalang ' . $catLabel . ' case.',
                        'I-angulo ang entry/exit camera para malinaw ang mukha at plaka tuwing ' . $window . ' — ang mga oras kung kailan nangyayari ang mga kasong ito.',
                        'Maglagay ng CCTV signage, i-review ang footage tuwing may bagong report, at ibigay ang clips sa PNP na humahawak sa mga hindi pa naresolbang kaso dito.',
                    ];

                case 'Organize community watch':
                    return [
                        'Mag-recruit ng 5-10 volunteer na nakatira sa ' . $street . ' at ikonekta sila sa tanod team sa iisang group chat.',
                        $modusBrief !== ''
                            ? 'I-orient ang mga miyembro sa modus na naitala dito — ' . $modusBrief . ' — at sa ' . $window . ' na oras kung kailan nangyayari ang mga kaso.'
                            : 'I-orient ang mga miyembro sa ' . $window . ' na oras kung kailan nangyayari ang mga kaso dito.',
                        'Mag-iskedyul ng bantay ng mga miyembro bandang ' . $window . ($day ? ' tuwing ' . $day : '') . ' at diretsong i-report sa tanod chat ang mga napapansin.',
                    ];

                case 'Set up checkpoint':
                    return [
                        'Ilagay ang checkpoint sa entrada ng ' . $street . ' tuwing ' . $window . ', kung kailan nangyayari ang mga naitalang ' . $catLabel . ' case.',
                        $modusBrief !== ''
                            ? 'I-brief ang mga tauhan na bantayan ang mga sasakyan at kilos na tumutugma sa naitalang modus: ' . $modusBrief . '.'
                            : 'I-rotate ang iskedyul para hindi mahulaan.',
                        'I-rotate ang mga gabi nang random at makipag-coordinate sa lokal na PNP station para sa joint spot checks.',
                    ];

                case 'Run crime-awareness drive':
                    return [
                        'Mamigay ng advisory sa bawat bahay at tindahan sa ' . $street . ' na naglalarawan ng mga aktwal na naitalang kaso — '
                            . ($modusBrief !== '' ? $modusBrief : $catLabel) . ' — at ng ' . $window . ' na delikadong oras.',
                        'Mag-anunsyo ng paalala sa barangay PA system at social media page' . ($day ? ', bago sumapit ang ' . $day . ' kung kailan pumipeak ang mga kaso' : '') . '.',
                        'Magsagawa ng maikling seminar kasama ang PNP resource speaker tungkol sa pag-iwas sa ' . $catLabel . ' at imbitahan ang mga residente ng kalyeng ito.',
                    ];

                default:
                    return self::ACTION_STEPS[$action] ?? [];
            }
        }

        $dayClause = $peakDay ? ', with extra passes on ' . $peakDay . 's (when most cases here fall)' : '';

        switch ($action) {
            case 'Deploy roving tanod patrol (ronda)':
                return [
                    'Assign at least 2 tanods to pass through ' . $street . ' every 30-45 minutes during ' . $window . $dayClause . '.',
                    $modusBrief !== ''
                        ? 'Brief every shift on the modus recorded here — ' . $modusBrief . ' — so patrols know exactly what to watch for.'
                        : 'Vary pass times slightly so the pattern stays unpredictable.',
                    'Log every pass and observation, and review the logbook weekly against new ' . $cat . ' reports on this street.',
                ];

            case 'Install streetlights':
                return [
                    'Walk ' . $street . ' during ' . $window . ' and list every dark segment, corner and alley mouth near where the recorded ' . $cat . ' cases happened.',
                    'File lamppost and bulb-replacement requests for those exact spots with the City Engineering Office; replace busted bulbs within the week.',
                    $modusBrief !== ''
                        ? 'Prioritize the spots the recorded modus (' . $modusBrief . ') depends on darkness to work, and assign a resident per block to report outages.'
                        : 'Assign a resident per block to report new outages to the barangay.',
                ];

            case 'Install CCTV':
                return [
                    'Mount cameras covering both ends of ' . $street . ' and the mid-block spots where the recorded ' . $cat . ' cases cluster.',
                    'Angle entry/exit cameras to capture faces and plate numbers clearly during ' . $window . ' — the hours these cases happen.',
                    'Post CCTV signage, review footage after every new report, and turn over clips to the PNP handling the unresolved cases here.',
                ];

            case 'Organize community watch':
                return [
                    'Recruit 5-10 volunteers living on ' . $street . ' and link them with the tanod team in one group chat.',
                    $modusBrief !== ''
                        ? 'Orient members on the modus recorded here — ' . $modusBrief . ' — and the ' . $window . ' window when cases happen.'
                        : 'Orient members on the ' . $window . ' window when cases happen here.',
                    'Schedule member look-outs around ' . $window . ($peakDay ? ' on ' . $peakDay . 's' : '') . ' and report sightings straight to the tanod chat.',
                ];

            case 'Set up checkpoint':
                return [
                    'Position the checkpoint at the ' . $street . ' entry during ' . $window . ', when the recorded ' . $cat . ' cases happen.',
                    $modusBrief !== ''
                        ? 'Brief personnel to flag vehicles and behavior matching the recorded modus: ' . $modusBrief . '.'
                        : 'Rotate the schedule so it stays unpredictable.',
                    'Rotate nights unpredictably and coordinate joint spot checks with the local PNP station.',
                ];

            case 'Run crime-awareness drive':
                return [
                    'Distribute advisories to every household and store on ' . $street . ' describing the actual recorded cases — '
                        . ($modusBrief !== '' ? $modusBrief : $cat) . ' — and the ' . $window . ' risk window.',
                    'Announce reminders through the barangay PA system and social media page' . ($peakDay ? ', timed before ' . $peakDay . 's when cases peak' : '') . '.',
                    'Hold a short seminar with a PNP resource speaker focused on ' . $cat . ' prevention and invite the residents of this street.',
                ];

            default:
                return self::ACTION_STEPS[$action] ?? [];
        }
    }

    /**
     * Resident tips grounded in this street's cases: a timing tip built from
     * the real peak window/day, then modus-matched tips, topped up from the
     * category fallback pool.
     */
    private function tipsFor(string $cat, array $modusNames, string $window, ?string $peakDay, string $street, array $fallback, string $lang = 'en'): array
    {
        $tl = $lang === 'tl';
        $day = $this->dayLabel($peakDay, $lang);

        $tips = [
            $tl
                ? 'Mag-doble-ingat sa ' . $street . ' bandang ' . $window
                    . ($day ? ', lalo na tuwing ' . $day : '')
                    . ' — dito pumipeak ang mga naitalang ' . $this->catLabel($cat, $lang) . ' case.'
                : 'Be extra alert on ' . $street . ' around ' . $window
                    . ($peakDay ? ', especially on ' . $peakDay . 's' : '')
                    . ' — the recorded ' . $cat . ' cases here cluster in that window.',
        ];

        $rules = $tl ? self::MODUS_TIP_RULES_TL : self::MODUS_TIP_RULES;
        $modusLower = array_map('mb_strtolower', $modusNames);
        foreach ($rules as $needle => $tip) {
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
