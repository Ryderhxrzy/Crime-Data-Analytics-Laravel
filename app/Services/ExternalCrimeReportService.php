<?php

namespace App\Services;

use App\Models\CrimeCategory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Read-only reader for the Alertara Reports system (report.alertaraqc.com).
 *
 * It pulls the case and blotter feeds, keeps only the fields the crime map
 * actually needs, and drops rows that carry no usable crime data. Nothing here
 * writes to our database — ExternalCrimeImportController decides what gets
 * inserted, and re-reads from this service so it never trusts values posted by
 * the browser.
 *
 * The Reports feed has no coordinates of its own (its "location" is free text
 * such as "Local Store"), so the importer pairs every record with a San Agustin
 * street and this service turns that street into a point on the street's
 * polyline — the same geojson the map draws.
 */
class ExternalCrimeReportService
{
    private const RECORDS_CACHE = 'alertara_reports_records_v1';
    private const RECORDS_TTL   = 300;      // seconds — the feed is polled, not pushed
    private const STREETS_CACHE = 'alertara_street_index_v1';
    private const MAX_ROWS      = 500;

    /** Reports incident_type (lowercased) => crime category used by this system */
    private const CATEGORY_SYNONYMS = [
        'theft'               => 'Theft',
        'shoplifting'         => 'Theft',
        'snatching'           => 'Theft',
        'pickpocketing'       => 'Theft',
        'robbery'             => 'Robbery',
        'hold-up'             => 'Robbery',
        'holdup'              => 'Robbery',
        'assault'             => 'Assault',
        'violence'            => 'Assault',
        'physical injury'     => 'Assault',
        'serious injury'      => 'Assault',
        'minor dispute'       => 'Assault',
        'burglary'            => 'Burglary',
        'break-in'            => 'Burglary',
        'breaking and entering' => 'Burglary',
        'vehicle theft'       => 'Vehicle Theft',
        'carnapping'          => 'Vehicle Theft',
        'motorcycle theft'    => 'Vehicle Theft',
        'abuse'               => 'Domestic Violence',
        'domestic violence'   => 'Domestic Violence',
        'domestic abuse'      => 'Domestic Violence',
        'vawc'                => 'Domestic Violence',
        'fraud'               => 'Fraud',
        'scam'                => 'Fraud',
        'estafa'              => 'Fraud',
        'swindling'           => 'Fraud',
        'sexual offense'      => 'Sexual Offense',
        'sexual assault'      => 'Sexual Offense',
        'rape'                => 'Sexual Offense',
        'harassment'          => 'Sexual Offense',
        'acts of lasciviousness' => 'Sexual Offense',
        'homicide'            => 'Homicide',
        'murder'              => 'Homicide',
        'attempted murder'    => 'Homicide',
        'killing'             => 'Homicide',
    ];

    /** Reports workflow status (lowercased) => our status enum */
    private const STATUS_MAP = [
        'draft'          => 'reported',
        'pending'        => 'reported',
        'submitted'      => 'reported',
        'new'            => 'reported',
        'under review'   => 'under_investigation',
        'under_review'   => 'under_investigation',
        'investigating'  => 'under_investigation',
        'in progress'    => 'under_investigation',
        'resolved'       => 'solved',
        'solved'         => 'solved',
        'closed'         => 'closed',
        'rejected'       => 'closed',
        'dismissed'      => 'closed',
        'archived'       => 'archived',
    ];

    // ------------------------------------------------------------ the feed

