<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use App\Models\StaffUser;
use App\Models\User;

class AuthController extends Controller
{
    /** Columns that actually exist on the centralized user table (cached per request) */
    private ?array $userColumns = null;

    /**
     * Update only the columns the centralized_admin_user table really has.
     * That table is shared with the central portal and its schema differs per
     * environment (production lacks attempt_count etc.), so writing a fixed
     * column list crashes with "Unknown column".
     */
    private function safeUpdate(User $user, array $attrs): void
    {
        if ($this->userColumns === null) {
            try {
                $this->userColumns = $user->getConnection()
                    ->getSchemaBuilder()
                    ->getColumnListing($user->getTable());
            } catch (\Exception $e) {
                \Log::warning('Could not list user table columns: ' . $e->getMessage());
                $this->userColumns = [];
            }
        }

        $filtered = array_intersect_key($attrs, array_flip($this->userColumns));
        if (!empty($filtered)) {
            $user->update($filtered);
        }
    }

    public function showLogin()
    {
        return view('auth.login', [
            'cloudflare_sitekey' => config('captcha.sitekey'),
        ]);
    }

    public function login(Request $request)
    {
        // The widget on the page proves nothing on its own: anything that posts
        // straight to this route skips it entirely. Only this check makes the
        // CAPTCHA real, so it has to run before the credentials are looked at.
        if (!$this->verifyCaptcha($request->input('cf-turnstile-response'))) {
            return back()->withErrors([
                'cf-turnstile-response' => 'Security verification failed. Please try again.',
            ])->onlyInput('email');
        }

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = mb_strtolower(trim($credentials['email']));
        $password = $credentials['password'];
        $ipAddress = $request->ip();

        // One form, two account tables: admins live in the shared centralized
        // table, staff in this app's own table. Admins are looked up first so
        // an admin address can never be shadowed by a staff row.
        $user = User::where('email', $email)->first();

        if ($user) {
            return $this->loginAdmin($request, $user, $password, $ipAddress);
        }

        $staff = $this->findStaff($email);

        if ($staff) {
            return $this->loginStaff($request, $staff, $password, $ipAddress);
        }

        return back()->withErrors([
            'email' => 'Account not found. Please contact administrator.',
        ])->onlyInput('email');
    }

    /** Staff lookup that survives a database where the staff table is not migrated yet */
    private function findStaff(string $email): ?StaffUser
    {
        try {
            return StaffUser::where('email', $email)->first();
        } catch (\Throwable $e) {
            \Log::warning('Staff table unavailable during login: ' . $e->getMessage());
            return null;
        }
    }

