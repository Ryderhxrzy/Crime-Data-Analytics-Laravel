<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * A staff member signed in with the temporary password an admin emailed them
 * is taken to their profile until they set a password of their own.
 */
class RequirePasswordChange
{
    /** Routes a staff member may still reach while the change is pending */
    private const ALLOWED = ['profile', 'profile.update', 'profile.password', 'logout'];

    public function handle(Request $request, Closure $next)
    {
        $account = currentAccount();

        if ($account
            && ($account['account_type'] ?? null) === 'staff'
            && !empty($account['must_change_password'])
            && !in_array($request->route()?->getName(), self::ALLOWED, true)
        ) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Please set a new password before continuing.',
                ], 403);
            }

            return redirect()->route('profile')
                ->with('error', 'You are using a temporary password. Please set a new password to continue.');
        }

        return $next($request);
    }
}
