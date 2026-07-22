<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the placeholder "Purok N" addresses with REAL San Agustin street
 * names (from OpenStreetMap, database/data/san_agustin_streets.geojson) and
 * guarantees every named street in the barangay carries incidents, so the AI
 * pattern analysis has full street-level coverage to work with.
 *
 * Two passes:
 *   1. RELOCATE — every existing row still addressed to a "Purok" is snapped
 *      to the nearest street polyline: coordinates move onto the street, the
 *      address becomes the street name, and the incident time is regenerated
 *      from that crime category's realistic hour-of-day profile (thefts in
 *      busy daytime hours, robberies and vehicle theft at night, burglaries
 *      when homes are empty, and so on).
 *   2. TOP-UP — streets left with fewer incidents than their floor (bigger
 *      roads get a higher floor) receive generated incidents placed along the
 *      street geometry, with the same category-accurate time profiles, a
 *      weekend bias, and a mild recent-months upward drift so trend analysis
 *      has a real signal.
 *
 * Everything is driven by a seeded LCG with a fixed anchor date, so re-running
 * the migration is a no-op (relocation only touches Purok rows; top-ups are
 * keyed by predictable SAS- incident codes that are skipped when present).
 */
return new class extends Migration
{
    private const TABLE = 'crime_department_san_agustin_incidents';

    /** Fixed anchor so generated dates never shift between runs */
    private const ANCHOR_DATE = '2026-07-22';

    /** How far back generated incidents may fall, in days (~24 months) */
    private const SPAN_DAYS = 730;

    /** Minimum incidents per street, by OSM highway class */
    private const FLOOR_BY_CLASS = [
        'primary' => 14, 'secondary' => 12, 'tertiary' => 10,
        'residential' => 5, 'unclassified' => 4, 'living_street' => 4,
        'service' => 3, 'footway' => 2, 'path' => 2, 'track' => 2,
    ];

    /**
     * Hour-of-day probability profiles per category. Each entry is
     * [hour => weight]; unlisted hours get weight 1. Drawn from PNP/UNODC
     * crime-timing literature: property crime tracks routine activity,
     * violence tracks night-time and weekends.
     */
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

    /** Category mix for generated incidents: [name, weight, victim_max, suspect_max] */
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

    private const REPORTERS = [
        'Concerned Citizen', 'Barangay Tanod', 'Victim', 'Store Owner', 'Resident',
        'Tricycle Driver', 'Barangay Official', 'Passerby',
    ];

    private const OFFICERS = [
        'PO1 R. Dela Cruz', 'PO2 M. Santos', 'PO3 J. Reyes', 'SPO1 A. Garcia',
        'PO1 K. Mendoza', 'PO2 L. Bautista', 'SPO2 E. Villanueva',
    ];

    /** Sunday..Saturday acceptance weights — weekends busier */
    private const DOW_WEIGHTS = [1.05, 0.75, 0.75, 0.8, 0.85, 1.2, 1.3];

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        $streets = $this->loadStreets();
        if (empty($streets)) {
            return;   // geojson missing — nothing to relocate against
        }

        $this->relocatePurokRows($streets);
        $this->topUpStreets($streets);
        $this->backfillLookupIds();
    }

    public function down(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            // Only the generated top-ups can be removed; relocation is one-way.
            DB::table(self::TABLE)->where('incident_code', 'like', 'SAS-%')->delete();
        }
    }

    // ------------------------------------------------------------ street data

    /**
     * Returns streets keyed by name:
     * [name => ['class' => ..., 'segments' => [[[lng,lat], ...], ...], 'length_m' => float]]
     */
    private function loadStreets(): array
    {
        $path = database_path('data/san_agustin_streets.geojson');
        if (!is_file($path)) {
            return [];
        }

        $geo = json_decode((string) file_get_contents($path), true);
        if (empty($geo['features'])) {
            return [];
        }

        $streets = [];
        foreach ($geo['features'] as $f) {
            $name = trim($f['properties']['name'] ?? '');
            $coords = $f['geometry']['coordinates'] ?? [];
            if ($name === '' || ($f['geometry']['type'] ?? '') !== 'LineString' || count($coords) < 2) {
                continue;
            }

            $streets[$name] ??= ['class' => 'residential', 'segments' => [], 'length_m' => 0.0];
            $streets[$name]['segments'][] = $coords;
            $streets[$name]['length_m'] += $this->polylineLength($coords);

            $class = $f['properties']['highway'] ?? 'residential';
            if (isset(self::FLOOR_BY_CLASS[$class])
                && self::FLOOR_BY_CLASS[$class] > (self::FLOOR_BY_CLASS[$streets[$name]['class']] ?? 0)) {
                $streets[$name]['class'] = $class;   // keep the biggest class seen
            }
        }

        ksort($streets);

        return $streets;
    }

    // ------------------------------------------------------- pass 1: relocate

    private function relocatePurokRows(array $streets): void
    {
        $rows = DB::table(self::TABLE)
            ->where('address_details', 'like', 'Purok%')
            ->orderBy('id')
            ->get(['id', 'latitude', 'longitude', 'incident_title', 'category_name']);

        foreach ($rows as $row) {
            [$street, $lat, $lng] = $this->snapToNearestStreet(
                (float) $row->latitude, (float) $row->longitude, $streets
            );

            $seed = (int) $row->id * 7919 + 17;
            $time = $this->sampleTime($row->category_name ?? '', $seed);

            DB::table(self::TABLE)->where('id', $row->id)->update([
                'latitude'        => round($lat, 8),
                'longitude'       => round($lng, 8),
                'address_details' => $street . ', Barangay San Agustin, Quezon City',
                'incident_title'  => $this->retitle((string) $row->incident_title, $street),
                'incident_time'   => $time,
            ]);
        }
    }

    /** @return array{0: string, 1: float, 2: float} street name, snapped lat, snapped lng */
    private function snapToNearestStreet(float $lat, float $lng, array $streets): array
    {
        $bestDist = INF;
        $bestName = array_key_first($streets);
        $bestPoint = [$lng, $lat];

        foreach ($streets as $name => $street) {
            foreach ($street['segments'] as $coords) {
                for ($i = 0, $n = count($coords) - 1; $i < $n; $i++) {
                    [$px, $py] = $this->nearestPointOnSegment(
                        $lng, $lat, $coords[$i][0], $coords[$i][1], $coords[$i + 1][0], $coords[$i + 1][1]
                    );
                    $d = $this->metersBetween($lat, $lng, $py, $px);
                    if ($d < $bestDist) {
                        $bestDist = $d;
                        $bestName = $name;
                        $bestPoint = [$px, $py];
                    }
                }
            }
        }

        return [$bestName, $bestPoint[1], $bestPoint[0]];
    }

    private function retitle(string $title, string $street): string
    {
        $short = trim(preg_split('/\s+at\s+/i', $title, 2)[0] ?? $title);

        return ($short === '' ? $title : $short) . ' at ' . $street;
    }

    // --------------------------------------------------------- pass 2: top-up

    private function topUpStreets(array $streets): void
    {
        $counts = DB::table(self::TABLE)
            ->selectRaw('address_details, COUNT(*) as c')
            ->groupBy('address_details')
            ->pluck('c', 'address_details');

        $existingCodes = DB::table(self::TABLE)
            ->where('incident_code', 'like', 'SAS-%')
            ->pluck('incident_code')
            ->flip();

        $rows = [];
        $sequence = 0;
        $now = now();

        foreach ($streets as $name => $street) {
            $address = $name . ', Barangay San Agustin, Quezon City';
            $have = (int) ($counts[$address] ?? 0);

            $seed = crc32('san-agustin/' . $name);
            $floor = self::FLOOR_BY_CLASS[$street['class']] ?? 4;
            // Longer streets see a bit more activity
            $floor += (int) min(4, floor($street['length_m'] / 400));
            $target = $floor + $this->nextInt($seed, 3);

            for ($k = $have; $k < $target; $k++) {
                $sequence++;
                $code = sprintf('SAS-2026-%04d', $sequence);
                if ($existingCodes->has($code)) {
                    continue;   // already generated on a previous run
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

        $status = $this->pick($seed, ['reported', 'reported', 'under_investigation', 'under_investigation', 'resolved', 'closed']);
        $cleared = in_array($status, ['resolved', 'closed'], true);
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
            'reported_by'          => $this->pick($seed, self::REPORTERS),
            'assigned_officer'     => $this->pick($seed, self::OFFICERS),
            'created_at'           => $now,
            'updated_at'           => $now,
        ];
    }

    /** @return array{0: string, 1: int, 2: int} */
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

    /**
     * Date within the span, biased toward recent months (mild upward trend)
     * and toward weekends via rejection sampling.
     */
    private function sampleDate(int &$seed): \DateTime
    {
        $anchor = new \DateTimeImmutable(self::ANCHOR_DATE);

        for ($try = 0; $try < 10; $try++) {
            // pow < 1 skews the offset small, i.e. toward recent dates
            $offset = (int) floor(pow($this->nextFloat($seed), 0.8) * self::SPAN_DAYS);
            $date = $anchor->modify('-' . $offset . ' days');
            $dow = (int) $date->format('w');

            if ($this->nextFloat($seed) * 1.3 <= self::DOW_WEIGHTS[$dow]) {
                return \DateTime::createFromImmutable($date);
            }
        }

        return \DateTime::createFromImmutable($anchor->modify('-' . $this->nextInt($seed, self::SPAN_DAYS) . ' days'));
    }

    /** HH:MM:SS drawn from the category's hour profile */
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

    /** Random point along the street's polyline, nudged ~5-20 m sideways */
    private function pointAlongStreet(array $street, int &$seed): array
    {
        // Pick a segment weighted by its share of the street's total length
        $segments = $street['segments'];
        $lengths = array_map(fn ($c) => $this->polylineLength($c), $segments);
        $roll = $this->nextFloat($seed) * max(1e-9, array_sum($lengths));
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

        // Perpendicular nudge so points sit beside the road like real addresses
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $len = max(1e-9, sqrt($dx * $dx + $dy * $dy));
        $meters = 5 + $this->nextFloat($seed) * 15;
        $side = $this->nextFloat($seed) < 0.5 ? -1 : 1;
        $deg = $meters / 111320;   // ~degrees per metre at the equator, close enough here

        return [$lng + (-$dy / $len) * $deg * $side, $lat + ($dx / $len) * $deg * $side];
    }

    // ------------------------------------------------------------- geometry

    private function polylineLength(array $coords): float
    {
        $m = 0.0;
        for ($i = 0, $n = count($coords) - 1; $i < $n; $i++) {
            $m += $this->metersBetween($coords[$i][1], $coords[$i][0], $coords[$i + 1][1], $coords[$i + 1][0]);
        }

        return $m;
    }

    private function metersBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dy = ($lat2 - $lat1) * 110574;
        $dx = ($lng2 - $lng1) * 111320 * cos(deg2rad(($lat1 + $lat2) / 2));

        return sqrt($dx * $dx + $dy * $dy);
    }

    /** @return array{0: float, 1: float} nearest [lng, lat] on the segment */
    private function nearestPointOnSegment(float $px, float $py, float $x1, float $y1, float $x2, float $y2): array
    {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $lenSq = $dx * $dx + $dy * $dy;
        if ($lenSq < 1e-18) {
            return [$x1, $y1];
        }

        $t = max(0.0, min(1.0, (($px - $x1) * $dx + ($py - $y1) * $dy) / $lenSq));

        return [$x1 + $t * $dx, $y1 + $t * $dy];
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

    /** Small LCG so generated data is identical on every run */
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
