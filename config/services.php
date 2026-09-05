<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_SECRET'),
        'redirect' => env('APP_URL') . '/auth/google/callback',
    ],

    // Google Maps JavaScript API (browser key, restrict it by HTTP referrer in
    // the Google Cloud console). Read from .env only; never hard-coded. The
    // VITE_ name is accepted too so an existing .env keeps working.
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY', env('VITE_GOOGLE_MAPS_API_KEY')),
    ],

    'central_auth' => [
        'login_url' => env('CENTRAL_LOGIN_URL', 'https://login.alertaraqc.com'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-flash-latest'),
    ],

    // Alertara Reports system — read-only crime feed behind the crime map's
    // "Import from Reports" tool. No credentials: the endpoint is public.
    'alertara_reports' => [
        'url' => env('REPORTS_API_URL', 'https://report.alertaraqc.com/api/api.php'),
        'timeout' => env('REPORTS_API_TIMEOUT', 20),
    ],

];
