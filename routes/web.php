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
use App\Http\Controllers\DataDecryptionController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\AlertsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ExternalCrimeImportController;

// Public landing page
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Public tip submission
Route::post('/submit-tip', [LandingController::class, 'submitTip'])->name('submit-tip');

// Public JSON API: saved AI reports (San Agustin) — intentionally outside the
// jwt.api group so it can be consumed by external services / dashboards.
Route::get('/saved-ai-reports/data', [DashboardController::class, 'savedAiReportsData'])
    ->name('saved-ai-reports.data');

// Public JSON API: active crime alerts — intentionally outside the jwt.api group
// so it can be consumed by external services / dashboards. The payload is
// aggregate alert data only (no reporter or victim details).
Route::get('/alerts/api/active-data', [AlertsController::class, 'activeData'])
    ->name('alerts.active-data');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Password-based login handled by this app in every environment — no redirect
// to the centralized portal. The ?token= JWT flow in ValidateJWTViaAPI still
// works if a token is passed, but is no longer the entry point.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/unlock-account/{token}', [AuthController::class, 'unlockAccount'])->name('unlock-account');

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
    Route::get('/dashboard/location-trends-data', [DashboardController::class, 'getLocationTrendsData'])->name('dashboard.location.trends-data');
    Route::get('/dashboard/crime-type-trends-data', [DashboardController::class, 'getCrimeTypeTrendsData'])->name('dashboard.crime-type.trends-data');
    Route::get('/dashboard/location-trends', [DashboardController::class, 'locationTrends'])->name('dashboard.location.trends');
    Route::get('/mapping', [LandingController::class, 'mapping'])->name('mapping');

    // Crime mapping > Import from the Alertara Reports system
    Route::get('/mapping/external-crimes', [ExternalCrimeImportController::class, 'index'])
        ->middleware('throttle:30,1')
        ->name('mapping.external-crimes');
    Route::post('/mapping/external-crimes/import', [ExternalCrimeImportController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('mapping.external-crimes.import');

    Route::get('/crime-hotspot', [DashboardController::class, 'crimeHotspot'])->name('crime-hotspot');
    Route::get('/risk-forecasting', [DashboardController::class, 'riskForecasting'])->name('risk-forecasting');
    Route::get('/pattern-detection', [DashboardController::class, 'patternDetection'])->name('pattern-detection');
    Route::post('/pattern-detection/simulate', [DashboardController::class, 'simulatePatterns'])->name('pattern-detection.simulate');
    Route::get('/pattern-detection/analyze', [DashboardController::class, 'detectPatterns'])->name('pattern-detection.analyze');
    Route::get('/pattern-detection/ai-analyze', [DashboardController::class, 'aiPatternAnalysis'])
        ->middleware('throttle:5,1')
        ->name('pattern-detection.ai-analyze');
    Route::post('/pattern-detection/ai-simulate', [DashboardController::class, 'aiSimulateAnalysis'])
        ->middleware('throttle:5,1')
        ->name('pattern-detection.ai-simulate');
    Route::get('/pattern-detection/street-stats', [DashboardController::class, 'sanAgustinStreetStats'])
        ->name('pattern-detection.street-stats');
    Route::get('/pattern-detection/street-detail', [DashboardController::class, 'sanAgustinStreetDetail'])
        ->name('pattern-detection.street-detail');
    // Default engine is the instant rule-based one, so the throttle is roomy;
    // the Gemini fallback (?ai=1) stays inside it too.
    Route::get('/pattern-detection/street-ai-suggest', [DashboardController::class, 'sanAgustinStreetAiSuggest'])
        ->middleware('throttle:30,1')
        ->name('pattern-detection.street-ai-suggest');
    Route::post('/pattern-detection/ai-save', [DashboardController::class, 'saveAiAnalysis'])
        ->name('pattern-detection.ai-save');
    Route::get('/pattern-detection/ai-reports', [DashboardController::class, 'listAiReports'])
        ->name('pattern-detection.ai-reports');
    Route::get('/saved-ai-reports', [DashboardController::class, 'savedAiReports'])
        ->name('saved-ai-reports');
    Route::get('/crimes', [CrimeIncidentController::class, 'index'])->name('crimes.index');
    Route::get('/crime-incident/create', [CrimeIncidentController::class, 'create'])->name('crime-incident.create');
    Route::post('/crime-incident', [CrimeIncidentController::class, 'store'])->name('crime-incident.store');
    Route::post('/api/crimes/{id}/log-view', [CrimeIncidentController::class, 'logView'])->name('api.crimes.log-view');
    Route::get('/api/crime-incident/{id}', [LandingController::class, 'getIncidentDetails'])->name('api.crime-incident.details');
    Route::get('/api/crime-incident/{id}/details', [CrimeIncidentController::class, 'getDetails'])->name('api.crime-incident.full-details');
    Route::get('/api/crime-incident', [CrimeIncidentController::class, 'index'])->name('api.crime-incident');
    Route::post('/api/cloudinary-signature', [CrimeIncidentController::class, 'generateCloudinarySignature'])->name('cloudinary.signature');

    // Data Decryption routes (OTP-based)
    Route::post('/decrypt-data/send-otp', [DataDecryptionController::class, 'sendOtp'])
        ->middleware('throttle:3,1')
        ->name('decrypt.send-otp');

    Route::post('/decrypt-data/verify-otp', [DataDecryptionController::class, 'verifyOtp'])
        ->middleware('throttle:60,1')
        ->name('decrypt.verify-otp');

    Route::get('/decrypt-data/status', [DataDecryptionController::class, 'checkDecryptionStatus'])
        ->middleware('throttle:60,1')
        ->name('decrypt.status');

    Route::post('/decrypt-data/invalidate', [DataDecryptionController::class, 'invalidateOtp'])
        ->middleware('throttle:10,1')
        ->name('decrypt.invalidate');

    Route::get('/decrypt-data/get-cached/{incident_id}', [DataDecryptionController::class, 'getCachedData'])
        ->middleware('throttle:60,1')
        ->name('decrypt.get-cached');

    // Audit Logs routes
    Route::prefix('audit-logs')->name('audit-logs.')->group(function () {
        Route::get('/', [AuditLogController::class, 'index'])->name('index');
        Route::get('/filtered', [AuditLogController::class, 'getFiltered'])->name('filtered');
    });

    // Reports routes
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('/create', [ReportsController::class, 'create'])->name('create');
        Route::post('/', [ReportsController::class, 'store'])->name('store');

        // Crime Data page — registered BEFORE the /{id} wildcard
        Route::get('/crime-data', [ReportsController::class, 'crimeData'])->name('crime-data');
        Route::get('/crime-data/list', [ReportsController::class, 'crimeDataList'])->name('crime-data.list');
        Route::post('/crime-data/save', [ReportsController::class, 'storeCrimeReport'])->name('crime-data.save');
        Route::get('/crime-data/saved', [ReportsController::class, 'listCrimeReports'])->name('crime-data.saved');
        Route::delete('/crime-data/saved/{id}', [ReportsController::class, 'deleteCrimeReport'])->name('crime-data.delete');

        Route::get('/{id}', [ReportsController::class, 'show'])->name('show');
        Route::get('/{id}/download', [ReportsController::class, 'download'])->name('download');
    });

    // Alerts routes
    Route::prefix('alerts')->name('alerts.')->group(function () {
        Route::get('/active', [AlertsController::class, 'activeAlerts'])->name('active');
        Route::get('/history', [AlertsController::class, 'history'])->name('history');
        Route::get('/settings', [AlertsController::class, 'settings'])->name('settings');
        Route::post('/settings', [AlertsController::class, 'updateSettings'])->name('updateSettings');
        Route::get('/management', [AlertsController::class, 'management'])->name('management');
        Route::post('/create-rule', [AlertsController::class, 'createRule'])->name('create-rule');
        Route::post('/update-rule/{id}', [AlertsController::class, 'updateRule'])->name('update-rule');
        Route::delete('/delete-rule/{id}', [AlertsController::class, 'deleteRule'])->name('delete-rule');

        // Alert data API (consumed client-side by alerts-active/alerts-history views).
        // Note: 'alerts.active-data' is registered publicly above, outside this group.
        Route::get('/api/history-data', [AlertsController::class, 'historyData'])->name('history-data');
        Route::post('/api/evaluate', [AlertsController::class, 'evaluate'])->name('evaluate');
        Route::post('/api/preview-rule', [AlertsController::class, 'previewRule'])->name('preview-rule');

        // Alert workflow. The parameter is the alert_code (ALT-2026-001), not an id.
        Route::post('/api/{code}/acknowledge', [AlertsController::class, 'acknowledge'])->name('acknowledge');
        Route::post('/api/{code}/resolve', [AlertsController::class, 'resolve'])->name('resolve');
        Route::post('/api/{code}/dismiss', [AlertsController::class, 'dismiss'])->name('dismiss');
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
