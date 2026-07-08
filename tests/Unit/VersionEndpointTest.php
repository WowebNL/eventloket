<?php

use Illuminate\Support\Facades\File;

// The /__version route reads no database. It lives in the Unit suite so it runs
// without RefreshDatabase (which the Feature suite applies globally): no migrations
// and no database connection are needed to verify the signature gate and payload.

/** Set the register public key in config and return the matching secret key. */
function registerKeypair(): string
{
    $pair = sodium_crypto_sign_keypair();
    config(['register.verify_key' => base64_encode(sodium_crypto_sign_publickey($pair))]);

    return sodium_crypto_sign_secretkey($pair);
}

/** Point the route at a temp version file with the given contents. */
function useVersionFile(array $data): string
{
    $path = tempnam(sys_get_temp_dir(), 'ver').'.json';
    File::put($path, json_encode($data));
    config(['register.version_file' => $path]);

    return $path;
}

/** Build the signed request headers the register would send for /__version. */
function signedHeaders(string $secret, ?int $timestamp = null): array
{
    $timestamp ??= time();
    $signature = sodium_crypto_sign_detached($timestamp."\n".'/__version', $secret);

    return [
        'X-Register-Timestamp' => (string) $timestamp,
        'X-Register-Signature' => base64_encode($signature),
    ];
}

it('returns the version metadata for a valid signed request', function () {
    $secret = registerKeypair();

    $response = $this->get('/__version', signedHeaders($secret));

    $response->assertOk()
        ->assertJson([
            'php' => PHP_VERSION,
            'app_env' => 'testing',
        ])
        ->assertJsonStructure([
            'php', 'framework', 'git_tag', 'git_sha', 'composer_lock_hash',
            'app_env', 'branch', 'runtimes', 'checked_at',
        ]);
});

it('exposes the deploy-time version from the version file', function () {
    $secret = registerKeypair();
    useVersionFile([
        'git_tag' => 'v1.2.3',
        'git_sha' => 'abc123def456',
        'branch' => null,
        'nodejs' => '22.1.0',
        'deployed_at' => '2026-07-08T10:00:00+00:00',
    ]);

    $this->get('/__version', signedHeaders($secret))
        ->assertOk()
        ->assertJson([
            'git_tag' => 'v1.2.3',
            'git_sha' => 'abc123def456',
            'branch' => null,
        ])
        ->assertJsonPath('runtimes.nodejs', '22.1.0');
});

it('returns null git fields when the version file is missing', function () {
    $secret = registerKeypair();
    config(['register.version_file' => sys_get_temp_dir().'/no-such-version-'.uniqid().'.json']);

    $this->get('/__version', signedHeaders($secret))
        ->assertOk()
        ->assertJson(['git_tag' => null, 'git_sha' => null, 'branch' => null]);
});

it('register:build-version writes the version file from the given options', function () {
    $path = tempnam(sys_get_temp_dir(), 'ver').'.json';
    config(['register.version_file' => $path]);

    $this->artisan('register:build-version', [
        '--tag' => 'v9.9.9',
        '--sha' => 'deadbeef',
        '--branch' => 'main',
        '--node' => '20.11.0',
    ])->assertSuccessful();

    $data = json_decode(File::get($path), true);

    expect($data['git_tag'])->toBe('v9.9.9')
        ->and($data['git_sha'])->toBe('deadbeef')
        ->and($data['branch'])->toBe('main')
        ->and($data['nodejs'])->toBe('20.11.0')
        ->and($data)->toHaveKey('deployed_at');

    @unlink($path);
});

it('404s without a signature so the route is invisible', function () {
    registerKeypair();

    $this->get('/__version')->assertNotFound();
});

it('404s on an invalid signature', function () {
    registerKeypair();

    $headers = [
        'X-Register-Timestamp' => (string) time(),
        'X-Register-Signature' => base64_encode(str_repeat("\0", SODIUM_CRYPTO_SIGN_BYTES)),
    ];

    $this->get('/__version', $headers)->assertNotFound();
});

it('404s on a malformed signature with a valid timestamp, without throwing', function () {
    registerKeypair();
    $timestamp = (string) time();

    // Empty signature header: base64_decode('') is '' (not false), so the length
    // guard must catch it before sodium_crypto_sign_verify_detached throws.
    $this->get('/__version', ['X-Register-Timestamp' => $timestamp])->assertNotFound();

    // Short, non 64-byte signature.
    $this->get('/__version', [
        'X-Register-Timestamp' => $timestamp,
        'X-Register-Signature' => base64_encode('too-short'),
    ])->assertNotFound();

    // Not valid base64 at all.
    $this->get('/__version', [
        'X-Register-Timestamp' => $timestamp,
        'X-Register-Signature' => '!!!not-base64!!!',
    ])->assertNotFound();
});

it('404s on a stale timestamp even with an otherwise valid signature', function () {
    $secret = registerKeypair();

    $stale = time() - 3600;

    $this->get('/__version', signedHeaders($secret, $stale))->assertNotFound();
});

it('404s when no verify key is configured', function () {
    $secret = registerKeypair();
    config(['register.verify_key' => null]);

    $this->get('/__version', signedHeaders($secret))->assertNotFound();
});
