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
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\AlertsController;

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

// Authenticated routes with JWT API middleware
Route::middleware('jwt.api')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/charts', [DashboardController::class, 'getChartData'])->name('dashboard.charts');
    Route::get('/time-based-trends', [DashboardController::class, 'timeBasedTrends'])->name('time-based-trends');
    Route::get('/location-trends', [DashboardController::class, 'locationTrends'])->name('location-trends');
    Route::get('/crime-type-trends', [DashboardController::class, 'crimeTypeTrends'])->name('crime-type-trends');
    Route::post('/dashboard/location-charts', [DashboardController::class, 'getLocationChartData'])->name('dashboard.location.charts');
    Route::get('/dashboard/location-trends', [DashboardController::class, 'locationTrends'])->name('dashboard.location.trends');
    Route::get('/mapping', [LandingController::class, 'mapping'])->name('mapping');
    Route::get('/crime-hotspot', [DashboardController::class, 'crimeHotspot'])->name('crime-hotspot');
    Route::get('/risk-forecasting', [DashboardController::class, 'riskForecasting'])->name('risk-forecasting');
    Route::get('/pattern-detection', [DashboardController::class, 'patternDetection'])->name('pattern-detection');
    Route::get('/realtime-test', [DashboardController::class, 'realtimeTest'])->name('realtime-test');
    Route::get('/crimes', [CrimeIncidentController::class, 'index'])->name('crimes.index');
    Route::get('/crime-incident/create', [CrimeIncidentController::class, 'create'])->name('crime-incident.create');
    Route::post('/crime-incident', [CrimeIncidentController::class, 'store'])->name('crime-incident.store');
    Route::get('/api/crime-incident/{id}', [LandingController::class, 'getIncidentDetails'])->name('api.crime-incident');

    // Reports routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('/create', [ReportsController::class, 'create'])->name('create');
        Route::post('/', [ReportsController::class, 'store'])->name('store');
        Route::get('/{id}', [ReportsController::class, 'show'])->name('show');
        Route::get('/{id}/download', [ReportsController::class, 'download'])->name('download');
    });

    // Alerts routes
    Route::prefix('alerts')->name('alerts.')->group(function () {
        Route::get('/active', [AlertsController::class, 'activeAlerts'])->name('active');
        Route::get('/history', [AlertsController::class, 'history'])->name('history');
        Route::get('/settings', [AlertsController::class, 'settings'])->name('settings');
        Route::post('/settings', [AlertsController::class, 'updateSettings'])->name('updateSettings');
    });
});

// DEBUG: Session & JWT token check (remove in production)
Route::get('/debug/session', function () {
    return response()->json([
        'page' => 'dashboard_debug',
        'session_id' => session()->getId(),
        'jwt_token_in_session' => session('jwt_token') ? 'YES ✓' : 'NO ✗',
        'token_preview' => session('jwt_token') ? substr(session('jwt_token'), 0, 50) . '...' : 'No token',
        'auth_user' => session('auth_user') ? 'YES ✓ - ' . (session('auth_user.email') ?? 'No email') : 'NO ✗',
        'app_env' => app()->environment(),
        'session_data_keys' => array_keys(session()->all()),
    ]);
});

// DEBUG: Check session on mapping page
Route::get('/debug/mapping', function () {
    return response()->json([
        'page' => 'mapping_debug',
        'session_id' => session()->getId(),
        'jwt_token_in_session' => session('jwt_token') ? 'YES ✓' : 'NO ✗',
        'token_preview' => session('jwt_token') ? substr(session('jwt_token'), 0, 50) . '...' : 'No token',
        'auth_user' => session('auth_user') ? 'YES ✓ - ' . (session('auth_user.email') ?? 'No email') : 'NO ✗',
        'app_env' => app()->environment(),
        'session_data_keys' => array_keys(session()->all()),
    ]);
});
