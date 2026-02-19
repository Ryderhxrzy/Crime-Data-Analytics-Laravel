<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\DashboardController;
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
    $crimes = \App\Models\CrimeIncident::with(['category', 'barangay'])
        ->orderBy('created_at', 'desc')
        ->get();
    
    $categories = \App\Models\CrimeCategory::select('id', 'category_name', 'color_code', 'icon')->get();
    $barangays = \App\Models\Barangay::select('id', 'barangay_name')->get();
    
    return response()->json([
        'incidents' => $crimes,
        'categories' => $categories,
        'barangays' => $barangays
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
