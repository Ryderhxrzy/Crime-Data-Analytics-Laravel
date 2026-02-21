<?php

namespace App\Http\Controllers;

use App\Models\CrimeIncident;
use App\Models\CrimeCategory;
use App\Models\Barangay;
use App\Models\PersonsInvolved;
use App\Models\Evidence;
use App\Services\EncryptionService;
use App\Services\AuditLogService;
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

    public function create()
    {
        $categories = CrimeCategory::all();
        $barangays = Barangay::all();

        return view('crime-incident-create', compact('categories', 'barangays'));
    }

    public function store(Request $request)
    {
        try {
            \Log::info('🔍 store() method called', ['request_type' => $request->expectsJson() ? 'AJAX/JSON' : 'FORM']);

            try {
            $validated = $request->validate([
                'incident_title' => 'required|string|max:255',
                'incident_description' => 'required|string',
                'crime_category_id' => 'required|exists:crime_department_crime_categories,id',
                'barangay_id' => 'required|exists:crime_department_barangays,id',
                'incident_date' => 'required|date',
                'incident_time' => 'required|date_format:H:i',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'address_details' => 'nullable|string|max:500',
                'victim_count' => 'nullable|integer|min:0',
                'suspect_count' => 'nullable|integer|min:0',
                'modus_operandi' => 'nullable|string',
                'weather_condition' => 'nullable|string',
                'assigned_officer' => 'nullable|string',
                'clearance_date' => 'nullable|date',
                'status' => 'required|in:reported,under_investigation,solved,closed,archived',
                'clearance_status' => 'required|in:cleared,uncleared',
                'persons_involved' => 'nullable|array',
                'evidence_items' => 'nullable|array',
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Validation failed in store()', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Validation error: ' . $e->getMessage()], 422);
        }

        // Auto-generate incident code
        $validated['incident_code'] = 'INC-' . date('YmdHis') . '-' . rand(100, 999);

        \Log::info('📝 Creating crime incident', [
            'incident_title' => $validated['incident_title'],
            'category_id' => $validated['crime_category_id'],
            'barangay_id' => $validated['barangay_id'],
            'coords' => $validated['latitude'] . ',' . $validated['longitude']
        ]);

        try {
            // Create the incident
            $incident = CrimeIncident::create($validated);
        } catch (\Exception $e) {
            \Log::error('❌ Error creating incident', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Error creating incident: ' . $e->getMessage()], 500);
        }

        \Log::info('✅ Crime incident created successfully', [
            'incident_id' => $incident->id,
            'incident_code' => $incident->incident_code,
            'incident_title' => $incident->incident_title,
            'latitude' => $incident->latitude,
            'longitude' => $incident->longitude,
            'category_id' => $incident->crime_category_id,
            'barangay_id' => $incident->barangay_id,
            'broadcast_ready' => true
        ]);

        // Log incident creation to audit (wrapped in try-catch to ensure JSON response)
        try {
            AuditLogService::logIncidentInsert($incident->id, [
                'incident_code' => $incident->incident_code,
                'incident_title' => $incident->incident_title,
                'category_id' => $incident->crime_category_id,
            ]);
        } catch (\Exception $e) {
            \Log::error('❌ Error logging incident to audit', ['error' => $e->getMessage()]);
            // Continue anyway - don't fail the request
        }

        // Process persons involved
        try {
            $personsData = $request->input('persons_involved', []);
            if (!empty($personsData)) {
                foreach ($personsData as $person) {
                    $personRecord = PersonsInvolved::create([
                        'incident_id' => $incident->id,
                        'person_type' => $person['person_type'],
                        'first_name' => $person['first_name'] ?? null,
                        'middle_name' => $person['middle_name'] ?? null,
                        'last_name' => $person['last_name'] ?? null,
                        'contact_number' => $person['contact_number'] ?? null,
                        'other_info' => $person['other_info'] ?? null,
                    ]);

                    // Log person creation (wrapped in try-catch to prevent failures)
                    try {
                        AuditLogService::logPersonInsert($personRecord->person_id, $incident->id, [
                            'person_type' => $person['person_type'],
                            'incident_id' => $incident->id,
                        ]);
                    } catch (\Exception $logError) {
                        \Log::error('❌ Error logging person', ['error' => $logError->getMessage()]);
                    }

                    \Log::info('👤 Person involved added', [
                        'person_id' => $personRecord->person_id,
                        'person_type' => $person['person_type'],
                        'incident_id' => $incident->id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('❌ Error processing persons involved', [
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Error adding persons: ' . $e->getMessage()], 500);
        }

        // Process evidence
        try {
            $evidenceData = $request->input('evidence_items', []);
            if (!empty($evidenceData)) {
                foreach ($evidenceData as $evidence) {
                    $evidenceRecord = Evidence::create([
                        'incident_id' => $incident->id,
                        'evidence_type' => $evidence['evidence_type'],
                        'description' => $evidence['description'] ?? null,
                        'evidence_link' => $evidence['evidence_link'] ?? null,
                    ]);

                    // Log evidence creation (wrapped in try-catch to prevent failures)
                    try {
                        AuditLogService::logEvidenceInsert($evidenceRecord->evidence_id, $incident->id, [
                            'evidence_type' => $evidence['evidence_type'],
                            'incident_id' => $incident->id,
                        ]);
                    } catch (\Exception $logError) {
                        \Log::error('❌ Error logging evidence', ['error' => $logError->getMessage()]);
                    }

                    \Log::info('📦 Evidence added', [
                        'evidence_id' => $evidenceRecord->evidence_id,
                        'evidence_type' => $evidence['evidence_type'],
                        'incident_id' => $incident->id,
                    ]);
                }
            }
        } catch (\Exception $e) {
            \Log::error('❌ Error processing evidence', [
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['success' => false, 'message' => 'Error adding evidence: ' . $e->getMessage()], 500);
        }

        // Reload incident with relationships
        try {
            $incident->load(['category', 'barangay', 'personsInvolved', 'evidence']);
        } catch (\Exception $e) {
            \Log::error('❌ Error reloading incident', [
                'incident_id' => $incident->id,
                'error' => $e->getMessage()
            ]);
            // Continue anyway - incident is already created and saved
        }

        // NOTE: Do NOT broadcast here - the model's booted() hook already handles broadcasting
        // Explicit broadcast here would cause duplicate events

        // Check if request is AJAX (from modal)
        if ($request->expectsJson()) {
            \Log::info('✅ Returning JSON response', [
                'incident_id' => $incident->id,
                'incident_code' => $incident->incident_code
            ]);
            return response()->json([
                'success' => true,
                'message' => 'Crime incident created successfully!',
                'incident_id' => $incident->id,
                'incident_code' => $incident->incident_code,
                'incident_title' => $incident->incident_title,
                'latitude' => $incident->latitude,
                'longitude' => $incident->longitude
            ]);
        }

        // Regular form submission - redirect back
        \Log::info('✅ Returning redirect response');
        return redirect()->back()->with('success', 'Crime incident created successfully! Check the mapping page for real-time update.');
        } catch (\Exception $e) {
            \Log::error('❌ FATAL ERROR in store()', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()], 500);
        }
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
}
