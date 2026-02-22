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

// Crimes data endpoint for crime page
Route::get('/crimes', function() {
    $crimes = \App\Models\CrimeIncident::with(['category', 'barangay', 'personsInvolved', 'evidence'])
        ->orderBy('created_at', 'desc')
        ->get();

    // Add persons and evidence data to each crime
    $crimesWithRelations = $crimes->map(function ($crime) {
        // Format persons involved with encrypted fields marked
        $personsData = $crime->personsInvolved->map(function ($person) {
            return [
                'id' => $person->person_id,
                'person_type' => $person->person_type,
                'first_name' => '[ENCRYPTED]',
                'middle_name' => '[ENCRYPTED]',
                'last_name' => '[ENCRYPTED]',
                'contact_number' => '[ENCRYPTED]',
                'other_info' => '[ENCRYPTED]',
            ];
        })->toArray();

        // Format evidence with encrypted fields marked
        $evidenceData = $crime->evidence->map(function ($evidence) {
            return [
                'id' => $evidence->evidence_id,
                'evidence_type' => $evidence->evidence_type,
                'description' => '[ENCRYPTED]',
                'evidence_link' => '[ENCRYPTED]',
            ];
        })->toArray();

        // Get unique person types
        $personTypes = $crime->personsInvolved?->pluck('person_type')->unique()->values()->toArray() ?? [];
        // Get unique evidence types
        $evidenceTypes = $crime->evidence?->pluck('evidence_type')->unique()->values()->toArray() ?? [];

        return [
            'id' => $crime->id,
            'incident_code' => $crime->incident_code,
            'incident_title' => $crime->incident_title,
            'incident_description' => $crime->incident_description,
            'incident_date' => $crime->incident_date,
            'incident_time' => $crime->incident_time,
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
            'category' => $crime->category,
            'barangay' => $crime->barangay,
            'persons_involved_count' => $crime->personsInvolved->count(),
            'persons_involved_types' => $personTypes,
            'persons_involved' => $personsData,
            'evidence_count' => $crime->evidence->count(),
            'evidence_types' => $evidenceTypes,
            'evidence' => $evidenceData,
        ];
    });

    $categories = \App\Models\CrimeCategory::select('id', 'category_name', 'color_code', 'icon')->get();
    $barangays = \App\Models\Barangay::select('id', 'barangay_name')->get();

    return response()->json([
        'incidents' => $crimesWithRelations,
        'categories' => $categories,
        'barangays' => $barangays
    ]);
})->middleware('throttle:60,1');

// Total crime statistics endpoint (unfiltered)
Route::get('/crime-stats', function() {
    $totalIncidents = \App\Models\CrimeIncident::count();
    $clearedCases = \App\Models\CrimeIncident::where('clearance_status', 'cleared')->count();
    $unclearedCases = \App\Models\CrimeIncident::where('clearance_status', 'uncleared')->count();
    $totalCategories = \App\Models\CrimeCategory::count();
    
    return response()->json([
        'total' => $totalIncidents,
        'cleared' => $clearedCases,
        'uncleared' => $unclearedCases,
        'categories' => $totalCategories
    ]);
})->middleware('throttle:60,1');

// Crime hotspot data endpoint
Route::get('/crime-hotspots', [DashboardController::class, 'getHotspotData'])
    ->middleware('throttle:60,1')
    ->name('api.crime-hotspots');

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
