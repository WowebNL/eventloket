<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('app.env', 'local');
    Config::set('app.debug', false);
});

test('security headers are present on every response', function () {
    $response = $this->get('/up');

    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    $response->assertHeader('X-Permitted-Cross-Domain-Policies', 'none');
    $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    $response->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    $response->assertHeader('Content-Security-Policy');
});

test('CSP contains required directives', function () {
    $response = $this->get('/up');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("default-src 'self'")
        ->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval'")
        ->toContain("style-src 'self' 'unsafe-inline'")
        ->toContain("img-src 'self' data: blob: https://tile.openstreetmap.org")
        ->toContain("font-src 'self'")
        ->toContain("connect-src 'self' https://api.pdok.nl")
        ->toContain("frame-ancestors 'none'")
        ->toContain("frame-src 'none'")
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'");
});

test('CSP allows the Vite dev server in debug mode', function () {
    Config::set('app.debug', true);

    $response = $this->get('/up');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)
        ->toContain("script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:5173")
        ->toContain("style-src 'self' 'unsafe-inline' http://localhost:5173")
        ->toContain('ws://localhost:5173')
        ->toContain('ws://127.0.0.1:5173');
});

test('CSP does not reference the Vite dev server when debug is off', function () {
    Config::set('app.debug', false);

    $response = $this->get('/up');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('5173');
});

test('HSTS header is absent in local environment', function () {
    Config::set('app.env', 'local');

    $response = $this->get('/up');

    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

test('HSTS header is present in non-local environments', function () {
    Config::set('app.env', 'production');

    $response = $this->get('https://localhost/up');

    $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('upgrade-insecure-requests is absent in local environment', function () {
    Config::set('app.env', 'local');

    $response = $this->get('/up');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->not->toContain('upgrade-insecure-requests');
});

test('upgrade-insecure-requests is present in non-local environments', function () {
    Config::set('app.env', 'production');

    $csp = $this->get('https://localhost/up')
        ->headers
        ->get('Content-Security-Policy');

    expect($csp)->toContain('upgrade-insecure-requests');
});

test('non-local HTTP requests are redirected to HTTPS', function () {
    Config::set('app.env', 'staging');

    $response = $this->get('http://example.com/up');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://');
    expect($response->getStatusCode())->toBe(301);
});

test('local HTTP requests are not redirected to HTTPS', function () {
    Config::set('app.env', 'local');

    $response = $this->get('http://localhost/up');

    $response->assertStatus(200);
});

test('testing HTTP requests are not redirected to HTTPS', function () {
    Config::set('app.env', 'testing');

    $response = $this->get('http://localhost/up');

    $response->assertStatus(200);
});
