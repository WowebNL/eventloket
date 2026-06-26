<?php

use App\Models\MunicipalityZgwConnection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

it('inherits unset values from the main connection', function () {
    Config::set('zgw.connections.main.bronorganisatie_rsin', '820151130');

    $connection = MunicipalityZgwConnection::factory()->create([
        'zaken_url' => 'https://gemeente-a.example.com/zaken/api/v1/',
        'catalogi_url' => null,
        'bronorganisatie_rsin' => null,
        'version' => null,
    ]);

    $config = $connection->buildConfig();

    expect($config['urls']['zaken'])->toBe('https://gemeente-a.example.com/zaken/api/v1/')
        // catalogi was not overridden, so it keeps main's value.
        ->and($config['urls']['catalogi'])->toBe(config('zgw.connections.main.urls.catalogi'))
        ->and($config['bronorganisatie_rsin'])->toBe('820151130')
        ->and($config['version'])->toBe(config('zgw.connections.main.version'));
});

it('stores the client secret encrypted and decrypts it in buildConfig', function () {
    $connection = MunicipalityZgwConnection::factory()->create([
        'client_secret' => 'gemeente-secret-at-least-32-bytes-long',
    ]);

    // The raw column is ciphertext, not the plaintext secret.
    $raw = DB::table('municipality_zgw_connections')->where('id', $connection->id)->value('client_secret');
    expect($raw)->not->toBe('gemeente-secret-at-least-32-bytes-long');

    expect($connection->fresh()->buildConfig()['client_secret'])->toBe('gemeente-secret-at-least-32-bytes-long');
});

it('rejects a client secret shorter than the minimum length', function () {
    $connection = MunicipalityZgwConnection::factory()->create([
        'client_secret' => 'too-short',
    ]);

    expect(fn () => $connection->buildConfig())->toThrow(RuntimeException::class);
});

it('overrides the vertrouwelijkheid map and allowed hosts when set', function () {
    $connection = MunicipalityZgwConnection::factory()->create([
        'allowed_hosts' => ['https://docs.gemeente-a.example.com'],
        'vertrouwelijkheid_map' => ['visibility' => ['organiser' => ['openbaar']]],
    ]);

    $config = $connection->buildConfig();

    expect($config['allowed_hosts'])->toBe(['https://docs.gemeente-a.example.com'])
        ->and($config['vertrouwelijkheid_map'])->toBe(['visibility' => ['organiser' => ['openbaar']]]);
});
