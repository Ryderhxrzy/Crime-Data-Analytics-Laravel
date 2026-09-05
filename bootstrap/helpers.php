<?php

/**
 * Global helper functions for authentication
 * Auto-loaded by composer.json
 */

if (!function_exists('getAuthUser')) {
    /**
     * Get the authenticated user from session
     */
    function getAuthUser()
    {
        return session('auth_user');
    }
}

if (!function_exists('getUserEmail')) {
    /**
     * Get the authenticated user's email
     */
    function getUserEmail()
    {
        return session('auth_user.email') ?? null;
    }
}

if (!function_exists('getUserRole')) {
    /**
     * Get the authenticated user's role
     */
    function getUserRole()
    {
        return session('auth_user.role') ?? null;
    }
}

if (!function_exists('getUserDepartment')) {
    /**
     * Get the authenticated user's department
     */
    function getUserDepartment()
    {
        return session('auth_user.department') ?? null;
    }
}

if (!function_exists('getDepartmentName')) {
    /**
     * Get the authenticated user's department name
     */
    function getDepartmentName()
    {
        return session('auth_user.department_name') ?? null;
    }
}

if (!function_exists('getUserId')) {
    /**
     * Get the authenticated user's ID
     */
    function getUserId()
    {
        return session('auth_user.id') ?? null;
    }
}

if (!function_exists('currentAccount')) {
    /**
     * The signed-in account as one plain array, whichever way they signed in:
     * centralized JWT, local admin (web guard) or staff (staff guard).
     * Keys: id, email, full_name, role, account_type ('admin'|'staff'),
     * department, department_name, must_change_password.
     */
    function currentAccount(): ?array
    {
        $session = session('auth_user');
        if (!empty($session)) {
            $session['account_type'] = $session['account_type'] ?? 'admin';
            return $session;
        }

        if (auth('staff')->check()) {
            return auth('staff')->user()->sessionPayload();
        }

        if (auth()->check()) {
            $u = auth()->user();
            return [
                'id'              => $u->id,
                'email'           => $u->email,
                'full_name'       => $u->full_name ?? $u->name ?? $u->email,
                'role'            => $u->role ?? 'admin',
                'account_type'    => 'admin',
                'department'      => $u->department ?? null,
                'department_name' => $u->department ? ucwords(str_replace('_', ' ', $u->department)) : null,
                'must_change_password' => false,
            ];
        }

        return null;
    }
}

if (!function_exists('isStaffAccount')) {
    /** True when the signed-in account is a staff member (not an admin) */
    function isStaffAccount(): bool
    {
        return (currentAccount()['account_type'] ?? null) === 'staff';
    }
}

if (!function_exists('isAdminAccount')) {
    /** True when the signed-in account is an admin (centralized or local) */
    function isAdminAccount(): bool
    {
        $account = currentAccount();
        return $account !== null && ($account['account_type'] ?? 'admin') !== 'staff';
    }
}

if (!function_exists('isAuthenticated')) {
    /**
     * Check if user is authenticated
     */
    function isAuthenticated()
    {
        return !empty(session('auth_user'));
    }
}

if (!function_exists('getLogoutUrl')) {
    /**
     * Get the logout URL based on environment
     */
    function getLogoutUrl()
    {
        if (app()->environment() === 'production') {
            return 'https://login.alertaraqc.com/logout';
        }
        return route('logout');
    }
}

if (!function_exists('authUrl')) {
    /**
     * Generate URL for authenticated routes
     * Token is now handled via session/middleware, no need to append to URL
     */
    function authUrl($route, $parameters = [])
    {
        return route($route, $parameters);
    }
}
