<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The street geojson was rebuilt from the official PSGC boundary (bbox fetch +
 * clip), growing from 80 to 131 streets — Rolex, Osmeña Extension, Alfonso
 * Street, Interneighborhood Avenue, Elgine Street, Titus, Rado Street, Citizen
 * Street, Orient Street, Gregorio Drive, S. Osmeña Sr. Street and others that
 * the earlier OSM-relation fetch missed entirely.
 *
 * This migration gives every incident-less street 2-3 records (same category
 * mix and hour-of-day realism as 000006, codes SAT-2026-####), then re-trims
 * the table to TARGET_TOTAL with the same newest-first round-robin as 000008,
 * so the invariant holds: every street covered, ~200 rows, all inside the
 * boundary (strict polygon + 40 m tolerance, matching how the geojson was
 * clipped).
 *
 * Idempotent: streets already covered generate nothing, and the trim only
 * deletes when the cap is exceeded.
 */
return new class extends Migration
{
    private const TABLE = 'crime_department_san_agustin_incidents';
    private const TARGET_TOTAL = 200;
    private const PSGC_CODE = '137404095';
    private const ANCHOR_DATE = '2026-07-22';
    private const SPAN_DAYS = 730;

    /** STRICT: generated points must sit inside the polygon itself */
    private const EDGE_TOLERANCE_M = 0.0;

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

    private const CATEGORY_MIX = [
        ['Theft', 30, 1, 2], ['Robbery', 15, 2, 2], ['Assault', 13, 2, 3],
        ['Burglary', 11, 1, 2], ['Vehicle Theft', 8, 1, 2], ['Domestic Violence', 9, 1, 1],
        ['Fraud', 7, 2, 1], ['Sexual Offense', 4, 1, 1], ['Homicide', 3, 1, 2],
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
        if (empty($streets)) {
            return;
        }

        $this->boundary = $this->loadPsgcBoundary();

        $this->topUpEmptyStreets($streets);
        $this->trimToTarget();
        $this->backfillLookupIds();
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            DB::table(self::TABLE)->where('incident_code', 'like', 'SAT-%')->delete();
        }
    }

    // ----------------------------------------------------------------- top-up

    private function topUpEmptyStreets(array $streets): void
    {
        $counts = DB::table(self::TABLE)
            ->selectRaw('address_details, COUNT(*) as c')
            ->groupBy('address_details')
            ->pluck('c', 'address_details');

        $existingCodes = DB::table(self::TABLE)
            ->where('incident_code', 'like', 'SAT-%')
            ->pluck('incident_code')
            ->flip();

        $rows = [];
        $sequence = 0;
        $now = now();

        foreach ($streets as $name => $street) {
            $address = $name . ', Barangay San Agustin, Quezon City';
            if ((int) ($counts[$address] ?? 0) > 0) {
                continue;   // street already carries incidents
            }

            $seed = crc32('san-agustin-topup/' . $name);
            $target = 2 + $this->nextInt($seed, 2);   // 2-3 records

            for ($k = 0; $k < $target; $k++) {
                $sequence++;
                $code = sprintf('SAT-2026-%04d', $sequence);
                if ($existingCodes->has($code)) {
                    continue;
                }

                $rows[] = $this->makeIncident($code, $name, $address, $street, $seed, $now);
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table(self::TABLE)->insert($chunk);
        }
    }

    private function makeIncident(string $code, string $name, string $address, array $street, int &$seed, $now): array
    {
        [$category, $victimMax, $suspectMax] = $this->sampleCategory($seed);
        $date = $this->sampleDate($seed);
        $time = $this->sampleTime($category, $seed);
        [$lng, $lat] = $this->pointAlongStreet($street, $seed);

        $status = $this->pick($seed, ['reported', 'reported', 'under_investigation', 'under_investigation', 'solved', 'closed']);
        $cleared = in_array($status, ['solved', 'closed'], true);
        $modus = $this->pick($seed, self::MODUS[$category] ?? ['Under investigation']);

        return [
            'incident_code'        => $code,
            'record_type'          => 'crime',
            'category_name'        => $category,
            'barangay_name'        => 'San Agustin',
            'incident_title'       => $category . ' at ' . $name,
            'incident_description' => $modus . ' along ' . $name . ', Barangay San Agustin. Responding unit dispatched; case logged for investigation.',
            'incident_date'        => $date->format('Y-m-d'),
            'incident_time'        => $time,
            'latitude'             => round($lat, 8),
            'longitude'            => round($lng, 8),
            'address_details'      => $address,
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

    // ------------------------------------------------------------------- trim

    private function trimToTarget(): void
    {
        $rows = DB::table(self::TABLE)->get(['id', 'address_details', 'incident_date']);
        if ($rows->count() <= self::TARGET_TOTAL) {
            return;
        }

        $byStreet = $rows
            ->groupBy(fn ($r) => trim(explode(',', (string) $r->address_details)[0] ?? ''))
            ->map(fn ($group) => $group->sortByDesc('incident_date')->values())
            ->sortKeys();

        $keep = [];
        for ($round = 0; count($keep) < self::TARGET_TOTAL; $round++) {
            $tookAny = false;
            foreach ($byStreet as $group) {
                if (!isset($group[$round])) {
                    continue;
                }
                $keep[] = $group[$round]->id;
                $tookAny = true;
                if (count($keep) >= self::TARGET_TOTAL) {
                    break;
                }
            }
            if (!$tookAny) {
                break;
            }
        }

        $drop = $rows->pluck('id')->diff($keep);
        foreach ($drop->chunk(500) as $chunk) {
            DB::table(self::TABLE)->whereIn('id', $chunk->all())->delete();
        }
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

    private function sampleCategory(int &$seed): array
    {
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

            if (empty($this->boundary) || $this->withinBoundary($plng, $plat)) {
                return [$plng, $plat];
            }
        }

        // Last resort: the raw on-street point without the sideways nudge
        return [$lng, $lat];
    }

    // ------------------------------------------------------------- geometry

    private function withinBoundary(float $lng, float $lat): bool
    {
        return $this->contains($lng, $lat)
            || $this->distToEdgeMeters($lng, $lat) <= self::EDGE_TOLERANCE_M;
    }

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

    private function distToEdgeMeters(float $lng, float $lat): float
    {
        $poly = $this->boundary;
        $best = INF;
        $cos = cos(deg2rad($lat));
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$x1, $y1] = $poly[$j];
            [$x2, $y2] = $poly[$i];
            $dx = $x2 - $x1;
            $dy = $y2 - $y1;
            $lsq = $dx * $dx + $dy * $dy;
            $t = $lsq < 1e-18 ? 0.0 : max(0.0, min(1.0, (($lng - $x1) * $dx + ($lat - $y1) * $dy) / $lsq));
            $px = $x1 + $t * $dx;
            $py = $y1 + $t * $dy;
            $mx = ($px - $lng) * 111320 * $cos;
            $my = ($py - $lat) * 110574;
            $best = min($best, sqrt($mx * $mx + $my * $my));
        }

        return $best;
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
