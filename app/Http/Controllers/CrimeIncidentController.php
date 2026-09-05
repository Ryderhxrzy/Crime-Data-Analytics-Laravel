<?php

namespace App\Http\Controllers;

use App\Models\CrimeIncident;
use App\Models\SanAgustinIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use App\Models\PersonsInvolved;
use App\Models\Evidence;
use App\Services\EncryptionService;
use App\Services\AuditLogService;
use App\Services\ExternalCrimeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CrimeIncidentController extends Controller
{
    public function index()
    {
        // Include relationships: category, barangay, persons involved, and evidence
        $crimes = CrimeIncident::with(['category', 'barangay', 'personsInvolved', 'evidence'])
            ->orderBy('id', 'desc')
            ->get();

        // Check if request expects JSON (API request)
        if (request()->expectsJson()) {
            // For API responses, include count and types of related data without exposing encrypted details
            $response = $crimes->map(function ($crime) {
                // Debug logging
                \Log::info('📊 Processing crime incident', [
                    'id' => $crime->id,
                    'code' => $crime->incident_code,
                    'persons_count' => $crime->personsInvolved->count(),
                    'evidence_count' => $crime->evidence->count(),
                ]);

                // Get unique person types from the relationship
                $personTypesCollection = $crime->personsInvolved->pluck('person_type')->unique();
                $personTypes = $personTypesCollection->values()->toArray();

                // Get unique evidence types from the relationship
                $evidenceTypesCollection = $crime->evidence->pluck('evidence_type')->unique();
                $evidenceTypes = $evidenceTypesCollection->values()->toArray();

                \Log::info('📝 Extracted types', [
                    'person_types' => $personTypes,
                    'evidence_types' => $evidenceTypes,
                ]);

                return [
                    'id' => $crime->id,
                    'incident_code' => $crime->incident_code,
                    'incident_title' => $crime->incident_title,
                    'incident_date' => $crime->incident_date,
                    'status' => $crime->status,
                    'clearance_status' => $crime->clearance_status,
                    'latitude' => $crime->latitude,
                    'longitude' => $crime->longitude,
                    'category' => $crime->category,
                    'barangay' => $crime->barangay,
                    'persons_involved_count' => $crime->personsInvolved->count(),
                    'persons_involved_types' => $personTypes,
                    'evidence_count' => $crime->evidence->count(),
                    'evidence_types' => $evidenceTypes,
                ];
            });

            \Log::info('✅ API Response prepared', ['total_crimes' => $crimes->count()]);
            return response()->json($response);
        }

        return view('crimes.index', compact('crimes'));
    }

    /**
     * Manual crime entry. The form is built around Barangay San Agustin: the
     * street list comes from the same geojson the maps draw, with the streets
     * that already carry records ("active" streets) listed first, and the map
     * marker stays on the chosen street unless free placement is switched on.
     */
    public function create(ExternalCrimeReportService $streetService)
    {
        $categories = $this->categoryOptions();
        $barangays = $this->barangayOptions();

        $sanAgustin = $barangays->first(fn ($b) => mb_strtolower(trim($b['name'])) === 'san agustin');

        $activeCounts = $this->activeStreetCounts();
        $allStreets = $streetService->streets();

        $active = [];
        $others = [];
        foreach ($allStreets as $name) {
            $count = $activeCounts[mb_strtolower($name)] ?? 0;
            if ($count > 0) {
                $active[] = ['name' => $name, 'count' => $count];
            } else {
                $others[] = ['name' => $name, 'count' => 0];
            }
        }
        usort($active, fn ($a, $b) => $b['count'] <=> $a['count'] ?: strnatcasecmp($a['name'], $b['name']));

        return view('crime-incident-create', [
            'categories'      => $categories,
            'barangays'       => $barangays,
            'defaultBarangay' => $sanAgustin['id'] ?? null,
            'activeStreets'   => $active,
            'otherStreets'    => $others,
            'statuses'        => ['reported', 'under_investigation', 'solved', 'closed', 'archived'],
            'weather'         => ['Clear', 'Cloudy', 'Rainy', 'Stormy', 'Foggy', 'Unknown'],
            'recentManual'    => $this->recentManualEntries(),
        ]);
    }

    public function store(Request $request, ExternalCrimeReportService $streetService)
    {
        $validated = $request->validate([
            'incident_title'       => 'required|string|max:255',
            'incident_description' => 'required|string|max:5000',
            'crime_category_id'    => 'required|integer',
            'barangay_id'          => 'required|integer',
            'street'               => 'required|string|max:150',
            'incident_date'        => 'required|date|before_or_equal:today',
            'incident_time'        => 'required|date_format:H:i',
            'latitude'             => 'required|numeric|between:-90,90',
            'longitude'            => 'required|numeric|between:-180,180',
            'placement'            => 'nullable|in:street,free',
            'victim_count'         => 'nullable|integer|min:0|max:999',
            'suspect_count'        => 'nullable|integer|min:0|max:999',
            'modus_operandi'       => 'nullable|string|max:2000',
            'weather_condition'    => 'nullable|string|max:50',
            'assigned_officer'     => 'nullable|string|max:150',
            'status'               => 'required|in:reported,under_investigation,solved,closed,archived',
            'clearance_status'     => 'required|in:cleared,uncleared',
            'clearance_date'       => 'nullable|date',
        ]);

        $category = $this->categoryOptions()->firstWhere('id', (int) $validated['crime_category_id']);
        $barangay = $this->barangayOptions()->firstWhere('id', (int) $validated['barangay_id']);

        if (!$category) {
            return back()->withErrors(['crime_category_id' => 'Please choose a valid crime category.'])->withInput();
        }
        if (!$barangay) {
            return back()->withErrors(['barangay_id' => 'Please choose a valid barangay.'])->withInput();
        }

        $street = trim($validated['street']);
        $isSanAgustin = mb_strtolower(trim($barangay['name'])) === 'san agustin';

        // Inside San Agustin the street must be one the maps know, otherwise
        // the record can never be grouped with the others on that street.
        if ($isSanAgustin && !$streetService->hasStreet($street)) {
            return back()->withErrors(['street' => 'Please pick a street from the San Agustin list.'])->withInput();
        }

        $lat = round((float) $validated['latitude'], 8);
        $lng = round((float) $validated['longitude'], 8);

        // Default placement keeps the pin on the street: snap whatever the
        // browser sent to the nearest point of that street's polyline.
        if ($isSanAgustin && ($validated['placement'] ?? 'street') === 'street') {
            $snapped = $this->snapToStreet($streetService, $street, $lat, $lng);
            if ($snapped) {
                [$lat, $lng] = $snapped;
            }
        }

        $data = [
            'incident_code'        => $this->manualIncidentCode(),
            'record_type'          => 'crime',
            'category_name'        => $category['name'],
            'crime_category_id'    => $category['id'],
            'barangay_name'        => $barangay['name'],
            'barangay_id'          => $barangay['id'],
            'incident_title'       => trim($validated['incident_title']),
            'incident_description' => trim($validated['incident_description']),
            'incident_date'        => $validated['incident_date'],
            'incident_time'        => $validated['incident_time'] . ':00',
            'latitude'             => $lat,
            'longitude'            => $lng,
            'address_details'      => $street . ', ' . $barangay['name'] . ', Quezon City',
            'victim_count'         => (int) ($validated['victim_count'] ?? 0),
            'suspect_count'        => (int) ($validated['suspect_count'] ?? 0),
            'status'               => $validated['status'],
            'clearance_status'     => $validated['clearance_status'],
            'clearance_date'       => $validated['clearance_status'] === 'cleared' ? ($validated['clearance_date'] ?? now()->toDateString()) : null,
            'modus_operandi'       => $validated['modus_operandi'] ?? null,
            'weather_condition'    => $validated['weather_condition'] ?? null,
            'reported_by'          => currentAccount()['id'] ?? null,
            'assigned_officer'     => $validated['assigned_officer'] ?? null,
        ];

        try {
            // Same table as CrimeIncident, but without its created() hook: that
            // hook broadcasts over Pusher, and a broadcast failure must not turn
            // a saved record into a 500 for the person typing it in.
            $incident = SanAgustinIncident::create($data);
        } catch (\Throwable $e) {
            Log::error('Manual crime entry failed', ['error' => $e->getMessage()]);

            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Could not save the crime record: ' . $e->getMessage()], 500);
            }

            return back()->with('error', 'Could not save the crime record: ' . $e->getMessage())->withInput();
        }

        // Re-check the alert rules against the new record (what the model hook
        // would have done), but never let it break the save.
        try {
            $asCrime = CrimeIncident::find($incident->id);
            if ($asCrime) {
                app(\App\Services\CrimeAlertEngine::class)->evaluateForIncident($asCrime);
            }
        } catch (\Throwable $e) {
            Log::warning('Alert evaluation after manual entry failed: ' . $e->getMessage());
        }

        try {
            AuditLogService::logIncidentInsert($incident->id, [
                'incident_code' => $incident->incident_code,
                'incident_title' => $incident->incident_title,
                'category' => $category['name'],
                'street' => $street,
                'entry' => 'manual',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not audit manual crime entry: ' . $e->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success'        => true,
                'message'        => 'Crime record saved.',
                'incident_id'    => $incident->id,
                'incident_code'  => $incident->incident_code,
                'incident_title' => $incident->incident_title,
                'latitude'       => $incident->latitude,
                'longitude'      => $incident->longitude,
            ]);
        }

        return redirect()->route('crime-incident.create')
            ->with('success', "Crime record {$incident->incident_code} saved on {$street}. It now appears on Crime Mapping and in every analysis.")
            ->with('saved_incident', [
                'code' => $incident->incident_code,
                'title' => $incident->incident_title,
                'street' => $street,
                'category' => $category['name'],
            ]);
    }

    /** [['id' => 1, 'name' => 'Theft', 'color' => '#FFA500'], ...] — crime categories only */
    private function categoryOptions()
    {
        try {
            $rows = CrimeCategory::query()
                ->where(function ($q) {
                    $q->whereNull('source_system')->orWhere('source_system', 'law_enforcement');
                })
                ->orderBy('category_name')
                ->get(['id', 'category_name', 'color_code', 'severity_level']);

            if ($rows->isNotEmpty()) {
                return $rows->map(fn ($c) => [
                    'id'       => (int) $c->id,
                    'name'     => $c->category_name,
                    'color'    => $c->color_code ?: '#6b7280',
                    'severity' => $c->severity_level,
                ])->values();
            }
        } catch (\Throwable $e) {
            Log::warning('Category table unavailable, falling back to incident names: ' . $e->getMessage());
        }

        // Fallback: whatever names the incident table already uses
        try {
            return CrimeIncident::query()
                ->select('category_name', 'crime_category_id')
                ->whereNotNull('crime_category_id')
                ->groupBy('category_name', 'crime_category_id')
                ->orderBy('category_name')
                ->get()
                ->map(fn ($c) => ['id' => (int) $c->crime_category_id, 'name' => $c->category_name, 'color' => '#6b7280', 'severity' => null])
                ->values();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** [['id' => 5, 'name' => 'San Agustin'], ...] */
    private function barangayOptions()
    {
        try {
            return Barangay::orderBy('barangay_name')
                ->get(['id', 'barangay_name'])
                ->map(fn ($b) => ['id' => (int) $b->id, 'name' => $b->barangay_name])
                ->values();
        } catch (\Throwable $e) {
            return collect([['id' => 0, 'name' => 'San Agustin']]);
        }
    }

    /** ['acacia street' => 12, ...] — crimes already recorded per street */
    private function activeStreetCounts(): array
    {
        $counts = [];

        try {
            CrimeIncident::query()
                ->where('record_type', 'crime')
                ->whereNotNull('address_details')
                ->pluck('address_details')
                ->each(function ($address) use (&$counts) {
                    $street = trim(explode(',', (string) $address)[0] ?? '');
                    if ($street === '' || str_starts_with($street, 'Purok')) {
                        return;
                    }
                    $key = mb_strtolower($street);
                    $counts[$key] = ($counts[$key] ?? 0) + 1;
                });
        } catch (\Throwable $e) {
            Log::warning('Could not count active streets: ' . $e->getMessage());
        }

        return $counts;
    }

    /** The last few manually entered records, for the side panel */
    private function recentManualEntries()
    {
        try {
            return CrimeIncident::query()
                ->where('incident_code', 'like', 'MAN-%')
                ->orderByDesc('id')
                ->limit(6)
                ->get(['incident_code', 'incident_title', 'category_name', 'address_details', 'incident_date', 'created_at']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    private function manualIncidentCode(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = 'MAN-' . now()->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            try {
                if (!CrimeIncident::where('incident_code', $code)->exists()) {
                    return $code;
                }
            } catch (\Throwable $e) {
                return $code;
            }
        }

        return 'MAN-' . now()->format('YmdHis');
    }

    /**
     * Nearest point on the street's polyline to (lat, lng). Longitude is scaled
     * by cos(latitude) so distances are compared in roughly equal units.
     */
    private function snapToStreet(ExternalCrimeReportService $service, string $street, float $lat, float $lng): ?array
    {
        $points = $service->streetPoints($street);
        if (count($points) === 0) {
            return null;
        }
        if (count($points) === 1) {
            return [$points[0][0], $points[0][1]];
        }

        $kx = cos(deg2rad($lat));
        $best = null;
        $bestDist = INF;

        for ($i = 0; $i < count($points) - 1; $i++) {
            [$aLat, $aLng] = $points[$i];
            [$bLat, $bLng] = $points[$i + 1];

            $ax = $aLng * $kx; $ay = $aLat;
            $bx = $bLng * $kx; $by = $bLat;
            $px = $lng * $kx;  $py = $lat;

            $dx = $bx - $ax; $dy = $by - $ay;
            $len2 = $dx * $dx + $dy * $dy;
            $t = $len2 > 0 ? max(0, min(1, (($px - $ax) * $dx + ($py - $ay) * $dy) / $len2)) : 0;

            $cx = $ax + $t * $dx; $cy = $ay + $t * $dy;
            $d = ($px - $cx) ** 2 + ($py - $cy) ** 2;

            if ($d < $bestDist) {
                $bestDist = $d;
                $best = [round($cy, 8), round($cx / $kx, 8)];
            }
        }

        return $best;
    }

    /**
     * Get incident details with persons involved and evidence
     * Returns sensitive data marked as "ENCRYPTED" to show as blurred in UI
     */
    public function getDetails($id)
    {
        try {
            $incident = CrimeIncident::with(['category', 'barangay', 'personsInvolved', 'evidence'])
                ->findOrFail($id);

            // Format persons involved with encrypted fields marked
            $personsData = $incident->personsInvolved?->map(function ($person) {
                return [
                    'id' => $person->person_id,
                    'person_type' => $person->person_type,
                    'first_name' => '[ENCRYPTED]', // Mark as encrypted for frontend to blur
                    'middle_name' => '[ENCRYPTED]',
                    'last_name' => '[ENCRYPTED]',
                    'contact_number' => '[ENCRYPTED]',
                    'other_info' => '[ENCRYPTED]',
                ];
            })->toArray() ?? [];

            // Format evidence with encrypted fields marked
            $evidenceData = $incident->evidence?->map(function ($evidence) {
                return [
                    'id' => $evidence->evidence_id,
                    'evidence_type' => $evidence->evidence_type,
                    'description' => '[ENCRYPTED]', // Mark as encrypted for frontend to blur
                    'evidence_link' => '[ENCRYPTED]',
                ];
            })->toArray() ?? [];

            return response()->json([
                'success' => true,
                'incident' => [
                    'id' => $incident->id,
                    'incident_code' => $incident->incident_code,
                    'incident_title' => $incident->incident_title,
                    'incident_date' => $incident->incident_date,
                    'status' => $incident->status,
                    'clearance_status' => $incident->clearance_status,
                    'category' => $incident->category,
                    'barangay' => $incident->barangay,
                ],
                'persons_involved' => $personsData,
                'evidence' => $evidenceData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Incident not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Generate Cloudinary signed upload signature
     * Used for secure file uploads with signature validation
     */
    public function generateCloudinarySignature(Request $request)
    {
        $timestamp = time();
        $preset = env('CLOUDINARY_UPLOAD_PRESET_INTERNAL', 'internal');
        $folder = 'crime-evidence/internal';
        $apiKey = env('CLOUDINARY_KEY');
        $secret = env('CLOUDINARY_SECRET');

        // Build signature parameters in alphabetical order (Cloudinary requirement)
        // NOTE: api_key is NOT included in signature (it's public)
        $params = [
            'folder' => $folder,
            'timestamp' => $timestamp,
            'upload_preset' => $preset,
        ];

        // Sort by key alphabetically
        ksort($params);

        // Build string to sign: key1=value1&key2=value2&...&secret
        $toSign = '';
        foreach ($params as $key => $value) {
            $toSign .= "{$key}={$value}&";
        }
        $toSign = rtrim($toSign, '&') . $secret;

        // Generate SHA256 hash
        $signature = hash('sha256', $toSign);

        Log::info('[Cloudinary] Signature generated', [
            'timestamp' => $timestamp,
            'folder' => $folder,
            'preset' => $preset,
            'string_to_sign' => rtrim(implode('&', array_map(fn($k, $v) => "$k=$v", array_keys($params), $params)), '&'),
            'signature' => $signature,
        ]);

        return response()->json([
            'success' => true,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'upload_preset' => $preset,
            'folder' => $folder,
            'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
            'api_key' => $apiKey
        ]);
    }

    /**
     * Log when a crime incident is viewed
     *
     * @param int $id The crime incident ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function logView($id)
    {
        try {
            // Verify the incident exists
            $incident = CrimeIncident::findOrFail($id);

            // Log the view action
            AuditLogService::logIncidentView($id, [
                'incident_code' => $incident->incident_code,
                'incident_title' => $incident->incident_title,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'View logged successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error logging incident view', [
                'incident_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to log view'
            ], 500);
        }
    }
}
