<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies the existing crime incidents into the San Agustin table.
 *
 * IMPORTANT — the source data contains no San Agustin records at all; every row
 * belongs to another barangay (Commonwealth, Litex, Batasan Hills, ...). To honour
 * the "San Agustin only" requirement each record is RELOCATED: its coordinates are
 * regenerated inside the San Agustin polygon and its address/title are rewritten,
 * because the original ones name landmarks in other barangays. The crime itself
 * (category, date, counts, status) is preserved.
 *
 * The relocation is seeded from the source row id, so re-running produces exactly
 * the same coordinates.
 */
return new class extends Migration
{
    /** Barangay San Agustin, Quezon City — PSGC 137404095. [lng, lat] */
    private const POLYGON = [
        [121.039612, 14.726895], [121.038996, 14.726262], [121.038419, 14.726334], [121.038018, 14.725859],
        [121.037263, 14.726140], [121.036333, 14.726059], [121.035001, 14.723995], [121.035285, 14.722808],
        [121.035769, 14.722889], [121.036022, 14.722252], [121.035417, 14.721832], [121.034295, 14.721795],
        [121.034332, 14.722444], [121.033787, 14.723374], [121.033584, 14.724775], [121.032707, 14.724873],
        [121.031408, 14.725681], [121.030561, 14.725916], [121.030961, 14.726653], [121.030966, 14.727419],
        [121.030514, 14.728083], [121.029730, 14.728554], [121.029021, 14.728471], [121.028787, 14.729426],
        [121.027361, 14.730137], [121.027532, 14.730545], [121.028326, 14.730940], [121.028311, 14.731869],
        [121.027775, 14.732289], [121.028082, 14.733364], [121.028572, 14.733174], [121.029616, 14.733343],
        [121.031352, 14.733001], [121.031159, 14.732207], [121.031908, 14.731987], [121.032158, 14.733172],
        [121.032794, 14.734112], [121.032929, 14.734785], [121.033979, 14.735900], [121.035408, 14.736114],
        [121.036431, 14.735962], [121.037898, 14.736018], [121.037865, 14.737582], [121.039419, 14.737598],
        [121.039427, 14.735762], [121.038554, 14.735755], [121.038562, 14.734830], [121.037839, 14.733980],
        [121.038965, 14.733413], [121.039214, 14.732315], [121.038549, 14.731546], [121.037242, 14.730977],
        [121.038274, 14.730477], [121.038890, 14.729830], [121.039335, 14.730292], [121.039859, 14.730013],
        [121.039752, 14.728794], [121.038905, 14.728635], [121.039291, 14.727349], [121.039612, 14.726895],
    ];

    /** Categories that are events/responses rather than criminal offences */
    private const INCIDENT_CATEGORIES = [
        'Fire Incident', 'Medical Emergency', 'Vehicular Accident', 'Natural Disaster',
        'Rescue Operation', 'Hazardous Material', 'Structural Fire', 'Vehicular Fire',
        'Grass/Brush Fire', 'Electrical Fire', 'Fire Rescue Operation', 'Hazmat Incident',
        'Road Obstruction', 'Suspicious Activity', 'Anonymous Tip', 'Noise Complaint',
        'Loitering', 'Illegal Parking', 'Traffic Violation',
    ];

    private const PUROKS = [
        'Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6', 'Purok 7',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('crime_department_san_agustin_incidents')
            || !Schema::hasTable('crime_department_crime_incidents')) {
            return;
        }

        $source = DB::table('crime_department_crime_incidents as i')
            ->leftJoin('crime_department_crime_categories as c', 'c.id', '=', 'i.crime_category_id')
            ->select('i.*', 'c.category_name')
            ->orderBy('i.id')
            ->get();

        if ($source->isEmpty()) {
            return;
        }

        $existing = DB::table('crime_department_san_agustin_incidents')
            ->pluck('incident_code')->flip();

        $rows = [];
        $now = now();

        foreach ($source as $row) {
            if ($existing->has($row->incident_code)) {
                continue;   // already seeded, keep this migration re-runnable
            }

            $seed = (int) $row->id;
            [$lng, $lat] = $this->pointInsideBarangay($seed);

            $category = $row->category_name ?: 'Uncategorized';
            $purok = self::PUROKS[$this->nextInt($seed, 7)];

            $rows[] = [
                'incident_code'        => $row->incident_code,
                'record_type'          => in_array($category, self::INCIDENT_CATEGORIES, true) ? 'incident' : 'crime',
                'category_name'        => $category,
                'barangay_name'        => 'San Agustin',
                'incident_title'       => $this->retitle($row->incident_title, $purok),
                'incident_description' => $row->incident_description,
                'incident_date'        => $row->incident_date,
                'incident_time'        => $row->incident_time,
                'latitude'             => round($lat, 8),
                'longitude'            => round($lng, 8),
                'address_details'      => $purok . ', Barangay San Agustin, Quezon City',
                'victim_count'         => $row->victim_count ?? 0,
                'suspect_count'        => $row->suspect_count ?? 0,
                'status'               => $row->status ?? 'reported',
                'clearance_status'     => $row->clearance_status ?? 'uncleared',
                'clearance_date'       => $row->clearance_date,
                'modus_operandi'       => $row->modus_operandi,
                'weather_condition'    => $row->weather_condition,
                'reported_by'          => $row->reported_by,
                'assigned_officer'     => $row->assigned_officer,
                'created_at'           => $row->created_at ?? $now,
                'updated_at'           => $row->updated_at ?? $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('crime_department_san_agustin_incidents')->insert($chunk);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('crime_department_san_agustin_incidents')) {
            DB::table('crime_department_san_agustin_incidents')->delete();
        }
    }

    /**
     * Strip the original location clause — it names landmarks in other barangays —
     * and point the title at the new San Agustin location.
     */
    private function retitle(string $title, string $purok): string
    {
        $short = preg_split('/\s+(at|near|in|along|on)\s+/i', $title, 2)[0];
        $short = trim($short);

        return ($short === '' ? $title : $short) . ' at ' . $purok . ', San Agustin';
    }

    /** Deterministic point inside the barangay polygon, seeded by the row id */
    private function pointInsideBarangay(int $seed): array
    {
        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        foreach (self::POLYGON as [$x, $y]) {
            $minX = min($minX, $x); $maxX = max($maxX, $x);
            $minY = min($minY, $y); $maxY = max($maxY, $y);
        }

        // Rejection sampling — the polygon fills a good share of its bounding box,
        // so this lands within a handful of tries.
        for ($i = 0; $i < 200; $i++) {
            $x = $minX + $this->nextFloat($seed) * ($maxX - $minX);
            $y = $minY + $this->nextFloat($seed) * ($maxY - $minY);
            if ($this->contains($x, $y)) {
                return [$x, $y];
            }
        }

        // Fallback: the polygon centroid is inside this shape
        $cx = 0; $cy = 0;
        foreach (self::POLYGON as [$x, $y]) { $cx += $x; $cy += $y; }
        return [$cx / count(self::POLYGON), $cy / count(self::POLYGON)];
    }

    private function contains(float $lng, float $lat): bool
    {
        $inside = false;
        $n = count(self::POLYGON);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = self::POLYGON[$i];
            [$xj, $yj] = self::POLYGON[$j];
            if (($yi > $lat) !== ($yj > $lat)
                && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }
        return $inside;
    }

    /** Small LCG so the generated data is identical on every run */
    private function nextFloat(int &$seed): float
    {
        $seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
        return $seed / 0x7FFFFFFF;
    }

    private function nextInt(int &$seed, int $bound): int
    {
        return (int) floor($this->nextFloat($seed) * $bound) % $bound;
    }
};
