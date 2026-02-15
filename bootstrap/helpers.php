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
