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

    public function analyze(int $days = 180): array
    {
        $days = max(30, min(730, $days));

        $incidents = $this->loadIncidents($days);

        if ($incidents->isEmpty()) {
            return [
                'success' => false,
                'error'   => 'No San Agustin incident records found in the selected period, so there is nothing to analyze.',
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

        $aiResult = $this->callGemini($aggregates);

        if (isset($aiResult['error'])) {
            return ['success' => false, 'error' => $aiResult['error']];
        }

        $result = [
            'success' => true,
            'meta' => [
                'barangay'      => 'San Agustin',
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

    // ------------------------------------------------------------- data prep

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

                return [
                    'date'     => $date->toDateString(),
                    'month'    => $date->format('Y-m'),
                    'dow'      => $date->format('l'),
                    'hour'     => $hour,
                    'category' => $i->category_name ?: 'Uncategorized',
                    'street'   => trim((string) $i->address_details) ?: null,
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

        return [
            'barangay'            => 'San Agustin, Quezon City',
            'period_days'         => $days,
            'total_incidents'     => $incidents->count(),
            'first_half_count'    => $earlier,
            'second_half_count'   => $recent,
            'monthly_counts'      => $incidents->countBy('month')->sortKeys()->all(),
            'by_category'         => $incidents->countBy('category')->sortDesc()->take(12)->all(),
            'top_streets'         => $incidents->pluck('street')->filter()
                                        ->countBy()->sortDesc()->take(8)->all(),
            'by_day_of_week'      => $incidents->countBy('dow')->all(),
            'by_time_of_day'      => $timeBuckets,
            'unresolved_count'    => $incidents->whereNotIn('status', ['resolved', 'closed', 'cleared'])->count(),
        ];
    }

    // ------------------------------------------------------------ Gemini call

    private function callGemini(array $aggregates): array
    {
        $apiKey = config('services.gemini.key');
        if (!$apiKey) {
            return ['error' => 'Gemini API key is not configured.'];
        }

        $model = config('services.gemini.model', 'gemini-flash-latest');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $prompt = $this->buildPrompt($aggregates);

        $payload = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature'      => 0.3,
                'maxOutputTokens'  => self::MAX_OUTPUT_TOKENS,
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
        if (!is_array($parsed) || !isset($parsed['forecast'])) {
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
      "action": "<specific intervention, e.g. Install streetlights, Deploy roving patrol (roronda), Install CCTV, Community watch>",
      "location": "<specific street/area from top_streets, or a time window from the data, where it should be applied>",
      "rationale": "<1-2 sentences: why this intervention fits this data pattern>",
      "expected_impact": {
        "direction": "decrease" | "increase" | "stable",
        "estimated_change_percent": <signed number: projected change in crime IF this is implemented, e.g. -20>,
        "explanation": "<1 sentence: what happens to crime if this is implemented, based on established crime-prevention evidence>"
      },
      "priority": "high" | "medium" | "low"
    },
    "... 3 to 5 recommendations total"
  ]
}

Rules:
- Base the forecast on the actual trend in monthly_counts and the first_half_count vs second_half_count comparison.
- Recommendations must reference the actual streets, times, and crime categories in the data (e.g. lighting for night-time incidents, patrols for peak hours/days).
- estimated_change_percent must be realistic and grounded in published crime-prevention research effect sizes (e.g. improved street lighting ≈ -20% area crime, CCTV ≈ -13%, hot-spot patrols ≈ -15 to -25%).
- Keep every text field concise. Output JSON only, no markdown.
PROMPT;
    }
}
