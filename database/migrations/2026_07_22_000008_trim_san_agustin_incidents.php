<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cleans up the San Agustin incident table after the street relocation:
 *
 *   1. DELETES every incident whose coordinates fall OUTSIDE the Barangay
 *      San Agustin boundary. OSM street geometries continue past the barangay
 *      edge (Susano Road, Don Alejandro Avenue, ...), so points generated
 *      along the full polyline could land in neighbouring barangays.
 *   2. TRIMS the table down to TARGET_TOTAL records. Selection is a
 *      round-robin across streets ordered by most-recent date, so every
 *      street keeps its newest incidents and street coverage survives the
 *      cut — no street loses all of its records unless it had none inside
 *      the boundary to begin with.
 *
 * Idempotent: a second run finds nothing outside the boundary and nothing
 * above the cap, and deletes nothing.
 */
return new class extends Migration
{
    private const TABLE = 'crime_department_san_agustin_incidents';

    private const TARGET_TOTAL = 200;

    /** PSGC code of Barangay San Agustin in public/qc_barangays.geojson */
    private const PSGC_CODE = '137404095';

    /** Runtime boundary — the PSGC ring when available, else self::POLYGON */
    private array $boundary = [];

    /** Fallback only — same rough polygon as the 000002 seeder. [lng, lat] */
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

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        // Prefer the PSGC barangay polygon — it is the exact boundary the
        // mapping page draws, so "inside" here matches what users see there.
        $this->boundary = $this->loadPsgcBoundary() ?: self::POLYGON;

        $rows = DB::table(self::TABLE)
            ->get(['id', 'latitude', 'longitude', 'address_details', 'incident_date']);

        if ($rows->isEmpty()) {
            return;
        }

        // ---- 1. drop everything outside the barangay boundary
        $outside = $rows->filter(
            fn ($r) => !$this->contains((float) $r->longitude, (float) $r->latitude)
        )->pluck('id');

        foreach ($outside->chunk(500) as $chunk) {
            DB::table(self::TABLE)->whereIn('id', $chunk->all())->delete();
        }

        $inside = $rows->whereNotIn('id', $outside->all());

        // ---- 2. cap at TARGET_TOTAL, round-robin per street, newest first
        if ($inside->count() <= self::TARGET_TOTAL) {
            return;
        }

        $byStreet = $inside
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
                break;   // every street exhausted
            }
        }

        $drop = $inside->pluck('id')->diff($keep);
        foreach ($drop->chunk(500) as $chunk) {
            DB::table(self::TABLE)->whereIn('id', $chunk->all())->delete();
        }
    }

    public function down(): void
    {
        // Deletions cannot be restored; re-run migrations 000002/000006 to reseed.
    }

    /** Outer ring of the San Agustin feature in qc_barangays.geojson, or [] */
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

    private function contains(float $lng, float $lat): bool
    {
        $polygon = $this->boundary ?: self::POLYGON;
        $inside = false;
        $n = count($polygon);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $polygon[$i];
            [$xj, $yj] = $polygon[$j];
            if (($yi > $lat) !== ($yj > $lat)
                && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = !$inside;
            }
        }

        return $inside;
    }
};