    private function loginAdmin(Request $request, User $user, string $password, string $ipAddress)
    {
        // Check if account is locked (unlock_token indicates locked status)
        if ($user->unlock_token && $user->unlock_token_expiry && $user->unlock_token_expiry->isFuture()) {
            return back()->withErrors([
                'email' => 'Your account has been locked due to multiple failed login attempts. Please check your email for unlock instructions.',
            ])->onlyInput('email');
        }

        // Verify password using password_hash from centralized table
        if (!password_verify($password, $user->password_hash)) {
            $currentAttempts = intval($user->attempt_count) + 1;

            if ($currentAttempts >= 3) {
                // Lock account by setting unlock token
                $this->sendAccountLockedEmail($user, $ipAddress);
                $this->safeUpdate($user, [
                    'attempt_count' => $currentAttempts,
                    'ip_address' => $ipAddress,
                ]);
                return back()->withErrors([
                    'email' => 'Account locked. Check your email for unlock instructions.',
                ])->onlyInput('email');
            }

            // Update attempt count and IP
            $this->safeUpdate($user, [
                'attempt_count' => $currentAttempts,
                'ip_address' => $ipAddress,
            ]);

            $remainingAttempts = 3 - $currentAttempts;
            return back()->withErrors([
                'email' => "Invalid credentials. $remainingAttempts attempt(s) remaining.",
            ])->onlyInput('email');
        }

        // Password is correct - login directly (no OTP step)
        $this->safeUpdate($user, [
            'attempt_count' => 0,
            'ip_address' => $ipAddress,
            'last_login' => now(),
            'last_activity' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        // The same session shape the centralized JWT login produces, so every
        // page (sidebar, audit log, profile) reads one source of identity.
        session(['auth_user' => [
            'id'              => $user->id,
            'email'           => $user->email,
            'full_name'       => $user->full_name ?? $user->name ?? $user->email,
            'role'            => $user->role ?? 'admin',
            'account_type'    => 'admin',
            'department'      => $user->department ?? 'crime_data_department',
            'department_name' => $user->department
                ? ucwords(str_replace('_', ' ', $user->department))
                : 'Crime Data Department',
            'must_change_password' => false,
        ]]);

        $this->audit('LOGIN_ADMIN', 'centralized_admin_user', (int) $user->id, ['email' => $user->email]);

        return redirect()->intended('dashboard');
    }

    private function loginStaff(Request $request, StaffUser $staff, string $password, string $ipAddress)
    {
        if (!$staff->is_active) {
            return back()->withErrors([
                'email' => 'This staff account has been deactivated. Please contact your administrator.',
            ])->onlyInput('email');
        }

        if ($staff->isLocked()) {
            $minutes = max(1, now()->diffInMinutes($staff->locked_until, false));
            return back()->withErrors([
                'email' => "Too many failed attempts. Try again in about {$minutes} minute(s).",
            ])->onlyInput('email');
        }

        if (!password_verify($password, $staff->password_hash)) {
            $attempts = (int) $staff->attempt_count + 1;
            $update = ['attempt_count' => $attempts, 'ip_address' => $ipAddress];

            if ($attempts >= 5) {
                $update['locked_until'] = now()->addMinutes(15);
                $update['attempt_count'] = 0;
                $staff->update($update);
                return back()->withErrors([
                    'email' => 'Too many failed attempts. The account is locked for 15 minutes.',
                ])->onlyInput('email');
            }

            $staff->update($update);
            $remaining = 5 - $attempts;
            return back()->withErrors([
                'email' => "Invalid credentials. $remaining attempt(s) remaining.",
            ])->onlyInput('email');
        }

        $staff->update([
            'attempt_count' => 0,
            'locked_until'  => null,
            'ip_address'    => $ipAddress,
            'last_login'    => now(),
            'last_activity' => now(),
        ]);

        Auth::guard('staff')->login($staff);
        $request->session()->regenerate();
        session(['auth_user' => $staff->sessionPayload()]);

        $this->audit('LOGIN_STAFF', 'crime_department_staff', (int) $staff->id, ['email' => $staff->email]);

        if ($staff->must_change_password) {
            return redirect()->route('profile')
                ->with('error', 'You signed in with a temporary password. Please set a new password to continue.');
        }

        return redirect()->intended('dashboard');
    }

    /** Audit entry that never breaks the login when the audit table is missing */
    private function audit(string $action, string $table, int $id, array $details = []): void
    {
        try {
            \App\Services\AuditLogService::log($action, $table, $id, $details);
        } catch (\Throwable $e) {
            \Log::warning("Could not write {$action} audit entry: " . $e->getMessage());
        }
    }

    private function sendAccountLockedEmail($user, $ipAddress)
    {
        $unlockToken = Str::random(64);
        $unlockTokenExpiry = now()->addHour();

        // Update with unlock token for locked state
        $this->safeUpdate($user, [
            'unlock_token' => $unlockToken,
            'unlock_token_expiry' => $unlockTokenExpiry,
        ]);

        // Send email with account locked notification
        try {
            \Mail::send('emails.account-locked', [
                'user' => $user,
                'ipAddress' => $ipAddress,
                'unlockToken' => $unlockToken,
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Account Locked - Security Alert');
            });
        } catch (\Exception $e) {
            \Log::error("Failed to send account locked email: " . $e->getMessage());
        }
    }

    private function verifyCaptcha($token)
    {

        if (empty($token)) {
            \Log::warning('CAPTCHA token is empty');
            return false;
        }

        try {
            $client = new Client();
            $secretKey = config('captcha.secret');

            \Log::info('Sending verification request to Cloudflare', [
                'secret_key_preview' => substr($secretKey ?? '', 0, 10) . '...',
                'secret_key_exists' => !empty($secretKey),
            ]);

            $response = $client->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'form_params' => [
                    'secret' => $secretKey,
                    'response' => $token,
                ],
                'timeout' => 10,
                'connect_timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
            $result = json_decode($body, true);

            \Log::info('Captcha verification result', [
                'status_code' => $statusCode,
                'success' => $result['success'] ?? false,
                'errors' => $result['error-codes'] ?? [],
                'full_response' => $result,
            ]);

            return $result['success'] === true;
        } catch (\Exception $e) {
            \Log::error('Captcha verification error: ' . $e->getMessage(), [
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    public function unlockAccount($token)
    {
        $user = User::where('unlock_token', $token)->first();

        if (!$user) {
            return redirect('/')->withErrors(['error' => 'Invalid or expired unlock token.']);
        }

        if ($user->unlock_token_expiry && $user->unlock_token_expiry->isPast()) {
            return redirect('/')->withErrors(['error' => 'Unlock token has expired. Please contact administrator.']);
        }

        $this->safeUpdate($user, [
            'attempt_count' => 0,
            'unlock_token' => null,
            'unlock_token_expiry' => null,
        ]);

        return redirect('/')->with('success', 'Account unlocked successfully! You can now login.');
    }

    public function logout(Request $request)
        {
            // Get JWT token before clearing session
            $jwtToken = session('jwt_token');

            // Call centralized logout API endpoint if token exists
            if ($jwtToken) {
                try {
                    $response = Http::withToken($jwtToken)
                        ->timeout(10)
                        ->post('https://login.alertaraqc.com/api/logout');

                    if ($response->successful()) {
                        $data = $response->json();
                        if ($data['success'] ?? false) {
                            \Log::info('Centralized logout successful');
                        } else {
                            \Log::warning('Centralized logout failed: ' . ($data['message'] ?? 'Unknown error'));
                        }
                    } else {
                        \Log::error('Centralized logout API error', [
                            'status_code' => $response->status(),
                            'message' => $response->json('message', 'Unknown error')
                        ]);
                    }
                } catch (\Exception $e) {
                    \Log::error('Centralized logout request failed: ' . $e->getMessage());
                }
            }

            // Clear session completely
            $request->session()->forget(['jwt_token', 'auth_user']);
            Auth::logout();
            Auth::guard('staff')->logout();
            $request->session()->regenerateToken();
            $request->session()->invalidate();

            // Always redirect back to this app's own login page
            $response = redirect('/login');

            // Get cookie configuration
            $sessionCookieName = config('session.cookie');
            $sessionDomain = config('session.domain');
            $sessionPath = config('session.path', '/');

            // All cookies to clear
            $cookiesToClear = [
                'laravel_session',
                $sessionCookieName,
                'XSRF-TOKEN',
                'jwt_token',
                'remember_me',
                'auth_token'
            ];

            // Domains to clear from
            $domains = [
                null,  // Current domain
                '.alertaraqc.com',
                'login.alertaraqc.com',
                $sessionDomain
            ];

            // Clear all cookie combinations
            foreach ($cookiesToClear as $cookieName) {
                foreach ($domains as $domain) {
                    if ($domain) {
                        $response->cookie(
                            $cookieName,
                            '',
                            -1,  // ✅ Expires immediately
                            $sessionPath ?? '/',
                            $domain,
                            false,
                            true
                        );
                    }
                }
            }

            return $response;
        }

    public function redirectToGoogle()
    {
        $googleConfig = config('services.google');

        $query = http_build_query([
            'client_id' => $googleConfig['client_id'],
            'redirect_uri' => $googleConfig['redirect'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
        ]);

        return redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $query);
    }

    public function handleGoogleCallback(Request $request)
    {
        if (!$request->has('code')) {
            return redirect('/')->withErrors(['error' => 'Google login failed']);
        }

        $code = $request->input('code');
        $googleConfig = config('services.google');

        try {
            $client = new Client();

            $response = $client->post('https://oauth2.googleapis.com/token', [
                'form_params' => [
                    'code' => $code,
                    'client_id' => $googleConfig['client_id'],
                    'client_secret' => $googleConfig['client_secret'],
                    'redirect_uri' => $googleConfig['redirect'],
                    'grant_type' => 'authorization_code',
                ],
            ]);

            $tokens = json_decode((string) $response->getBody(), true);
            $accessToken = $tokens['access_token'];

            $userResponse = $client->get('https://www.googleapis.com/oauth2/v2/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                ],
            ]);

            $googleUser = json_decode((string) $userResponse->getBody(), true);

            // Check if user exists in centralized_admin_user table
            $user = User::where('email', $googleUser['email'])->first();

            if (!$user) {
                return redirect('/')->withErrors(['error' => 'Account not found. Please contact administrator to create your account.']);
            }

            // Check if account is locked
            if ($user->unlock_token && $user->unlock_token_expiry && $user->unlock_token_expiry->isFuture()) {
                return redirect('/')->withErrors(['error' => 'Your account has been locked. Please contact administrator.']);
            }

            // Update last login, attempt count reset, and activity timestamp
            $this->safeUpdate($user, [
                'attempt_count' => 0,
                'last_login' => now(),
                'last_activity' => now(),
                'ip_address' => $request->ip(),
            ]);

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended('dashboard');
        } catch (\Exception $e) {
            \Log::error('Google OAuth Callback Error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'redirect_uri' => config('services.google.redirect'),
                'app_url' => config('app.url'),
                'google_config' => [
                    'client_id' => config('services.google.client_id') ? 'SET' : 'NOT SET',
                    'client_secret' => config('services.google.client_secret') ? 'SET' : 'NOT SET',
                    'redirect' => config('services.google.redirect'),
                ]
            ]);
            return redirect('/')->withErrors(['error' => 'Failed to authenticate with Google. Please try again or contact support.']);
        }
    }
}
