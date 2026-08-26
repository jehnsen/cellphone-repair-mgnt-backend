<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // No 'sanctum/csrf-cookie' path — this API is stateless bearer-token auth
    // only (Rule Zero: no cookies, no CSRF). See docs/design/01-domain-design.md.
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    // Explicit allow-list, never '*' — set CORS_ALLOWED_ORIGINS as a comma
    // separated list (e.g. the Next.js PWA origin(s)) in .env.
    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 3600,

    // No cookie-based auth exists, so credentialed CORS requests are never needed.
    'supports_credentials' => false,

];
