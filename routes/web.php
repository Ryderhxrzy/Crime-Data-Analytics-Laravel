<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CrimeIncidentController;
use App\Http\Controllers\LandingController;

// Public landing page
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Public tip submission
Route::post('/submit-tip', [LandingController::class, 'submitTip'])->name('submit-tip');

// Authentication routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::get('/unlock-account/{token}', [AuthController::class, 'unlockAccount'])->name('unlock-account');

// OTP verification routes
Route::get('/verify-otp', [AuthController::class, 'showVerifyOtp'])->name('verify.otp.show');
Route::post('/verify-otp', [AuthController::class, 'verifyOtp'])->name('verify.otp');
Route::post('/otp/resend', [AuthController::class, 'resendOtp'])->name('otp.resend');

// Google login routes
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle'])->name('login.google');
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])->name('login.google.callback');

// Dashboard routes with conditional authentication
Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/dashboard/charts', [DashboardController::class, 'getChartData'])->name('dashboard.charts');
Route::get('/time-based-trends', [DashboardController::class, 'timeBasedTrends'])->name('time-based-trends');
Route::get('/location-trends', [DashboardController::class, 'locationTrends'])->name('location-trends');
Route::get('/crime-type-trends', [DashboardController::class, 'crimeTypeTrends'])->name('crime-type-trends');
Route::post('/dashboard/location-charts', [DashboardController::class, 'getLocationChartData'])->name('dashboard.location.charts');
Route::get('/dashboard/location-trends', [DashboardController::class, 'locationTrends'])->name('dashboard.location.trends');
// Authenticated mapping page (for users logging in via centralized system)
Route::get('/mapping', [LandingController::class, 'mapping'])->name('mapping');
// Note: Authentication is handled via JWT token in session, checked in auth-include.php
Route::get('/crime-hotspot', [DashboardController::class, 'crimeHotspot'])->name('crime-hotspot');
Route::get('/risk-forecasting', [DashboardController::class, 'riskForecasting'])->name('risk-forecasting');
Route::get('/pattern-detection', [DashboardController::class, 'patternDetection'])->name('pattern-detection');
Route::get('/crimes', [CrimeIncidentController::class, 'index'])->name('crimes.index');

// Note: Token authentication now handled automatically in all authenticated views via query parameter
// Example: /dashboard?token=xyz or /mapping?token=xyz
// No special route needed - all views capture token via: @php session(['jwt_token' => request()->query('token')]) @endphp

// Incident details endpoint (authenticated)
Route::get('/api/crime-incident/{id}', [LandingController::class, 'getIncidentDetails'])->middleware('auth')->name('api.crime-incident');
