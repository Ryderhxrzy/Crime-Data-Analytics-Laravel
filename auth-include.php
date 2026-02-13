<?php
// Authentication Include File
// Include this at the top of any dashboard file to handle JWT authentication
// Usage: require_once 'auth-include.php';

// Step 1: Check environment first
$environment = app()->environment() ?? env('APP_ENV', 'local');

// Skip JWT authentication in local environment
if ($environment === 'local') {
    // In local environment, use Laravel's built-in authentication
    // Don't override the existing auth system
    return;
}

// Step 2: Detect environment (Laravel vs Pure PHP) - Only for production
$isLaravel = false;
$jwtSecret = null;
$mainDomain = 'https://alertaraqc.com';

if (defined('LARAVEL_START') || function_exists('app') || class_exists('Illuminate\Foundation\Application')) {
    $isLaravel = true;
    try {
        if (function_exists('config')) {
            $jwtSecret = config('jwt.secret');
        }
        if (!$jwtSecret && env('JWT_SECRET')) {
            $jwtSecret = env('JWT_SECRET');
        }
        $mainDomain = env('MAIN_DOMAIN', 'https://alertaraqc.com');
    } catch (Exception $e) {
        $jwtSecret = env('JWT_SECRET', 'fallback-laravel-secret');
    }
} else {
    $isLaravel = false;
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '#') === 0) continue;
            if (strpos($line, '=') === false) continue;
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
            $_ENV[trim($key)] = trim($value);
        }
    }
    $jwtSecret = $_ENV['JWT_SECRET'] ?? 'fallback-php-secret';
    $mainDomain = $_ENV['MAIN_DOMAIN'] ?? 'https://alertaraqc.com';
}

// Define constants
define('IS_LARAVEL', $isLaravel);
define('JWT_SECRET', $jwtSecret);
define('MAIN_DOMAIN', $mainDomain);

// Step 3: JWT Validation Function
function validateJWT($token) {
    try {
        if (IS_LARAVEL) {
            if (class_exists('Tymon\JWTAuth\Facades\JWTAuth')) {
                $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
                return $payload->toArray();
            } elseif (function_exists('auth')) {
                $user = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->authenticate();
                return $user ? [
                    'sub' => $user->id,
                    'email' => $user->email,
                    'department' => $user->department ?? '',
                    'role' => $user->role ?? '',
                    'iat' => time(),
                    'exp' => time() + 3600
                ] : null;
            }
        } else {
            if (!class_exists('Firebase\JWT\JWT')) {
                require_once 'vendor/autoload.php';
            }
            $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key(JWT_SECRET, 'HS256'));
            return (array) $decoded;
        }
    } catch (Exception $e) {
        error_log("JWT Validation Error: " . $e->getMessage());
        return null;
    }
    return null;
}

// Step 4: Get Token
$token = null;
$user = null;

// From URL (initial redirect)
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if (!IS_LARAVEL) {
        session_start();
        $_SESSION['jwt_token'] = $token;
    } else {
        session(['jwt_token' => $token]);
    }
} elseif (IS_LARAVEL) {
    $token = session('jwt_token');
} else {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    $token = $_SESSION['jwt_token'] ?? null;
}

// From Authorization header
if (!$token) {
    $headers = getallheaders() ?? [];
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    }
}

// Step 5: Validate Token
if ($token) {
    $user = validateJWT($token);
}

// Step 6: Redirect if not authenticated
if (!$user) {
    $redirectUrl = MAIN_DOMAIN;
    if (IS_LARAVEL) {
        return redirect($redirectUrl);
    } else {
        header('Location: ' . $redirectUrl);
        exit();
    }
}

// Step 7: Extract User Data (Available globally)
$userId = $user['sub'] ?? null;
$userEmail = $user['email'] ?? '';
$userDepartment = $user['department'] ?? '';
$userRole = $user['role'] ?? '';
$iat = $user['iat'] ?? 0;
$exp = $user['exp'] ?? 0;

// Step 8: Check Expiration
if ($exp && $exp < time()) {
    if (IS_LARAVEL) {
        session()->forget('jwt_token');
        return redirect(MAIN_DOMAIN);
    } else {
        session_destroy();
        header('Location: ' . MAIN_DOMAIN);
        exit();
    }
}

// Step 9: Get Department Info
$currentSubdomain = explode('.', $_SERVER['HTTP_HOST'])[0] ?? 'unknown';
$departmentNames = [
    'law-enforcement' => 'Law Enforcement Department',
    'traffic' => 'Traffic & Transport Department',
    'fire' => 'Fire & Rescue Department',
    'emergency' => 'Emergency Response Department',
    'community' => 'Community Policing Department',
    'crime-analytics' => 'Crime Data Analytics Department',
    'public-safety' => 'Public Safety Department',
    'health-safety' => 'Health & Safety Department',
    'disaster' => 'Disaster Preparedness Department',
    'emergency-comm' => 'Emergency Communication Department',
    'super-admin' => 'Super Admin Dashboard'
];

$departmentName = $departmentNames[$currentSubdomain] ?? ucfirst($currentSubdomain) . ' Department';

// Step 10: Make user data available globally
$GLOBALS['authenticated_user'] = [
    'id' => $userId,
    'email' => $userEmail,
    'department' => $userDepartment,
    'role' => $userRole,
    'department_name' => $departmentName,
    'subdomain' => $currentSubdomain,
    'exp' => $exp,
    'is_laravel' => IS_LARAVEL
];

// Step 11: Helper Functions for use in dashboard files
function getCurrentUser() {
    return $GLOBALS['authenticated_user'] ?? null;
}

function getUserRole() {
    return $GLOBALS['authenticated_user']['role'] ?? 'guest';
}

function getUserEmail() {
    return $GLOBALS['authenticated_user']['email'] ?? '';
}

function getUserDepartment() {
    return $GLOBALS['authenticated_user']['department'] ?? '';
}

function getDepartmentName() {
    return $GLOBALS['authenticated_user']['department_name'] ?? '';
}

function isSuperAdmin() {
    return getUserRole() === 'super_admin';
}

function isAdmin() {
    return getUserRole() === 'admin';
}

function isLaravelEnv() {
    return IS_LARAVEL;
}

function getLogoutUrl() {
    return MAIN_DOMAIN . '/logout';
}

function getMainDomain() {
    return MAIN_DOMAIN;
}

// Step 12: Auto-refresh token check (JavaScript helper)
function getTokenRefreshScript() {
    $exp = $GLOBALS['authenticated_user']['exp'] ?? 0;
    $mainDomain = MAIN_DOMAIN;
    return "
    <script>
        // Token expiration check
        const tokenExpiresAt = " . ($exp * 1000) . ",
        const checkTokenExpiration = () => {
            if (Date.now() >= tokenExpiresAt) {
                alert('Your session has expired. Please login again.');
                window.location.href = '{$mainDomain}';
            }
        };
        
        // Check every minute
        setInterval(checkTokenExpiration, 60000);
        checkTokenExpiration();
        
        // Store user data
        const userData = " . json_encode($GLOBALS['authenticated_user']) . ";
        localStorage.setItem('user_data', JSON.stringify(userData));
    </script>";
}

// Step 13: Logout handler
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (IS_LARAVEL) {
        session()->forget('jwt_token');
        return redirect(MAIN_DOMAIN . '/logout');
    } else {
        session_destroy();
        header('Location: ' . MAIN_DOMAIN . '/logout');
        exit();
    }
}

?>