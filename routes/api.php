<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CrimeIncidentController;
use App\Http\Controllers\DataDecryptionController;
use App\Http\Controllers\Api\MobileUserController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public API for crime heatmap (rate limited)
Route::get('/crime-heatmap', [LandingController::class, 'getCrimeData'])
    ->middleware('throttle:60,1')
    ->name('api.crime-heatmap');


// Public API for submitting anonymous tips (rate limited)
Route::post('/submit-tip', [LandingController::class, 'submitTipApi'])
    ->middleware('throttle:5,1')
    ->name('api.submit-tip');

// Crime categories and barangays endpoints (for filters)
Route::get('/crime-categories', function() {
    return \App\Models\CrimeCategory::select('id', 'category_name', 'color_code', 'icon')->get();
})->middleware('throttle:60,1');

Route::get('/barangays', function() {
    return \App\Models\Barangay::select('id', 'barangay_name')->get();
})->middleware('throttle:60,1');

// Official Quezon City barangay list (142) with PSGC codes.
// Use this for QC-scoped filters — /barangays returns every barangay on file,
// including ones outside QC such as Addition Hills.
// Read straight from the boundary file so the list and the polygons can never
// drift apart, and there is no second file to go missing.
Route::get('/qc-barangays', function() {
    $path = public_path('qc_barangays.geojson');

    if (!is_file($path)) {
        return response()->json(['message' => 'QC barangay boundary file not found'], 404);
    }

    $geojson = json_decode(file_get_contents($path), true);

    $barangays = collect($geojson['features'] ?? [])
        ->map(fn ($f) => [
            'code' => $f['properties']['code'] ?? null,
            'name' => $f['properties']['name'] ?? null,
        ])
        ->filter(fn ($b) => $b['code'] && $b['name'])
        ->sortBy('name')
        ->values();

    return response()->json($barangays);
})->middleware('throttle:60,1');

// Crimes data endpoint for crime page — served from the San Agustin table.
// That table has no persons_involved relation, so those fields come back empty.
Route::get('/crimes', function() {
    // category_name/barangay_name are plain text there; the page still expects
    // nested category/barangay objects, so rebuild them by name.
    $categoryByName = \App\Models\CrimeCategory::select('id', 'category_name', 'color_code', 'icon')
        ->get()
        ->keyBy(fn ($c) => mb_strtolower(trim($c->category_name)));

    $crimes = \App\Models\SanAgustinIncident::with('evidence')
        ->orderBy('created_at', 'desc')
        ->get();

    $crimesWithRelations = $crimes->map(function ($crime) use ($categoryByName) {
        $category = $categoryByName->get(mb_strtolower(trim($crime->category_name)));

        // Format evidence with encrypted fields marked
        $evidenceData = $crime->evidence->map(function ($evidence) {
            return [
                'id' => $evidence->evidence_id,
                'evidence_type' => $evidence->evidence_type,
                'description' => '[ENCRYPTED]',
                'evidence_link' => '[ENCRYPTED]',
            ];
        })->toArray();

        $evidenceTypes = $crime->evidence->pluck('evidence_type')->unique()->values()->toArray();

        return [
            'id' => $crime->id,
            'incident_code' => $crime->incident_code,
            'incident_title' => $crime->incident_title,
            'incident_description' => $crime->incident_description,
            'incident_date' => optional($crime->incident_date)->format('Y-m-d'),
            'incident_time' => $crime->incident_time,
            'record_type' => $crime->record_type,
            'status' => $crime->status,
            'clearance_status' => $crime->clearance_status,
            'latitude' => $crime->latitude,
            'longitude' => $crime->longitude,
            'address_details' => $crime->address_details,
            'victim_count' => $crime->victim_count,
            'suspect_count' => $crime->suspect_count,
            'modus_operandi' => $crime->modus_operandi,
            'weather_condition' => $crime->weather_condition,
            'assigned_officer' => $crime->assigned_officer,
            'category' => [
                'id' => $category->id ?? null,
                'category_name' => $crime->category_name ?: 'Unknown',
                'color_code' => $category->color_code ?? '#274d4c',
                'icon' => $category->icon ?? 'fa-exclamation-circle',
            ],
            'barangay' => [
                'id' => null,
                'barangay_name' => $crime->barangay_name,
            ],
            // No persons_involved table for San Agustin records yet
            'persons_involved_count' => 0,
            'persons_involved_types' => [],
            'persons_involved' => [],
            'evidence_count' => $crime->evidence->count(),
            'evidence_types' => $evidenceTypes,
            'evidence' => $evidenceData,
        ];
    });

    $categories = \App\Models\CrimeCategory::select('id', 'category_name', 'color_code', 'icon')->get();

    // Only the barangays actually present in the data, so the page's barangay
    // filter cannot offer options that would always return nothing.
    $barangays = $crimes->pluck('barangay_name')
        ->filter()
        ->unique()
        ->sort()
        ->values()
        ->map(fn ($name, $i) => ['id' => $i + 1, 'barangay_name' => $name]);

    return response()->json([
        'incidents' => $crimesWithRelations,
        'categories' => $categories,
        'barangays' => $barangays
    ]);
})->middleware('throttle:60,1');

// Total crime statistics endpoint (unfiltered)
// Totals for the map stat cards, served from the San Agustin incident table.
// record_type splits criminal offences from non-criminal incidents.
Route::get('/crime-stats', function() {
    $rows = \App\Models\SanAgustinIncident::query()
        ->get(['record_type', 'clearance_status', 'category_name']);

    return response()->json([
        'total_crime'    => $rows->where('record_type', 'crime')->count(),
        'total_incident' => $rows->where('record_type', 'incident')->count(),
        'total'          => $rows->count(),
        'cleared'        => $rows->where('clearance_status', 'cleared')->count(),
        'uncleared'      => $rows->where('clearance_status', 'uncleared')->count(),
        'categories'     => $rows->pluck('category_name')->filter()->unique()->count(),
    ]);
})->middleware('throttle:60,1');

// Crime hotspot data endpoint
Route::get('/crime-hotspots', [DashboardController::class, 'getHotspotData'])
    ->middleware('throttle:60,1')
    ->name('api.crime-hotspots');

// Trend-based hotspot forecast endpoint
Route::get('/crime-hotspot-forecast', [DashboardController::class, 'getHotspotForecast'])
    ->middleware('throttle:30,1')
    ->name('api.crime-hotspot-forecast');

// Pattern detection data endpoint
Route::get('/pattern-detection', [DashboardController::class, 'getPatternData'])
    ->middleware('throttle:60,1')
    ->name('api.pattern-detection');

// Mobile User API endpoints
Route::post('/mobile-users/register', [MobileUserController::class, 'register'])
    ->middleware('throttle:5,1')
    ->name('api.mobile-users.register');

Route::post('/mobile-users/login', [MobileUserController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('api.mobile-users.login');

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