    /**
     * Normalised crime records from the Reports API, newest first.
     * Returns [] when the API is unreachable or has nothing usable.
     */
    public function records(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::RECORDS_CACHE);
        }

        return Cache::remember(self::RECORDS_CACHE, self::RECORDS_TTL, function () {
            $records = [];

            foreach ($this->fetch('cases') as $row) {
                if ($record = $this->normaliseCase($row)) {
                    $records[$record['code']] = $record;
                }
            }

            foreach ($this->fetch('blotters') as $row) {
                if ($record = $this->normaliseBlotter($row)) {
                    $records[$record['code']] ??= $record;
                }
            }

            $records = array_values($records);
            usort($records, fn ($a, $b) => strcmp((string) $b['reported_at'], (string) $a['reported_at']));

            return $records;
        });
    }

    /** The normalised records keyed by their code, for lookup during import */
    public function recordsByCode(bool $fresh = false): array
    {
        $keyed = [];
        foreach ($this->records($fresh) as $record) {
            $keyed[$record['code']] = $record;
        }

        return $keyed;
    }

    /** One list from the unified API (`cases` or `blotters`); [] on any failure */
    private function fetch(string $action): array
    {
        $url = (string) config('services.alertara_reports.url');
        if ($url === '') {
            return [];
        }

        try {
            $response = Http::timeout((int) config('services.alertara_reports.timeout', 20))
                ->acceptJson()
                ->get($url, ['action' => $action, 'limit' => self::MAX_ROWS]);

            if (! $response->successful()) {
                Log::warning('Reports API request failed', ['action' => $action, 'status' => $response->status()]);

                return [];
            }

            $body = $response->json();
            if (! is_array($body) || ($body['status'] ?? null) !== 'success') {
                Log::warning('Reports API returned a non-success payload', [
                    'action'  => $action,
                    'message' => is_array($body) ? ($body['message'] ?? null) : null,
                ]);

                return [];
            }

            $rows = $body['data'][$action] ?? [];

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            Log::error('Reports API unreachable', ['action' => $action, 'error' => $e->getMessage()]);

            return [];
        }
    }

    // ------------------------------------------------------- normalisation

    /**
     * A case row. Returns null when the row carries nothing worth mapping —
     * no reference number, no date, or neither a crime type nor a narrative.
     */
    private function normaliseCase(array $row): ?array
    {
        $id        = (int) ($row['id'] ?? 0);
        $reference = trim((string) ($row['case_no'] ?? ''));
        $type      = trim((string) ($row['incident_type'] ?? ''));
        $narrative = trim((string) ($row['narrative'] ?? '')) ?: trim((string) ($row['description'] ?? ''));
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $date      = $this->cleanDate($row['incident_date'] ?? null) ?? $this->cleanDate(substr($createdAt, 0, 10));
        $rawStatus = trim((string) ($row['status'] ?? ''));

        // A row with no date at all, or with neither a crime type nor a
        // narrative, describes nothing we could put on a map.
        if ($id === 0 || $date === null || ($type === '' && $narrative === '')) {
            return null;
        }

        $location = trim((string) ($row['location'] ?? ''));
        $victim   = trim((string) ($row['victim_name'] ?? ''));
        $suspect  = trim((string) ($row['suspect_name'] ?? ''));
        $status   = $this->mapStatus($rawStatus);

        return [
            'source'      => 'case',
            'external_id' => $id,
            'code'        => $reference !== '' ? mb_substr($reference, 0, 50) : 'AR-CASE-' . $id,
            'reference'   => $reference !== '' ? $reference : null,
            // Unfiled work: still in Draft, or filed without a case number.
            'draft'       => mb_strtolower($rawStatus) === 'draft' || $reference === '',
            'type'        => $type !== '' ? $type : 'Other',
            'category'    => $this->categoryFor($type),
            'title'       => $this->buildTitle($type, $location),
            'description' => $narrative !== '' ? $narrative : null,
            'date'        => $date,
            'time'        => $this->cleanTime($row['incident_time'] ?? null),
            'location'    => $location !== '' ? $location : null,
            'street_hint' => $this->matchStreet($location),
            'lat'         => $this->cleanCoordinate($row['latitude'] ?? null, -90, 90),
            'lng'         => $this->cleanCoordinate($row['longitude'] ?? null, -180, 180),
            'status'      => $status,
            'raw_status'  => $rawStatus ?: null,
            'clearance'   => $status === 'solved' ? 'cleared' : 'uncleared',
            'urgency'     => trim((string) ($row['urgency_level'] ?? '')) ?: null,
            'high_risk'   => (bool) ($row['is_high_risk'] ?? false),
            'reporter'    => trim((string) ($row['reporter_name'] ?? '')) ?: null,
            'victim'      => $victim !== '' ? $victim : null,
            'suspect'     => $suspect !== '' ? $suspect : null,
            'victims'     => $victim !== '' ? 1 : 0,
            'suspects'    => $suspect !== '' ? 1 : 0,
            'officer'     => trim((string) ($row['assigned_to'] ?? '')) ?: null,
            'modus'       => trim((string) ($row['incident_subtype'] ?? '')) ?: null,
            'reported_at' => $createdAt ?: $date,
        ];
    }

    /** A blotter row — thinner than a case: no narrative, no reporter contact */
    private function normaliseBlotter(array $row): ?array
    {
        $id        = (int) ($row['id'] ?? 0);
        $reference = trim((string) ($row['blotter_no'] ?? ''));
        $type      = trim((string) ($row['incident_type'] ?? ''));
        $createdAt = trim((string) ($row['created_at'] ?? ''));
        $date      = $this->cleanDate(substr($createdAt, 0, 10));
        $rawStatus = trim((string) ($row['status'] ?? ''));

        // A blotter with no crime type is just an empty shell — skip it.
        if ($id === 0 || $date === null || $type === '') {
            return null;
        }

        $location    = trim((string) ($row['location'] ?? ''));
        $complainant = trim((string) ($row['complainant_name'] ?? ''));
        $status      = $this->mapStatus($rawStatus);

        return [
            'source'      => 'blotter',
            'external_id' => $id,
            'code'        => $reference !== '' ? mb_substr($reference, 0, 50) : 'AR-BLT-' . $id,
            'reference'   => $reference !== '' ? $reference : null,
            'draft'       => mb_strtolower($rawStatus) === 'draft' || $reference === '',
            'type'        => $type,
            'category'    => $this->categoryFor($type),
            'title'       => $this->buildTitle($type, $location),
            'description' => $complainant !== '' ? 'Blotter entry filed by ' . $complainant . '.' : null,
            'date'        => $date,
            'time'        => $this->cleanTime(substr($createdAt, 11, 8)),
            'location'    => $location !== '' ? $location : null,
            'street_hint' => $this->matchStreet($location),
            'lat'         => null,
            'lng'         => null,
            'status'      => $status,
            'raw_status'  => $rawStatus ?: null,
            'clearance'   => $status === 'solved' ? 'cleared' : 'uncleared',
            'urgency'     => null,
            'high_risk'   => false,
            'reporter'    => $complainant !== '' ? $complainant : null,
            'victim'      => null,
            'suspect'     => null,
            'victims'     => 0,
            'suspects'    => 0,
            'officer'     => null,
            'modus'       => null,
            'reported_at' => $createdAt ?: $date,
        ];
    }

    private function buildTitle(string $type, string $location): string
    {
        $type  = $type !== '' ? $type : 'Reported incident';
        $title = $location !== '' ? $type . ' at ' . $location : $type;

        return mb_substr($title, 0, 255);
    }

    /**
     * Reports crime type => a category this system already uses. Falls back to
     * an exact match against the categories table, then to the raw type, so an
     * unrecognised type is never silently relabelled as something else.
     */
    public function categoryFor(string $rawType): string
    {
        $key = mb_strtolower(trim($rawType));
        if ($key === '') {
            return 'Other';
        }

        if (isset(self::CATEGORY_SYNONYMS[$key])) {
            return self::CATEGORY_SYNONYMS[$key];
        }

        foreach ($this->categoryNames() as $lower => $name) {
            if ($lower === $key) {
                return $name;
            }
        }

        return mb_substr(trim($rawType), 0, 100);
    }

    /** ['theft' => 'Theft', ...] from the categories table (cached) */
    public function categoryNames(): array
    {
        return Cache::remember('alertara_category_names_v1', 600, function () {
            try {
                return CrimeCategory::pluck('category_name')
                    ->filter()
                    ->mapWithKeys(fn ($name) => [mb_strtolower(trim($name)) => trim($name)])
                    ->all();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    private function mapStatus($status): string
    {
        return self::STATUS_MAP[mb_strtolower(trim((string) $status))] ?? 'reported';
    }

    /** 'Y-m-d' or null — the feed uses '0000-00-00' for "not set" */
    private function cleanDate($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return null;
        }

        try {
            $date = \Carbon\Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }

        return $date->year > 1970 ? $date->format('Y-m-d') : null;
    }

    /** 'H:i:s' or null */
    private function cleanTime($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || ! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $m)) {
            return null;
        }

        if ((int) $m[1] > 23 || (int) $m[2] > 59) {
            return null;
        }

        return sprintf('%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
    }

    private function cleanCoordinate($value, float $min, float $max): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return ($value >= $min && $value <= $max && $value !== 0.0) ? $value : null;
    }

    // ------------------------------------------------------------- streets

    /** Street names available for placing an imported record, alphabetical */
    public function streets(): array
    {
        $names = array_column($this->streetIndex(), 'name');
        sort($names, SORT_NATURAL | SORT_FLAG_CASE);

        return $names;
    }

    public function hasStreet(string $name): bool
    {
        return isset($this->streetIndex()[mb_strtolower(trim($name))]);
    }

    /**
     * A point that lies on the named street's polyline. $seed (the record code)
     * decides where along the street the point lands, so the same record always
     * imports to the same spot and two records rarely stack on one pixel.
     */
    public function pointOnStreet(string $name, string $seed): ?array
    {
        $points = $this->streetIndex()[mb_strtolower(trim($name))]['points'] ?? [];
        $count  = count($points);

        if ($count === 0) {
            return null;
        }

        if ($count === 1) {
            return ['lat' => $points[0][0], 'lng' => $points[0][1]];
        }

        $hash    = crc32($seed);
        $segment = $hash % ($count - 1);
        $ratio   = (($hash >> 8) % 1000) / 1000;

        [$latA, $lngA] = $points[$segment];
        [$latB, $lngB] = $points[$segment + 1];

        return [
            'lat' => round($latA + ($latB - $latA) * $ratio, 8),
            'lng' => round($lngA + ($lngB - $lngA) * $ratio, 8),
        ];
    }

    /** The first street name mentioned in a free-text location, if any */
    public function matchStreet(?string $text): ?string
    {
        $text = mb_strtolower(trim((string) $text));
        if ($text === '') {
            return null;
        }

        foreach ($this->streetIndex() as $lower => $street) {
            if (str_contains($text, $lower)) {
                return $street['name'];
            }
        }

        return null;
    }

    /** ['acacia street' => ['name' => 'Acacia Street', 'points' => [[lat, lng], ...]]] */
    private function streetIndex(): array
    {
        return Cache::remember(self::STREETS_CACHE, 21600, function () {
            $path = public_path('data/san_agustin_streets.geojson');
            if (! is_file($path)) {
                return [];
            }

            $geo   = json_decode((string) file_get_contents($path), true);
            $index = [];

            foreach (($geo['features'] ?? []) as $feature) {
                $name = trim((string) ($feature['properties']['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $key = mb_strtolower($name);
                $index[$key]['name'] = $name;

                foreach (($feature['geometry']['coordinates'] ?? []) as $coord) {
                    // GeoJSON stores [lng, lat]
                    if (isset($coord[0], $coord[1]) && is_numeric($coord[0]) && is_numeric($coord[1])) {
                        $index[$key]['points'][] = [(float) $coord[1], (float) $coord[0]];
                    }
                }
            }

            return array_filter($index, fn ($street) => ! empty($street['points']));
        });
    }
}
