<?php

return [
    // Login/General CAPTCHA
    'sitekey' => env('CLOUDFLARE_SITEKEY'),
    'secret' => env('CLOUDFLARE_SECRET_KEY'),

    // Submit Tip CAPTCHA
    'submit_tip_sitekey' => env('SUBMIT_TIP_CLOUDFLARE_SITEKEY'),
    'submit_tip_secret' => env('SUBMIT_TIP_CLOUDFLARE_SECRET_KEY'),
];
