<?php

return [

    /*
    |--------------------------------------------------------------------------
    | service-register /__version endpoint
    |--------------------------------------------------------------------------
    |
    | The service-register probes this app's running version over a signed
    | request to /__version. We verify each request with the register's public
    | ed25519 key. The public key is not a secret; the register holds the private
    | key. See the /__version route in routes/web.php.
    |
    | Read via config() (not env()) so the route keeps working after
    | `php artisan config:cache`, where env() outside config files returns null.
    | Set REGISTER_VERIFY_KEY per environment in .env, never commit the value.
    */

    'verify_key' => env('REGISTER_VERIFY_KEY'),

    // Path to the version file written at deploy time by `register:build-version`.
    // The /__version route reads the deploy-time facts (git tag/sha/branch) from
    // here instead of shelling out to git per request. It lives outside public/ so
    // it is never web-served, and is regenerated on every deploy.
    'version_file' => storage_path('app/version.json'),

    'timestamp_header' => 'X-Register-Timestamp',
    'signature_header' => 'X-Register-Signature',

    // Seconds of tolerance on the request timestamp, to stop replay.
    'clock_skew' => (int) env('REGISTER_CLOCK_SKEW', 60),

];
