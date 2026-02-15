<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AuthService;

class ValidateJWTViaAPI
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function handle(Request $request, Closure $next)
    {
        // Get token from URL query parameter or session
        $token = $request->query('token') ?? session('jwt_token');

        if ($token) {
            // Store token in session if it came from URL
            if ($request->query('token')) {
                session(['jwt_token' => $token]);
            }

            // Check if we need to validate (re-validate every request or only on first login)
            $cachedUser = $this->authService->getCachedUser();

            if (!$cachedUser || !$this->authService->isTokenValid()) {
                // Validate token via external API
                $response = $this->authService->validateToken($token);

                if ($response) {
                    // Cache user data in session
                    $this->authService->setUserInSession($response);

                    // Redirect to remove token from URL if it came from query parameter
                    if ($request->query('token')) {
                        return redirect()->to($request->path());
                    }
                } else {
                    // Token validation failed - clear session and redirect to login
                    session()->flush();
                    return redirect('/login');
                }
            }
        } else {
            // Check if user data is cached in session
            $cachedUser = $this->authService->getCachedUser();

            if (!$cachedUser) {
                // No token and no cached user - redirect to login
                return redirect('/login');
            }

            // Check if cached user token has expired
            if (!$this->authService->isTokenValid()) {
                session()->flush();
                return redirect('/login');
            }
        }

        return $next($request);
    }
}
