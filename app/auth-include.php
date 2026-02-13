<?php
/**
 * JWT Authentication Include File for Laravel
 *
 * Include this at the top of any dashboard Blade view to validate JWT tokens
 * from the centralized login system.
 *
 * Usage in your dashboard view:
 * <?php require_once app_path('path/to/auth-include.php'); ?>
 *
 * Then use these helper functions:
 * - getCurrentUser()      // Returns array with user data
 * - getUserEmail()        // Returns user email
 * - getUserRole()         // Returns 'admin' or 'super_admin'
 * - getUserDepartment()   // Returns department code
 * - getDepartmentName()   // Returns human-readable department name
 * - isSuperAdmin()        // Boolean check
 * - isAdmin()             // Boolean check
 */

// Get JWT secret and main domain from environment
$jwtSecret = env('JWT_SECRET');
$mainDomain = env('MAIN_DOMAIN', 'https://alertaraqc.com');

if (!$jwtSecret) {
    abort(500, 'JWT_SECRET not configured in environment');
}

// Ensure session is started
if (!session()->isStarted()) {
    session()->start();
}

// Debug logging
$debugLog = [];
$debugLog[] = '=== JWT AUTHENTICATION DEBUG ===';
$debugLog[] = 'Current URL: ' . request()->fullUrl();
$debugLog[] = 'Current Time: ' . now()->format('Y-m-d H:i:s');
$debugLog[] = 'Session started: YES';

// Step 1: Get JWT token from multiple sources
$token = null;
$user = null;

// Try URL query parameter first (initial redirect from login)
if (request()->query('token')) {
    $token = request()->query('token');
    $debugLog[] = '✓ Token found in URL (?token parameter)';
    $debugLog[] = 'Token Preview: ' . substr($token, 0, 50) . '...';

    // Store in session for subsequent requests
    session(['jwt_token' => $token]);
    session()->save(); // Explicitly save the session
    $debugLog[] = '✓ Token stored in session';
    $debugLog[] = 'Session ID: ' . session()->getId();
    $debugLog[] = 'Session contents: ' . json_encode(session()->all());
} else {
    // Try to get from session for subsequent requests
    $token = session('jwt_token');
    if ($token) {
        $debugLog[] = '✓ Token retrieved from session';
        $debugLog[] = 'Token Preview: ' . substr($token, 0, 50) . '...';
    } else {
        $debugLog[] = '✗ No token found in URL or session';
    }
}

// Step 2: Try Authorization header as fallback
if (!$token) {
    $authHeader = request()->header('Authorization');
    if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
        $token = str_replace('Bearer ', '', $authHeader);
        $debugLog[] = '✓ Token found in Authorization header';
        $debugLog[] = 'Token Preview: ' . substr($token, 0, 50) . '...';
    } else {
        $debugLog[] = '✗ No Authorization header found';
    }
}

// Step 3: Validate JWT token using Laravel's JWTAuth
$user = null;

if ($token) {
    $debugLog[] = '🔐 Validating JWT token using Laravel JWTAuth...';

    try {
        // Use tymon/jwt-auth to validate and authenticate user
        $authUser = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();

        if ($authUser) {
            // Get payload (contains custom claims: department, role, email from getJWTCustomClaims())
            $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();

            // Extract from JWT payload - not from $authUser object
            $user = [
                'sub' => $payload->get('sub') ?? $authUser->id,
                'id' => $authUser->id,
                'email' => $payload->get('email') ?? $authUser->email,
                'department' => $payload->get('department') ?? '',
                'role' => $payload->get('role') ?? 'admin',
                'iat' => $payload->get('iat') ?? time(),
                'exp' => $payload->get('exp') ?? (time() + 3600)
            ];

            $debugLog[] = '✓ JWT token validated successfully!';
            $debugLog[] = 'User ID: ' . ($user['id'] ?? 'N/A');
            $debugLog[] = 'User Email: ' . ($user['email'] ?? 'N/A');
            $debugLog[] = 'Department: ' . ($user['department'] ?? 'N/A');
            $debugLog[] = 'Role: ' . ($user['role'] ?? 'N/A');
            $debugLog[] = 'Expires: ' . date('Y-m-d H:i:s', $user['exp'] ?? time());

        } else {
            $debugLog[] = '✗ JWT token validation FAILED!';
            $debugLog[] = 'Error: Token invalid or expired';
        }

    } catch (\Exception $e) {
        $debugLog[] = '✗ JWT token validation FAILED!';
        $debugLog[] = 'Error: ' . $e->getMessage();

        \Log::error('JWT Validation Error: ' . $e->getMessage(), [
            'token' => substr($token, 0, 50) . '...',
            'error' => $e->getMessage()
        ]);
    }
} else {
    $debugLog[] = '✗ No token available for validation';
}

// Log debug information
foreach ($debugLog as $log) {
    \Log::debug($log);
}

// Step 4: Redirect if not authenticated
if (!$user) {
    $debugLog[] = '';
    $debugLog[] = '❌ AUTHENTICATION FAILED - REDIRECTING';
    $debugLog[] = 'Redirect URL: ' . $mainDomain;
    $debugLog[] = '===================================';

    foreach ($debugLog as $log) {
        \Log::debug($log);
    }

    return redirect($mainDomain);
}

// Step 5: Check token expiration
if ($user['exp'] && $user['exp'] < time()) {
    \Log::warning('JWT token expired', [
        'email' => $user['email'],
        'expired_at' => date('Y-m-d H:i:s', $user['exp'])
    ]);

    session()->forget('jwt_token');
    return redirect($mainDomain);
}

// Step 6: Authentication successful - log it
$debugLog[] = '';
$debugLog[] = '✅ AUTHENTICATION SUCCESSFUL';
$debugLog[] = '===================================';

