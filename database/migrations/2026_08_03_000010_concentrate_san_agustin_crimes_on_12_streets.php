<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Concentrate every San Agustin crime onto exactly STREET_COUNT (12) streets,
 * each carrying at least MIN_PER_STREET (10) records — the user wants a few
 * clearly readable hot streets instead of 131 thinly covered ones.
 *
 *   1. The 12 longest streets (by total polyline length, deterministic) are
 *      chosen as the crime-carrying streets.
 *   2. Every incident sitting on any OTHER street is relocated onto one of the
 *      12 (round-robin, always topping up the emptiest street first): new
 *      point along the street, new address, and a REGENERATED title /
 *      description / modus so the text always matches the row's category.
 *   3. Streets still below 10 records get new rows (codes SAC-2026-####) with
 *      category-consistent titles, per-category hour-of-day realism, and a
 *      per-street dominant category so each street has a distinct crime
 *      profile.
 *
 * Idempotent: relocation only touches rows on non-chosen streets (none on a
 * re-run), and top-up only fires while a street is below the minimum.
 */
return new class extends Migration
{
    private const TABLE = 'crime_department_san_agustin_incidents';
    private const STREET_COUNT = 12;
    private const MIN_PER_STREET = 10;
    private const CODE_PREFIX = 'SAC-2026-';
    private const PSGC_CODE = '137404095';
    private const ANCHOR_DATE = '2026-08-03';
    private const SPAN_DAYS = 365;

    private array $boundary = [];

    private const HOUR_PROFILES = [
        'Theft'             => [10 => 6, 11 => 7, 12 => 8, 13 => 7, 17 => 8, 18 => 9, 19 => 7, 20 => 5],
        'Robbery'           => [19 => 6, 20 => 8, 21 => 9, 22 => 9, 23 => 8, 0 => 6, 1 => 5, 2 => 4],
        'Assault'           => [20 => 6, 21 => 8, 22 => 9, 23 => 9, 0 => 8, 1 => 7, 2 => 5],
        'Burglary'          => [1 => 6, 2 => 7, 3 => 8, 4 => 7, 10 => 5, 11 => 5, 14 => 5, 15 => 4],
        'Vehicle Theft'     => [22 => 7, 23 => 8, 0 => 9, 1 => 9, 2 => 8, 3 => 7, 4 => 5],
        'Domestic Violence' => [18 => 5, 19 => 6, 20 => 8, 21 => 9, 22 => 8, 23 => 6],
        'Fraud'             => [9 => 6, 10 => 8, 11 => 8, 12 => 6, 13 => 6, 14 => 8, 15 => 7, 16 => 6],
        'Sexual Offense'    => [20 => 6, 21 => 7, 22 => 8, 23 => 8, 0 => 7, 1 => 6],
        'Homicide'          => [21 => 6, 22 => 8, 23 => 9, 0 => 8, 1 => 7, 2 => 6],
    ];

    /** [category, weight, victimMax, suspectMax] — non-dominant share of the mix */
    private const CATEGORY_MIX = [
        ['Theft', 30, 1, 2], ['Robbery', 15, 2, 2], ['Assault', 13, 2, 3],
        ['Burglary', 11, 1, 2], ['Vehicle Theft', 8, 1, 2], ['Domestic Violence', 9, 1, 1],
        ['Fraud', 7, 2, 1], ['Sexual Offense', 4, 1, 1], ['Homicide', 3, 1, 2],
    ];

    /** Dominant category rotates per street so each street reads differently */
    private const DOMINANTS = [
        'Theft', 'Robbery', 'Assault', 'Burglary', 'Vehicle Theft', 'Theft',
        'Robbery', 'Domestic Violence', 'Fraud', 'Assault', 'Theft', 'Burglary',
    ];

    /**
     * Titles must always match the category (user requirement), and each title
     * is PAIRED BY INDEX with the MODUS entry below it so the title, modus and
     * description of one row always tell the same story.
     */
    private const TITLES = [
        'Theft'             => ['Pickpocketing incident', 'Cellphone snatching', 'Store merchandise theft', 'Bag slashing incident'],
        'Robbery'           => ['Armed hold-up of pedestrian', 'Riding-in-tandem robbery', 'Knife-point hold-up', 'Store hold-up'],
        'Assault'           => ['Injury from drunken altercation', 'Neighbor dispute turned physical', 'Street brawl injury'],
        'Burglary'          => ['Akyat-bahay break-in (forced door)', 'House break-in via window', 'Break-in through roof'],
        'Vehicle Theft'     => ['Motorcycle theft from street parking', 'Carnapping of parked vehicle', 'Motorcycle hotwiring theft'],
        'Domestic Violence' => ['Domestic disturbance', 'VAWC complaint'],
        'Fraud'             => ['Online selling scam', 'Investment scam complaint', 'Budol-budol confidence scam'],
        'Sexual Offense'    => ['Acts of lasciviousness report', 'Harassment of commuter'],
        'Homicide'          => ['Fatal stabbing incident', 'Fatal shooting incident'],
    ];

    private const MODUS = [
        'Theft'             => ['Pickpocketing in a crowded area', 'Snatching from pedestrian', 'Shoplifting from a store', 'Bag slashing'],
        'Robbery'           => ['Armed hold-up of pedestrian', 'Motorcycle-riding suspects (riding in tandem)', 'Knife-point robbery', 'Store hold-up'],
        'Assault'           => ['Drunken altercation escalated', 'Neighborhood dispute turned physical', 'Street brawl'],
        'Burglary'          => ['Forced door entry while occupants away', 'Window entry at unoccupied house', 'Roof/ceiling entry'],
        'Vehicle Theft'     => ['Motorcycle stolen from street parking', 'Carnapping of parked vehicle', 'Broken ignition / hotwiring'],
        'Domestic Violence' => ['Domestic dispute', 'Intimate partner violence'],
        'Fraud'             => ['Online sale scam / bogus seller', 'Investment scam', 'Budol-budol / confidence scam'],
        'Sexual Offense'    => ['Acts of lasciviousness against passerby', 'Harassment of commuter'],
        'Homicide'          => ['Stabbing during altercation', 'Gunshot by unidentified assailant'],
    ];

    private const WEATHER = ['Clear', 'Cloudy', 'Rainy', 'Overcast', 'Humid'];
    private const OFFICERS = [
        'PO1 R. Dela Cruz', 'PO2 M. Santos', 'PO3 J. Reyes', 'SPO1 A. Garcia',
        'PO1 K. Mendoza', 'PO2 L. Bautista', 'SPO2 E. Villanueva',
    ];
    private const DOW_WEIGHTS = [1.05, 0.75, 0.75, 0.8, 0.85, 1.2, 1.3];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $streets = $this->loadStreets();
        if (count($streets) < self::STREET_COUNT) {
            return;
        }

        $this->boundary = $this->loadPsgcBoundary();
        $chosen = $this->chooseStreets($streets);

        $counts = $this->relocateStrays($chosen);
        $this->topUpChosen($chosen, $counts);
        $this->backfillLookupIds();
    }

    public function down(): void
    {
        // Relocations are not reversible; only the generated rows are removed.
        if (Schema::hasTable(self::TABLE)) {
            DB::table(self::TABLE)->where('incident_code', 'like', self::CODE_PREFIX . '%')->delete();
        }
    }

    /** The 12 longest streets — deterministic and stable across runs */
    private function chooseStreets(array $streets): array
    {
        $ranked = [];
        foreach ($streets as $name => $street) {
            $len = 0.0;
            foreach ($street['segments'] as $coords) {
                $len += $this->polylineLength($coords);
            }
            $ranked[$name] = $len;
        }
        arsort($ranked);

        $chosen = [];
        foreach (array_slice(array_keys($ranked), 0, self::STREET_COUNT) as $name) {
            $chosen[$name] = $streets[$name];
        }
        ksort($chosen);

        return $chosen;
    }

    // ------------------------------------------------------------- relocation

    /**
     * Move every incident on a NON-chosen street onto the chosen street that
     * currently has the fewest records. Returns the per-street counts after.
     */
    private function relocateStrays(array $chosen): array
    {
        $addressOf = fn (string $name) => $name . ', Barangay San Agustin, Quezon City';

        $counts = array_fill_keys(array_keys($chosen), 0);
        $strays = [];

        foreach (DB::table(self::TABLE)->orderBy('id')->get(['id', 'address_details', 'category_name']) as $row) {
            $street = trim(explode(',', (string) $row->address_details)[0] ?? '');
            if (isset($chosen[$street])) {
                $counts[$street]++;
            } else {
                $strays[] = $row;
            }
        }

        foreach ($strays as $row) {
            // Always fill the emptiest street first → even spread
            asort($counts);
            $target = array_key_first($counts);

            $seed = crc32('san-agustin-concentrate/' . $row->id);
            [$lng, $lat] = $this->pointAlongStreet($chosen[$target], $seed);

            $category = trim((string) $row->category_name) ?: 'Theft';
            [$title, $modus] = $this->titleAndModus($category, $seed);

            DB::table(self::TABLE)->where('id', $row->id)->update([
                'latitude'             => round($lat, 8),
                'longitude'            => round($lng, 8),
                'address_details'      => $addressOf($target),
                'incident_title'       => $title . ' along ' . $target,
                'incident_description' => $modus . ' along ' . $target . ', Barangay San Agustin. Responding unit dispatched; case logged for investigation.',
                'modus_operandi'       => $modus,
                'updated_at'           => now(),
            ]);

            $counts[$target]++;
        }

        return $counts;
    }

    // ----------------------------------------------------------------- top-up

    private function topUpChosen(array $chosen, array $counts): void
    {
        $existingCodes = DB::table(self::TABLE)
            ->where('incident_code', 'like', self::CODE_PREFIX . '%')
            ->pluck('incident_code')
            ->flip();

        $rows = [];
        $sequence = 0;
        $now = now();
        $index = 0;

        foreach ($chosen as $name => $street) {
            $seed = crc32('san-agustin-concentrate-topup/' . $name);
            $dominant = self::DOMINANTS[$index % count(self::DOMINANTS)];
            $index++;

            // 10 guaranteed + 0-4 extra so street totals differ naturally
            $target = self::MIN_PER_STREET + $this->nextInt($seed, 5);
            $need = $target - (int) ($counts[$name] ?? 0);

            for ($k = 0; $k < $need; $k++) {
                do {
                    $sequence++;
                    $code = self::CODE_PREFIX . sprintf('%04d', $sequence);
                } while ($existingCodes->has($code));

                $rows[] = $this->makeIncident($code, $name, $street, $dominant, $seed, $now);
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table(self::TABLE)->insert($chunk);
        }
    }

    private function makeIncident(string $code, string $name, array $street, string $dominant, int &$seed, $now): array
    {
        [$category, $victimMax, $suspectMax] = $this->sampleCategory($seed, $dominant);
        $date = $this->sampleDate($seed);
        $time = $this->sampleTime($category, $seed);
        [$lng, $lat] = $this->pointAlongStreet($street, $seed);

        $status = $this->pick($seed, ['reported', 'reported', 'under_investigation', 'under_investigation', 'solved', 'closed']);
        $cleared = in_array($status, ['solved', 'closed'], true);
        [$title, $modus] = $this->titleAndModus($category, $seed);

        return [
            'incident_code'        => $code,
            'record_type'          => 'crime',
            'category_name'        => $category,
            'barangay_name'        => 'San Agustin',
            'incident_title'       => $title . ' along ' . $name,
            'incident_description' => $modus . ' along ' . $name . ', Barangay San Agustin. Responding unit dispatched; case logged for investigation.',
            'incident_date'        => $date->format('Y-m-d'),
            'incident_time'        => $time,
            'latitude'             => round($lat, 8),
            'longitude'            => round($lng, 8),
            'address_details'      => $name . ', Barangay San Agustin, Quezon City',
            'victim_count'         => 1 + $this->nextInt($seed, max(1, $victimMax)),
            'suspect_count'        => $this->nextInt($seed, $suspectMax + 1),
            'status'               => $status,
            'clearance_status'     => $cleared ? 'cleared' : 'uncleared',
            'clearance_date'       => $cleared ? $date->modify('+' . (3 + $this->nextInt($seed, 21)) . ' days')->format('Y-m-d') : null,
            'modus_operandi'       => $modus,
            'weather_condition'    => $this->pick($seed, self::WEATHER),
            'reported_by'          => null,
            'assigned_officer'     => $this->pick($seed, self::OFFICERS),
            'created_at'           => $now,
            'updated_at'           => $now,
        ];
    }

    /** One index drives both arrays, so title and modus always agree */
    private function titleAndModus(string $category, int &$seed): array
    {
        $titles = self::TITLES[$category] ?? [$category . ' incident'];
        $modus = self::MODUS[$category] ?? ['Under investigation'];
        $idx = $this->nextInt($seed, min(count($titles), count($modus)));

        return [$titles[$idx], $modus[$idx]];
    }

    // ------------------------------------------------------------ street data

    private function loadStreets(): array
    {
        $path = database_path('data/san_agustin_streets.geojson');
        if (!is_file($path)) {
            return [];
        }

        $geo = json_decode((string) file_get_contents($path), true);
        $streets = [];
        foreach ($geo['features'] ?? [] as $f) {
            $name = trim($f['properties']['name'] ?? '');
            $coords = $f['geometry']['coordinates'] ?? [];
            if ($name === '' || ($f['geometry']['type'] ?? '') !== 'LineString' || count($coords) < 2) {
                continue;
            }

            $streets[$name] ??= ['segments' => []];
            $streets[$name]['segments'][] = $coords;
        }
        ksort($streets);

        return $streets;
    }

    private function loadPsgcBoundary(): array
    {
        $path = public_path('qc_barangays.geojson');
        if (!is_file($path)) {
            return [];
        }

        $geo = json_decode((string) file_get_contents($path), true);
        foreach ($geo['features'] ?? [] as $f) {
            if ((string) ($f['properties']['code'] ?? '') !== self::PSGC_CODE) {
                continue;
            }
            $g = $f['geometry'] ?? [];
            if (($g['type'] ?? '') === 'Polygon') {
                return $g['coordinates'][0] ?? [];
            }
            if (($g['type'] ?? '') === 'MultiPolygon') {
                return $g['coordinates'][0][0] ?? [];
            }
        }

        return [];
    }

    // --------------------------------------------------------------- sampling

    /** Dominant category ~55% of the time; the weighted mix otherwise */
    private function sampleCategory(int &$seed, string $dominant): array
    {
        if ($this->nextFloat($seed) < 0.55) {
            foreach (self::CATEGORY_MIX as [$name, $weight, $victimMax, $suspectMax]) {
                if ($name === $dominant) {
                    return [$name, $victimMax, $suspectMax];
                }
            }
        }

        $total = array_sum(array_column(self::CATEGORY_MIX, 1));
        $roll = $this->nextFloat($seed) * $total;
        foreach (self::CATEGORY_MIX as [$name, $weight, $victimMax, $suspectMax]) {
            $roll -= $weight;
            if ($roll <= 0) {
                return [$name, $victimMax, $suspectMax];
            }
        }

        return ['Theft', 1, 2];
    }

    private function sampleDate(int &$seed): \DateTime
    {
        $anchor = new \DateTimeImmutable(self::ANCHOR_DATE);
        for ($try = 0; $try < 10; $try++) {
            $offset = (int) floor(pow($this->nextFloat($seed), 0.8) * self::SPAN_DAYS);
            $date = $anchor->modify('-' . $offset . ' days');
            if ($this->nextFloat($seed) * 1.3 <= self::DOW_WEIGHTS[(int) $date->format('w')]) {
                return \DateTime::createFromImmutable($date);
            }
        }

        return \DateTime::createFromImmutable($anchor->modify('-' . $this->nextInt($seed, self::SPAN_DAYS) . ' days'));
    }

    private function sampleTime(string $category, int &$seed): string
    {
        $profile = self::HOUR_PROFILES[$category] ?? [];
        $weights = [];
        $total = 0;
        for ($h = 0; $h < 24; $h++) {
            $weights[$h] = $profile[$h] ?? 1;
            $total += $weights[$h];
        }

        $roll = $this->nextFloat($seed) * $total;
        $hour = 23;
        for ($h = 0; $h < 24; $h++) {
            $roll -= $weights[$h];
            if ($roll <= 0) {
                $hour = $h;
                break;
            }
        }

        return sprintf('%02d:%02d:00', $hour, $this->nextInt($seed, 60));
    }

    /** Point along the street, nudged sideways, retried until inside the boundary */
    private function pointAlongStreet(array $street, int &$seed): array
    {
        $segments = $street['segments'];
        $lengths = array_map(fn ($c) => $this->polylineLength($c), $segments);
        $totalLen = max(1e-9, array_sum($lengths));

        $lng = $segments[0][0][0];
        $lat = $segments[0][0][1];

        for ($try = 0; $try < 12; $try++) {
            $roll = $this->nextFloat($seed) * $totalLen;
            $coords = $segments[0];
            foreach ($segments as $i => $c) {
                $roll -= $lengths[$i];
                if ($roll <= 0) {
                    $coords = $c;
                    break;
                }
            }

            $i = $this->nextInt($seed, count($coords) - 1);
            $t = $this->nextFloat($seed);
            [$x1, $y1] = $coords[$i];
            [$x2, $y2] = $coords[$i + 1];
            $lng = $x1 + ($x2 - $x1) * $t;
            $lat = $y1 + ($y2 - $y1) * $t;

            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $len = max(1e-9, sqrt($dx * $dx + $dy * $dy));
            $meters = 5 + $this->nextFloat($seed) * 15;
            $side = $this->nextFloat($seed) < 0.5 ? -1 : 1;
            $deg = $meters / 111320;

            $plng = $lng + (-$dy / $len) * $deg * $side;
            $plat = $lat + ($dx / $len) * $deg * $side;

            if (empty($this->boundary) || $this->contains($plng, $plat)) {
                return [$plng, $plat];
            }
        }

        // Last resort: the raw on-street point without the sideways nudge
        return [$lng, $lat];
    }

    // ------------------------------------------------------------- geometry

    private function contains(float $lng, float $lat): bool
    {
        $poly = $this->boundary;
        $inside = false;
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $poly[$i];
            [$xj, $yj] = $poly[$j];
            if (($yi > $lat) !== ($yj > $lat)
                && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    private function polylineLength(array $coords): float
    {
        $m = 0.0;
        for ($i = 0, $n = count($coords) - 1; $i < $n; $i++) {
            $dy = ($coords[$i + 1][1] - $coords[$i][1]) * 110574;
            $dx = ($coords[$i + 1][0] - $coords[$i][0]) * 111320 * cos(deg2rad($coords[$i][1]));
            $m += sqrt($dx * $dx + $dy * $dy);
        }

        return $m;
    }

    // ---------------------------------------------------------------- lookups

    private function backfillLookupIds(): void
    {
        if (Schema::hasColumn(self::TABLE, 'crime_category_id')
            && Schema::hasTable('crime_department_crime_categories')) {
            foreach (DB::table('crime_department_crime_categories')->get(['id', 'category_name']) as $category) {
                DB::table(self::TABLE)
                    ->whereNull('crime_category_id')
                    ->whereRaw('LOWER(TRIM(category_name)) = ?', [mb_strtolower(trim($category->category_name))])
                    ->update(['crime_category_id' => $category->id]);
            }
        }

        if (Schema::hasColumn(self::TABLE, 'barangay_id')
            && Schema::hasTable('crime_department_barangays')) {
            $sanAgustin = DB::table('crime_department_barangays')
                ->whereRaw('LOWER(TRIM(barangay_name)) = ?', ['san agustin'])
                ->value('id');

            if ($sanAgustin) {
                DB::table(self::TABLE)
                    ->whereNull('barangay_id')
                    ->update(['barangay_id' => $sanAgustin]);
            }
        }
    }

    // ------------------------------------------------------------------- rng

    private function nextFloat(int &$seed): float
    {
        $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;

        return $seed / 0x7FFFFFFF;
    }

    private function nextInt(int &$seed, int $bound): int
    {
        return $bound <= 0 ? 0 : (int) floor($this->nextFloat($seed) * $bound) % $bound;
    }

    private function pick(int &$seed, array $options)
    {
        return $options[$this->nextInt($seed, count($options))];
    }
};
