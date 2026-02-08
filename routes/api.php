<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingController;

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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
