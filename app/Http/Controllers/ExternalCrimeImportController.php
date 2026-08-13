<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\CrimeCategory;
use App\Models\SanAgustinIncident;
use App\Services\AuditLogService;
use App\Services\ExternalCrimeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Import from Reports" on the crime mapping page.
 *
 * Lists what the Alertara Reports system (report.alertaraqc.com) currently
 * holds, marks what we already have, and inserts the rows the user ticked into
 * the San Agustin incident table the map reads from.
 *
 * The browser only ever posts a record code plus the placement choices; every
 * value that lands in the database is re-read from the Reports feed here.
 */
class ExternalCrimeImportController extends Controller
{
    public function __construct(private ExternalCrimeReportService $reports)
    {
    }

    /**
     * GET — what is available to import. `available` is 0 when the feed is
     * empty or unreachable, which is what the modal uses to stay table-less.
     */
    public function index(Request $request)
    {
        $records = $this->reports->records($request->boolean('refresh'));

        if (empty($records)) {
            return response()->json([
                'success'   => true,
                'records'   => [],
                'streets'   => $this->reports->streets(),
                'total'     => 0,
                'available' => 0,
                'imported'  => 0,
                'message'   => 'The Reports system returned no crime data right now.',
            ]);
        }

        $existing = SanAgustinIncident::whereIn('incident_code', array_column($records, 'code'))
            ->pluck('incident_code')
            ->flip();

        $imported = 0;
        foreach ($records as &$record) {
            $record['already_imported'] = $existing->has($record['code']);
            $imported += $record['already_imported'] ? 1 : 0;
        }
        unset($record);

        return response()->json([
            'success'    => true,
            'records'    => $records,
            'streets'    => $this->reports->streets(),
            'categories' => array_values($this->reports->categoryNames()),
            'total'      => count($records),
            'available'  => count($records) - $imported,
            'imported'   => $imported,
        ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * POST — insert the selected records. Rows already in the table are
     * skipped rather than duplicated, and a row that cannot be placed on the
     * map is reported back instead of being inserted without coordinates.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'items'            => 'required|array|min:1|max:200',
            'items.*.code'     => 'required|string|max:50',
            'items.*.street'   => 'nullable|string|max:120',
            'items.*.category' => 'nullable|string|max:100',
        ]);

        $records = $this->reports->recordsByCode();
        if (empty($records)) {
            return response()->json([
                'success' => false,
                'error'   => 'The Reports system is not returning data right now. Try again in a moment.',
            ], 503);
        }

        $codes    = array_values(array_unique(array_column($validated['items'], 'code')));
        $existing = SanAgustinIncident::whereIn('incident_code', $codes)->pluck('incident_code')->flip();

        $barangayId = Barangay::whereRaw('LOWER(TRIM(barangay_name)) = ?', ['san agustin'])->value('id');
        $categoryIds = CrimeCategory::pluck('id', 'category_name')
            ->mapWithKeys(fn ($id, $name) => [mb_strtolower(trim($name)) => $id])
            ->all();

        $inserted = [];
        $skipped  = [];
        $failed   = [];
        $seen     = [];

        try {
            DB::transaction(function () use (
                $validated, $records, $existing, $barangayId, $categoryIds,
                &$inserted, &$skipped, &$failed, &$seen
            ) {
                foreach ($validated['items'] as $item) {
                    $code = trim($item['code']);

                    if (isset($seen[$code])) {
                        continue;
                    }
                    $seen[$code] = true;

                    $record = $records[$code] ?? null;
                    if (! $record) {
                        $failed[] = ['code' => $code, 'reason' => 'No longer present in the Reports feed.'];
                        continue;
                    }

                    if ($existing->has($code)) {
                        $skipped[] = $code;
                        continue;
                    }

                    // Coordinates: the feed's own point when it has one, otherwise
                    // a point on the street the user picked for this row.
                    $lat    = $record['lat'];
                    $lng    = $record['lng'];
                    $street = trim((string) ($item['street'] ?? ''));

                    if ($lat === null || $lng === null) {
                        if ($street === '') {
                            $failed[] = ['code' => $code, 'reason' => 'No coordinates in the report — pick a street for it.'];
                            continue;
                        }

                        $point = $this->reports->pointOnStreet($street, $code);
                        if (! $point) {
                            $failed[] = ['code' => $code, 'reason' => 'Unknown street "' . $street . '".'];
                            continue;
                        }

                        $lat = $point['lat'];
                        $lng = $point['lng'];
                    }

                    $category = trim((string) ($item['category'] ?? '')) ?: $record['category'];
                    $incident = SanAgustinIncident::create([
                        'incident_code'        => $code,
                        'record_type'          => 'crime',
                        'category_name'        => mb_substr($category, 0, 100),
                        'barangay_name'        => 'San Agustin',
                        'crime_category_id'    => $categoryIds[mb_strtolower($category)] ?? null,
                        'barangay_id'          => $barangayId,
                        'incident_title'       => $record['title'],
                        'incident_description' => $this->buildDescription($record),
                        'incident_date'        => $record['date'],
                        'incident_time'        => $record['time'],
                        'latitude'             => $lat,
                        'longitude'            => $lng,
                        'address_details'      => $this->buildAddress($street, $record['location']),
                        'victim_count'         => $record['victims'],
                        'suspect_count'        => $record['suspects'],
                        'status'               => $record['status'],
                        'clearance_status'     => $record['clearance'],
                        'clearance_date'       => $record['clearance'] === 'cleared' ? $record['date'] : null,
                        'modus_operandi'       => $record['modus'],
                        'assigned_officer'     => $record['officer'],
                    ]);

                    $inserted[] = $code;

                    AuditLogService::log(
                        'IMPORT_EXTERNAL_INCIDENT',
                        'crime_department_san_agustin_incidents',
                        $incident->id,
                        [
                            'source'        => 'report.alertaraqc.com',
                            'source_type'   => $record['source'],
                            'external_code' => $code,
                            'street'        => $street ?: null,
                            'category'      => $category,
                        ]
                    );
                }
            });
        } catch (\Throwable $e) {
            Log::error('Reports import failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => 'The import could not be saved. Nothing was inserted.',
            ], 500);
        }