foreach ($debugLog as $log) {
    \Log::debug($log);
}

// Step 7: Make user data globally available
$GLOBALS['authenticated_user'] = $user;

// Department name mapping
$departmentNames = [
    'law_enforcement_department' => 'Law Enforcement Department',
    'traffic_and_transport_department' => 'Traffic & Transport Department',
    'fire_and_rescue_department' => 'Fire & Rescue Department',
    'emergency_response_department' => 'Emergency Response Department',
    'community_policing_department' => 'Community Policing Department',
    'crime_data_department' => 'Crime Data Analytics Department',
    'public_safety_department' => 'Public Safety Department',
    'health_and_safety_department' => 'Health & Safety Department',
    'disaster_preparedness_department' => 'Disaster Preparedness Department',
    'emergency_communication_department' => 'Emergency Communication Department',
];

$departmentName = $departmentNames[$user['department']] ?? ucfirst(str_replace('_', ' ', $user['department']));

// Step 8: Helper Functions

/**
 * Get current authenticated user
 */
function getCurrentUser()
{
    return $GLOBALS['authenticated_user'] ?? null;
}

/**
 * Get user role
 */
function getUserRole()
{
    return $GLOBALS['authenticated_user']['role'] ?? 'guest';
}

/**
 * Get user email
 */
function getUserEmail()
{
    return $GLOBALS['authenticated_user']['email'] ?? '';
}

/**
 * Get user department code
 */
function getUserDepartment()
{
    return $GLOBALS['authenticated_user']['department'] ?? '';
}

/**
 * Get human-readable department name
 */
function getDepartmentName()
{
    static $names = [
        'law_enforcement_department' => 'Law Enforcement Department',
        'traffic_and_transport_department' => 'Traffic & Transport Department',
        'fire_and_rescue_department' => 'Fire & Rescue Department',
        'emergency_response_department' => 'Emergency Response Department',
        'community_policing_department' => 'Community Policing Department',
        'crime_data_department' => 'Crime Data Analytics Department',
        'public_safety_department' => 'Public Safety Department',
        'health_and_safety_department' => 'Health & Safety Department',
        'disaster_preparedness_department' => 'Disaster Preparedness Department',
        'emergency_communication_department' => 'Emergency Communication Department',
    ];

    $dept = getUserDepartment();
    return $names[$dept] ?? ucfirst(str_replace('_', ' ', $dept));
}

/**
 * Check if user is super admin
 */
function isSuperAdmin()
{
    return getUserRole() === 'super_admin';
}

/**
 * Check if user is admin
 */
function isAdmin()
{
    return getUserRole() === 'admin';
}

/**
 * Get logout URL
 */
function getLogoutUrl()
{
    return env('MAIN_DOMAIN', 'https://alertaraqc.com') . '/logout';
}

/**
 * Get JavaScript token refresh script
 */
function getTokenRefreshScript()
{
    $user = getCurrentUser();
    $exp = $user['exp'] ?? 0;
    $mainDomain = env('MAIN_DOMAIN', 'https://alertaraqc.com');

    return "
    <script>
        // Token expiration check
        const tokenExpiresAt = " . ($exp * 1000) . ";

        const checkTokenExpiration = () => {
            if (Date.now() >= tokenExpiresAt) {
                alert('Your session has expired. Please login again.');
                window.location.href = '{$mainDomain}';
            }
        };

        // Check every minute
        setInterval(checkTokenExpiration, 60000);
        checkTokenExpiration();

        // Store user data in localStorage
        const userData = " . json_encode($user) . ";
        localStorage.setItem('user_data', JSON.stringify(userData));
    </script>";
}

/**
 * Get customizable redirect URL based on role and department
 * Override this function in your dashboard to customize redirect behavior
 *
 * USAGE IN YOUR DASHBOARD VIEW:
 *
 * Option 1 - Auto redirect after authentication:
 * <?php
 *     require_once app_path('path/to/auth-include.php');
 *     redirect(getRedirectUrl())->send();
 * ?>
 *
 * Option 2 - Custom redirect logic:
 * <?php
 *     require_once app_path('path/to/auth-include.php');
 *     $redirectUrl = getRedirectUrl();
 *     if (isSuperAdmin()) {
 *         $redirectUrl = route('super-admin.dashboard');
 *     }
 *     redirect($redirectUrl)->send();
 * ?>
 *
 * Option 3 - Stay on current page (default):
 * <?php
 *     require_once app_path('path/to/auth-include.php');
 *     // Just use the page normally, no redirect
 * ?>
 */
function getRedirectUrl()
{
    $user = getCurrentUser();

    if (!$user) {
        return env('MAIN_DOMAIN', 'https://alertaraqc.com');
    }

    // DEFAULT: Return current page (no redirect)
    // CUSTOMIZE BY UNCOMMENTING EXAMPLES BELOW

    // Example 1: Redirect super admin
    // if (isSuperAdmin()) {
    //     return route('super-admin.dashboard');
    // }

    // Example 2: Redirect by department
    // $departmentRoutes = [
    //     'crime_data_department' => route('crime-analytics.dashboard'),
    //     'law_enforcement_department' => route('law-enforcement.dashboard'),
    // ];
    // return $departmentRoutes[getUserDepartment()] ?? request()->url();

    // Example 3: Redirect by role
    // if (isAdmin()) {
    //     return route('admin.dashboard');
    // }

    // For now, stay on current page
    return request()->url();
}

/**
 * Handle logout action
 */
function logout()
{
    session()->forget('jwt_token');
    return redirect('https://login.alertaraqc.com');
}

// Auto logout if requested via query parameter
if (request()->input('action') === 'logout') {
    return logout();
}

?>
