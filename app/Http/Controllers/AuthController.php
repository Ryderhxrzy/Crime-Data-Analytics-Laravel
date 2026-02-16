<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use App\Models\User;
use App\Models\OtpVerification;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', [
            'cloudflare_sitekey' => config('captcha.sitekey'),
        ]);
    }

    public function login(Request $request)
    {
        // Verify CAPTCHA
        $captchaToken = $request->input('cf-turnstile-response');
        $captchaValid = $this->verifyCaptcha($captchaToken);
        if (!$captchaValid) {
            return back()->withErrors(['cf-turnstile-response' => 'Security verification failed. Please try again.']);
        }

        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $credentials['email'];
        $password = $credentials['password'];
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Find user by email
        $user = User::where('email', $email)->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'Account not found. Please contact administrator.',
            ])->onlyInput('email');
        }

        // Check if account is locked
        if ($user->status === 'lock') {
            return back()->withErrors([
                'email' => 'Your account has been locked due to multiple failed login attempts. Please check your email for unlock instructions.',
            ])->onlyInput('email');
        }

        // Check if account is verified
        if ($user->account_status === 'unverified') {
            return back()->withErrors([
                'email' => 'Your account is not verified. Please contact the super admin.',
            ])->onlyInput('email');
        }

        // Verify password
        if (!password_verify($password, $user->password)) {
            $currentAttempts = intval($user->attempt_count) + 1;
            $newStatus = $user->status;

            if ($currentAttempts >= 3) {
                $newStatus = 'lock';
            }

            $user->update([
                'attempt_count' => $currentAttempts,
                'status' => $newStatus,
                'ip_address' => $ipAddress,
            ]);

            // If account is now locked, send unlock email
            if ($newStatus === 'lock') {
                $this->sendAccountLockedEmail($user, $ipAddress);
                return back()->withErrors([
                    'email' => 'Account locked. Check your email for unlock instructions.',
                ])->onlyInput('email');
            } else {
                $remainingAttempts = 3 - $currentAttempts;
                return back()->withErrors([
                    'email' => "Invalid credentials. $remainingAttempts attempt(s) remaining.",
                ])->onlyInput('email');
            }
        }

        // Password is correct - check if 2FA is enabled
        $isTwoFactorEnabled = \DB::table('crime_department_user_settings')
            ->where('admin_user_id', $user->id)
            ->where('two_factor_auth', 1)
            ->exists();

        if ($isTwoFactorEnabled) {
            // Send OTP and redirect to verification page
            $this->sendOtpEmail($user, $ipAddress);

            $request->session()->put([
                'pending_user_id' => $user->id,
                'pending_ip' => $ipAddress,
                'otp_sent_at' => now(),
            ]);

            return redirect()->route('verify.otp.show');
        }

        // 2FA not enabled - login directly
        $user->update([
            'status' => 'active',
            'attempt_count' => 0,
            'ip_address' => $ipAddress,
            'last_login' => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('dashboard');
    }

    private function sendAccountLockedEmail($user, $ipAddress)
    {
        $unlockToken = Str::random(64);
        $unlockTokenExpiry = now()->addHour();

        $user->update([
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

    private function sendOtpEmail($user, $ipAddress)
    {
        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(5);

        // Create OTP record in verification table
        OtpVerification::create([
            'admin_user_id' => $user->id,
            'otp_code' => $otpCode,
            'expires_at' => $expiresAt,
            'is_used' => false,
            'attempt_count' => 0,
        ]);

        // Send OTP email
        try {
            \Mail::send('emails.otp-verification', [
                'user' => $user,
                'otpCode' => $otpCode,
                'ipAddress' => $ipAddress,
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Verify Your Login - New Device Detected');
            });
        } catch (\Exception $e) {
            \Log::error("Failed to send OTP email: " . $e->getMessage());
        }
    }

    public function showVerifyOtp()
    {
        if (!session()->has('pending_user_id')) {
            return redirect('/')->withErrors(['error' => 'Session expired. Please login again.']);
        }

        $userId = session('pending_user_id');
        $user = User::find($userId);
        $otpSentAt = session('otp_sent_at');

        return view('auth.verify-otp', [
            'user' => $user,
            'otpSentAt' => $otpSentAt,
        ]);
    }

    public function resendOtp(Request $request)
    {
        if (!session()->has('pending_user_id')) {
            return response()->json(['error' => 'Session expired'], 401);
        }

        $userId = session('pending_user_id');
        $pendingIp = session('pending_ip');
        $user = User::find($userId);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Check if user can resend (prevent spam - 30 seconds)
        $lastOtp = OtpVerification::where('admin_user_id', $user->id)
            ->latest('created_at')
            ->first();

        if ($lastOtp && $lastOtp->created_at->addSeconds(30) > now()) {
            return response()->json([
                'error' => 'Please wait before requesting a new OTP',
                'retryAfter' => $lastOtp->created_at->addSeconds(30)->diffInSeconds(now())
            ], 429);
        }

        // Send new OTP
        $this->sendOtpEmail($user, $pendingIp);
        $request->session()->put('otp_sent_at', now());

        return response()->json(['success' => true, 'message' => 'OTP sent successfully']);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp_code' => 'required|digits:6',
        ]);

        $userId = $request->session()->get('pending_user_id');
        $pendingIp = $request->session()->get('pending_ip');

        $user = User::find($userId);

        if (!$user) {
            return back()->withErrors(['error' => 'Session expired. Please login again.']);
        }

        // Find the latest OTP record for this user
        $otp = OtpVerification::where('admin_user_id', $user->id)
            ->where('is_used', false)
            ->latest('created_at')
            ->first();

        if (!$otp) {
            return back()->withErrors(['error' => 'No pending OTP found. Please login again.']);
        }

        // Check if OTP has expired
        if ($otp->isExpired()) {
            return back()->withErrors(['otp_code' => 'OTP has expired. Please login again.']);
        }

        // Check attempt count
        if ($otp->attempt_count >= 3) {
            return back()->withErrors(['otp_code' => 'Too many failed attempts. Please login again.']);
        }

        // Verify OTP code
        if ($otp->otp_code !== $request->input('otp_code')) {
            $otp->increment('attempt_count');
            $otp->update(['last_attempt_at' => now()]);

            $remainingAttempts = 3 - $otp->attempt_count;
            return back()->withErrors(['otp_code' => "Invalid OTP code. $remainingAttempts attempt(s) remaining."]);
        }

        // Mark OTP as used
        $otp->update(['is_used' => true]);

        // Update user and login
        $user->update([
            'status' => 'active',
            'attempt_count' => 0,
            'ip_address' => $pendingIp,
            'last_login' => now(),
        ]);

        Auth::login($user);
        $request->session()->forget(['pending_user_id', 'pending_ip']);
        $request->session()->regenerate();

        return redirect()->intended('dashboard')->with('success', 'Welcome back! Your login was verified.');
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

        $user->update([
            'status' => 'active',
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
                        $message = $data['message'] ?? 'Unknown error';
                        \Log::warning('Centralized logout failed: ' . $message);
                    }
                } else {
                    $error = $response->json('message', 'Unknown error');
                    \Log::error('Centralized logout API error: ' . $error, [
                        'status_code' => $response->status()
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Centralized logout request failed: ' . $e->getMessage(), [
                    'exception' => \get_class($e),
                    'code' => $e->getCode(),
                ]);
            }
        }

        // Clear JWT token and user data from session
        $request->session()->forget(['jwt_token', 'auth_user']);

        // Also clear local auth
        Auth::logout();

        // Determine redirect URL based on environment
        if (app()->environment() === 'production') {
            // Production: redirect to centralized login system
            $redirectUrl = 'https://login.alertaraqc.com';
        } else {
            // Local/Development: redirect to local login page
            $redirectUrl = '/login';
        }

        // Regenerate CSRF token BEFORE invalidating session
        $request->session()->regenerateToken();
        $request->session()->invalidate();

        // Explicitly delete the session cookies to clear them from browser
        $response = redirect($redirectUrl);

        // Clear the session cookie by setting it to expire in the past
        $sessionCookieName = config('session.cookie');
        $sessionDomain = config('session.domain');
        $sessionPath = config('session.path', '/');

        // Delete cookie for configured domain
        if ($sessionDomain) {
            $response->cookie(
                $sessionCookieName,
                '',
                now()->subDays(1),
                $sessionPath,
                $sessionDomain,
                false,
                true
            );
        }

        // Also delete parent domain cookie (.alertaraqc.com) for production cross-domain logout
        if (app()->environment() === 'production' && $sessionDomain !== '.alertaraqc.com') {
            $response->cookie(
                $sessionCookieName,
                '',
                now()->subDays(1),
                '/',
                '.alertaraqc.com',
                false,
                true
            );
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

            // Check if user exists - don't auto-create
            $user = User::where('email', $googleUser['email'])->first();

            if (!$user) {
                return redirect('/')->withErrors(['error' => 'Account not found. Please contact administrator to create your account.']);
            }

            // Check if account is verified
            if ($user->account_status === 'unverified') {
                return redirect('/')->withErrors(['error' => 'Your account is not verified. Please contact the super admin.']);
            }

            // Check if account is locked
            if ($user->status === 'lock') {
                return redirect('/')->withErrors(['error' => 'Your account has been locked. Please contact administrator.']);
            }

            // Update google_id if not set
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser['id']]);
            }

            // Update last login and ip address
            $user->update([
                'status' => 'active',
                'attempt_count' => 0,
                'last_login' => now(),
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
