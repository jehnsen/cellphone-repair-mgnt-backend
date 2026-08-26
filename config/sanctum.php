<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sanctum Routes
    |--------------------------------------------------------------------------
    |
    | Disabled outright: the only route this registers is GET /sanctum/csrf-
    | cookie, which exists solely to support cookie-based SPA authentication.
    | This API never does that (Rule Zero — stateless bearer tokens only), so
    | the route is dead surface area otherwise.
    |
    */

    'routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Intentionally empty: this API never issues cookie-based SPA sessions
    | (Rule Zero — no cookies, no CSRF, stateless bearer tokens only). Every
    | client, including the Next.js PWA, authenticates with a personal
    | access token via POST /api/v1/auth/token.
    |
    */

    'stateful' => [],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | No global forced expiry — per the domain design, a POS terminal keeps a
    | long-lived device token while staff tokens are shorter, so expiry is
    | set per-token at issuance time instead (see TokenController).
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

];