        return response()->json([
            'success'  => true,
            'inserted' => count($inserted),
            'skipped'  => count($skipped),
            'failed'   => $failed,
            'codes'    => $inserted,
        ]);
    }

    /** Narrative plus the context the feed carries that our columns have no home for */
    private function buildDescription(array $record): ?string
    {
        $parts = [];

        if ($record['description']) {
            $parts[] = $record['description'];
        }

        $notes = array_filter([
            $record['victim'] ? 'Victim: ' . $record['victim'] : null,
            $record['suspect'] ? 'Suspect: ' . $record['suspect'] : null,
            $record['reporter'] ? 'Reported by: ' . $record['reporter'] : null,
            $record['urgency'] ? 'Urgency: ' . $record['urgency'] : null,
            $record['high_risk'] ? 'Flagged high risk' : null,
            $record['raw_status'] ? 'Reports status: ' . $record['raw_status'] : null,
        ]);

        if ($notes) {
            $parts[] = implode(' · ', $notes);
        }

        $parts[] = 'Imported from the Alertara Reports system (' . $record['source'] . ' ' . $record['code'] . ').';

        return implode("\n\n", $parts);
    }

    /**
     * Street first — the crime-data report page and the street modal both read
     * the street off the front of this field.
     */
    private function buildAddress(string $street, ?string $reportedAs): string
    {
        $address = ($street !== '' ? $street . ', ' : '') . 'San Agustin, Quezon City';

        if ($reportedAs) {
            $address .= ' (reported as: ' . $reportedAs . ')';
        }

        return $address;
    }
}
