<?php

use Illuminate\Support\Facades\Route;

/**
 * Rule Zero: every route returns application/json or 204 No Content, no
 * matter what. This iterates the entire route list so a package that
 * accidentally registers an HTML-rendering route gets caught immediately.
 */
it('returns a JSON content type from every registered route', function () {
    $exercised = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        foreach ($route->methods() as $method) {
            if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                continue;
            }

            $concreteUri = preg_replace('/\{[^}]+\?\}/', '1', $uri);
            $concreteUri = preg_replace('/\{[^}]+\}/', '1', $concreteUri);
            $signature = $method.' '.$concreteUri;

            if (isset($exercised[$signature])) {
                continue;
            }
            $exercised[$signature] = true;

            $response = $this->call($method, '/'.ltrim($concreteUri, '/'));

            if ($response->getStatusCode() === 204) {
                continue;
            }

            $this->assertStringContainsString(
                'application/json',
                (string) $response->headers->get('Content-Type'),
                "Route [{$signature}] did not return a JSON content type (got status {$response->getStatusCode()}).",
            );
        }
    }

    expect($exercised)->not->toBeEmpty();
});
