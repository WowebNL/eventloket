<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The trusted-proxy contract.
 *
 * Two halves, and the first one is the one that must never regress: with no
 * TRUSTED_PROXIES in the environment the application ignores every forwarded
 * header, because the login limiters in config/auth.php key on the real client
 * address. The second half is the opt-in that config/trustedproxy.php adds for
 * environments that really do sit behind a proxy.
 */
beforeEach(function () {
    Route::get('/_test/trusted-proxy-probe', fn (Request $request) => response()->json([
        'ip' => $request->ip(),
        'scheme' => $request->getScheme(),
        'secure' => $request->isSecure(),
        // host() and url() are what a signed link (a password reset, for example)
        // is generated from, so they prove whether a forged X-Forwarded-Host can
        // reach the URL generator.
        'host' => $request->getHost(),
        'url' => $request->url(),
    ]));
});

afterEach(function () {
    // setTrustedProxies() is static state on the Symfony request. The middleware
    // resets it on every request, but leaving it set would still be a surprise
    // for anything running after this file.
    Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
});

/**
 * Load config/trustedproxy.php with TRUSTED_PROXIES set to the given value and
 * publish the result into the running config, exactly as booting the
 * application with that environment variable would.
 *
 * The env has to be read through the config file itself: setting the config key
 * directly would test nothing, since Config::set() works whether or not the
 * file exists. A missing file yields an empty config, so these tests fail on
 * the behaviour they describe rather than on a file that is not there.
 */
function bootTrustedProxyConfig(string $trustedProxies): void
{
    $path = config_path('trustedproxy.php');

    $previous = $_ENV['TRUSTED_PROXIES'] ?? null;

    putenv('TRUSTED_PROXIES='.$trustedProxies);
    $_ENV['TRUSTED_PROXIES'] = $trustedProxies;
    $_SERVER['TRUSTED_PROXIES'] = $trustedProxies;

    try {
        config()->set('trustedproxy', file_exists($path) ? require $path : []);
    } finally {
        if ($previous === null) {
            putenv('TRUSTED_PROXIES');
            unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        } else {
            putenv('TRUSTED_PROXIES='.$previous);
            $_ENV['TRUSTED_PROXIES'] = $previous;
            $_SERVER['TRUSTED_PROXIES'] = $previous;
        }
    }
}

it('ignores a forwarded client address when no trusted proxies are configured', function () {
    expect(config('trustedproxy.proxies'))->toBeNull();

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->getJson('/_test/trusted-proxy-probe', [
            'X-Forwarded-For' => '203.0.113.7',
        ]);

    $response->assertOk();

    // The address the login limiters count on stays the address that actually
    // connected, so a forged header cannot be used to escape the counters.
    expect($response->json('ip'))->toBe('10.0.0.9');
});

it('ignores a forwarded protocol when no trusted proxies are configured', function () {
    expect(config('trustedproxy.proxies'))->toBeNull();

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->getJson('/_test/trusted-proxy-probe', [
            'X-Forwarded-Proto' => 'https',
        ]);

    $response->assertOk();
    expect($response->json('scheme'))->toBe('http');
    expect($response->json('secure'))->toBeFalse();
});

it('honours forwarded headers when TRUSTED_PROXIES is a wildcard', function () {
    bootTrustedProxyConfig('*');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->getJson('/_test/trusted-proxy-probe', [
            'X-Forwarded-For' => '203.0.113.7',
            'X-Forwarded-Proto' => 'https',
        ]);

    $response->assertOk();
    expect($response->json('ip'))->toBe('203.0.113.7');
    expect($response->json('scheme'))->toBe('https');
    expect($response->json('secure'))->toBeTrue();
});

it('honours forwarded headers when the calling address is listed in TRUSTED_PROXIES', function () {
    bootTrustedProxyConfig('198.51.100.4, 10.0.0.9');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->getJson('/_test/trusted-proxy-probe', [
            'X-Forwarded-For' => '203.0.113.7',
            'X-Forwarded-Proto' => 'https',
        ]);

    $response->assertOk();
    expect($response->json('ip'))->toBe('203.0.113.7');
    expect($response->json('scheme'))->toBe('https');
});

it('ignores a forwarded address from a caller outside the trusted list', function () {
    // The trusted list names a different proxy than the one that connects, so
    // the calling address is not trusted and its forwarded header must be ignored.
    bootTrustedProxyConfig('198.51.100.4');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->getJson('/_test/trusted-proxy-probe', [
            'X-Forwarded-For' => '203.0.113.7',
            'X-Forwarded-Proto' => 'https',
        ]);

    $response->assertOk();

    // The address that actually connected is kept, not the forged forwarded one,
    // and the forwarded protocol is ignored as well.
    expect($response->json('ip'))->toBe('10.0.0.9');
    expect($response->json('scheme'))->toBe('http');
    expect($response->json('secure'))->toBeFalse();
});

it('never trusts a forwarded host, even when TRUSTED_PROXIES is a wildcard', function () {
    // The wildcard trusts every caller, which is the widest the opt-in ever gets
    // (and what the on-forge/laravel-cloud autofallback sets). Even then the
    // header mask in bootstrap/app.php must keep X-Forwarded-Host untrusted.
    bootTrustedProxyConfig('*');

    $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
        ->getJson('/_test/trusted-proxy-probe', [
            'X-Forwarded-Host' => 'attacker.example',
            'X-Forwarded-For' => '203.0.113.7',
            'X-Forwarded-Proto' => 'https',
        ]);

    $response->assertOk();

    // For and Proto are still honoured, so the opt-in keeps doing its job.
    expect($response->json('ip'))->toBe('203.0.113.7');
    expect($response->json('scheme'))->toBe('https');

    // But the forged host never reaches the request host or a generated URL, so a
    // password-reset link cannot be poisoned through X-Forwarded-Host.
    expect($response->json('host'))->not->toBe('attacker.example');
    expect($response->json('url'))->not->toContain('attacker.example');
});
