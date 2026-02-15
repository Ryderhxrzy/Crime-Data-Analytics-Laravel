<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthService
{
    protected $apiEndpoint;
    protected $apiToken;

    public function __construct()
    {
        $this->apiEndpoint = env('AUTH_API_ENDPOINT', 'https://login.alertaraqc.com/api/auth/validate');
        $this->apiToken = env('AUTH_API_TOKEN');
    }

    /**
     * Validate JWT token via external API
     */
    public function validateToken($token)
    {
        try {
            $response = Http::timeout(10)
                ->get($this->apiEndpoint, [
                    'token' => $token,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['authenticated']) && $data['authenticated'] === true) {
                    return $data;
                }
            }

            return null;
        } catch (\Exception $e) {
            Log::error('JWT validation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get cached user from session
     */
    public function getCachedUser()
    {
        return session('auth_user');
    }

    /**
     * Set user in session
     */
    public function setUserInSession($userData)
    {
        if (isset($userData['user'])) {
            session(['auth_user' => $userData['user']]);
        }
    }

    /**
     * Clear user from session
     */
    public function clearUserSession()
    {
        session()->forget('jwt_token');
        session()->forget('auth_user');
    }

    /**
     * Check if token is still valid (check expiration)
     */
    public function isTokenValid()
    {
        $user = $this->getCachedUser();

        if (!$user) {
            return false;
        }

        // Check if token has expired
        if (isset($user['exp']) && $user['exp'] < time()) {
            $this->clearUserSession();
            return false;
        }

        return true;
    }
}
