<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Generates synthetic crime records for scenario analysis.
 *
 * Every record it returns has is_simulated = true. That flag is set here, at the
 * only place synthetic records are created, so nothing downstream has to remember
 * to add it — and with simulation off this class is never called at all.
 *
 * The generator is NOT a forecast. It resamples the shape of the real data
 * (hour-of-day, day-of-week, category mix, location clusters) and applies the
 * scenario multipliers the user asked for. It cannot predict real crime.
 */
class PatternSimulationGenerator
{
    /** Scatter applied to a cloned location, in degrees (~55 m) */
    private const LOCATION_JITTER = 0.0005;

    /**
     * @param Collection $real      Real incidents, as shaped by PatternDetectionService
     * @param array      $scenarios volume_multiplier, category_surge, time_spike, location_surge
     * @param int        $days      Period length to spread generated records across
     */
    public function generate(Collection $real, array $scenarios, int $days): Collection
    {
        if ($real->isEmpty()) {
            return collect();   // nothing to learn a shape from
        }

        $volumeMultiplier = max(0.0, (float) ($scenarios['volume_multiplier'] ?? 0.5));
        $targetCount = (int) round($real->count() * $volumeMultiplier);
        if ($targetCount < 1) {
            return collect();
        }
        $targetCount = min($targetCount, 5000);   // guard against runaway input

        // Distributions learned from the real data
        $hourWeights     = $this->weights($real->pluck('hour')->filter(fn ($h) => $h !== null));
        $dowWeights      = $this->weights($real->pluck('dow'));
        $categoryWeights = $this->weights($real->pluck('category'));
        $locations       = $real->filter(fn ($i) => $i['latitude'] && $i['longitude'])
            ->map(fn ($i) => [$i['latitude'], $i['longitude']])
            ->values();

        // Scenario knobs
        $categorySurge  = $scenarios['category_surge'] ?? null;    // ['category' => x, 'multiplier' => n]
        $timeSpike      = $scenarios['time_spike'] ?? null;        // ['start_hour' => h, 'end_hour' => h, 'multiplier' => n]
        $locationSurge  = $scenarios['location_surge'] ?? null;    // ['multiplier' => n] — concentrates on the top hotspot

        if ($categorySurge && !empty($categorySurge['category'])) {
            $name = $categorySurge['category'];
            $mult = max(1.0, (float) ($categorySurge['multiplier'] ?? 2));
            $categoryWeights[$name] = ($categoryWeights[$name] ?? 0.05) * $mult;
            $categoryWeights = $this->normalize($categoryWeights);
        }

        if ($timeSpike && isset($timeSpike['start_hour'], $timeSpike['end_hour'])) {
            $mult = max(1.0, (float) ($timeSpike['multiplier'] ?? 2));
            for ($h = (int) $timeSpike['start_hour']; $h <= (int) $timeSpike['end_hour']; $h++) {
                $hour = ($h + 24) % 24;
                $hourWeights[$hour] = ($hourWeights[$hour] ?? 0.02) * $mult;
            }
            $hourWeights = $this->normalize($hourWeights);
        }

        // Concentrate a share of generated records on the densest existing area
        $surgeAnchor = null;
        $surgeShare = 0.0;
        if ($locationSurge && $locations->isNotEmpty()) {
            $surgeAnchor = $this->densestPoint($locations);
            $surgeShare = min(0.9, max(0.0, ((float) ($locationSurge['multiplier'] ?? 2) - 1) / 4));
        }

        $start = now()->subDays($days);
        $out = [];

        for ($n = 0; $n < $targetCount; $n++) {
            $dow = (int) $this->pick($dowWeights);
            $date = $this->dateWithDayOfWeek($start, $days, $dow);
            $hour = $hourWeights ? (int) $this->pick($hourWeights) : random_int(0, 23);
            $category = (string) $this->pick($categoryWeights);

            if ($surgeAnchor && (mt_rand() / mt_getrandmax()) < $surgeShare) {
                [$lat, $lng] = $surgeAnchor;
            } elseif ($locations->isNotEmpty()) {
                [$lat, $lng] = $locations[random_int(0, $locations->count() - 1)];
            } else {
                [$lat, $lng] = [null, null];
            }

            if ($lat !== null) {
                $lat += $this->jitter();
                $lng += $this->jitter();
            }

            $out[] = [
                'id'            => 'SIM-' . ($n + 1),
                'incident_code' => 'SIM-' . str_pad((string) ($n + 1), 4, '0', STR_PAD_LEFT),
                'title'         => $category . ' (simulated)',
                'date'          => $date->toDateString(),
                'hour'          => $hour,
                'dow'           => $date->dayOfWeek,
                'latitude'      => $lat === null ? null : round($lat, 8),
                'longitude'     => $lng === null ? null : round($lng, 8),
                'category'      => $category,
                'barangay'      => 'San Agustin',
                'record_type'   => 'crime',
                'is_simulated'  => true,
            ];
        }

        return collect($out);
    }

    /** Frequency table normalised to sum 1 */
    private function weights(Collection $values): array
    {
        $counts = [];
        foreach ($values as $v) {
            if ($v === null) {
                continue;
            }
            $counts[$v] = ($counts[$v] ?? 0) + 1;
        }

        return $this->normalize($counts);
    }

    private function normalize(array $counts): array
    {
        $total = array_sum($counts);
        if ($total <= 0) {
            return [];
        }

        return array_map(fn ($c) => $c / $total, $counts);
    }

    /** Weighted random pick over a normalised distribution */
    private function pick(array $weights)
    {
        if (empty($weights)) {
            return null;
        }

        $r = mt_rand() / mt_getrandmax();
        $cumulative = 0.0;
        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($r <= $cumulative) {
                return $key;
            }
        }

        return array_key_last($weights);
    }

    /** A random date inside the window that falls on the requested weekday */
    private function dateWithDayOfWeek(Carbon $start, int $days, int $dow): Carbon
    {
        $offset = random_int(0, max(0, $days - 1));
        $date = $start->copy()->addDays($offset);

        $shift = ($dow - $date->dayOfWeek + 7) % 7;
        $date->addDays($shift);

        return $date->greaterThan(now()) ? $date->subWeek() : $date;
    }

    /** Grid-mode of the supplied points — the densest existing cluster */
    private function densestPoint(Collection $locations): array
    {
        $cells = [];
        foreach ($locations as [$lat, $lng]) {
            $key = round($lat / 0.0015) . ':' . round($lng / 0.0015);
            $cells[$key] ??= ['count' => 0, 'lat' => 0.0, 'lng' => 0.0];
            $cells[$key]['count']++;
            $cells[$key]['lat'] += $lat;
            $cells[$key]['lng'] += $lng;
        }

        $best = collect($cells)->sortByDesc('count')->first();

        return [$best['lat'] / $best['count'], $best['lng'] / $best['count']];
    }

    private function jitter(): float
    {
        return (mt_rand() / mt_getrandmax() - 0.5) * 2 * self::LOCATION_JITTER;
    }
}
