<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Pages reserved for admins (Staff Management). Staff accounts are sent back
 * to the dashboard with a notice instead of a bare 403.
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (!isAdminAccount()) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'Admin access required.'], 403);
            }

            return redirect()->route('dashboard')
                ->with('error', 'That page is for administrators only.');
        }

        return $next($request);
    }
}
