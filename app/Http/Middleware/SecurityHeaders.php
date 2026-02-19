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

        // Add CSP headers for Cloudflare Turnstile, Leaflet Maps, Reverb WebSocket, and other resources
        // In development, allow Vite dev server on localhost:5173 and 127.0.0.1:5173
        $viteAllowed = app()->environment('local') ? "http://localhost:5173 http://127.0.0.1:5173" : "";

        $response->header('Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' {$viteAllowed} https://challenges.cloudflare.com https://cdn.tailwindcss.com https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.jsdelivr.net https://cdn.datatables.net https://unpkg.com https://js.pusher.com https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.min.js; " .
            "frame-src https://challenges.cloudflare.com; " .
            "connect-src 'self' ws: wss: {$viteAllowed} https://challenges.cloudflare.com https://cdnjs.cloudflare.com https://oauth2.googleapis.com https://www.googleapis.com https://cdn.jsdelivr.net https://cdn.datatables.net https://tile.openstreetmap.org https://unpkg.com https://*.pusher.com; " .
            "style-src 'self' 'unsafe-inline' {$viteAllowed} https://cdnjs.cloudflare.com https://cdn.tailwindcss.com https://cdn.datatables.net https://unpkg.com; " .
            "img-src 'self' data: https: https://tile.openstreetmap.org; " .
            "font-src 'self' https://cdnjs.cloudflare.com; " .
            "form-action 'self';"
        );

        return $response;
    }
}
