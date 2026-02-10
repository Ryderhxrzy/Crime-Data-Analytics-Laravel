<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;
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
