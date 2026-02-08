<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Add CSP headers for Cloudflare Turnstile
        $response->header('Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.jsdelivr.net https://cdn.datatables.net; " .
            "frame-src https://challenges.cloudflare.com; " .
            "connect-src 'self' https://challenges.cloudflare.com https://cdnjs.cloudflare.com https://oauth2.googleapis.com https://www.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net; " .
            "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com https://cdn.datatables.net; " .
            "img-src 'self' data: https:; " .
            "font-src 'self' https://cdnjs.cloudflare.com; " .
            "form-action 'self';"
        );

        return $response;
    }
}
