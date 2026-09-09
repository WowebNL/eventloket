<?php

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

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

it('audits an endpoint change with the causer and never logs the secret', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $connection = MunicipalityZgwConnection::factory()->create();

    $connection->update(['zaken_url' => 'https://new.gemeente-a.example.com/zaken/api/v1/']);

    $activity = Activity::query()
        ->where('subject_type', $connection->getMorphClass())
        ->where('subject_id', $connection->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    $properties = $activity?->properties->toArray() ?? [];

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toEqual($user->id)
        ->and(data_get($properties, 'attributes.zaken_url'))->toBe('https://new.gemeente-a.example.com/zaken/api/v1/')
        // The secret is excluded from the field log entirely.
        ->and(data_get($properties, 'attributes.client_secret'))->toBeNull()
        ->and(data_get($properties, 'old.client_secret'))->toBeNull();
});

it('audits a secret rotation as a redacted marker without the value', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $connection = MunicipalityZgwConnection::factory()->create();

    $connection->update(['client_secret' => 'a-rotated-secret-at-least-32-bytes-long']);

    $activity = Activity::query()
        ->where('subject_type', $connection->getMorphClass())
        ->where('subject_id', $connection->id)
        ->where('event', 'secret_rotated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toEqual($user->id)
        ->and(data_get($activity->properties->toArray(), 'municipality_id'))->toBe($connection->municipality_id);

    // The plaintext secret must never appear anywhere in the audit entry.
    expect(json_encode($activity->toArray()))->not->toContain('a-rotated-secret-at-least-32-bytes-long');
});

it('reports its active state from activated_at', function () {
    $inactive = MunicipalityZgwConnection::factory()->create();
    $active = MunicipalityZgwConnection::factory()->active()->create();

    expect($inactive->isActive())->toBeFalse()
        ->and($active->isActive())->toBeTrue();
});

it('deactivates and clears verification when a connection-critical field changes', function () {
    $connection = MunicipalityZgwConnection::factory()->active()->create();

    $connection->update(['zaken_url' => 'https://changed.gemeente-a.example.com/zaken/api/v1/']);

    $connection->refresh();

    expect($connection->activated_at)->toBeNull()
        ->and($connection->last_verified_at)->toBeNull();
});

it('deactivates when the client secret is rotated', function () {
    $connection = MunicipalityZgwConnection::factory()->active()->create();

    $connection->update(['client_secret' => 'a-rotated-secret-at-least-32-bytes-long']);

    expect($connection->fresh()->isActive())->toBeFalse();
});

it('stays active when only a cosmetic field changes', function () {
    $connection = MunicipalityZgwConnection::factory()->active()->create([
        'show_besluiten_tab' => true,
    ]);

    $connection->update(['show_besluiten_tab' => false]);

    expect($connection->fresh()->isActive())->toBeTrue();
});

it('does not deactivate itself when it is activated', function () {
    $connection = MunicipalityZgwConnection::factory()->create([
        'last_verified_at' => now(),
    ]);

    $connection->update(['activated_at' => now()]);

    expect($connection->fresh()->isActive())->toBeTrue();
});

it('only lets a managing user of the connection municipality activate it', function () {
    $ownMunicipality = Municipality::factory()->create();
    $otherMunicipality = Municipality::factory()->create();

    $beheerder = User::factory()->create(['role' => Role::KoppelingBeheerder]);
    $ownMunicipality->users()->attach($beheerder);
    $beheerder = User::findOrFail($beheerder->id);

    $ownConnection = MunicipalityZgwConnection::factory()->for($ownMunicipality)->create();
    $otherConnection = MunicipalityZgwConnection::factory()->for($otherMunicipality)->create();

    expect($beheerder->can('activate', $ownConnection))->toBeTrue()
        ->and($beheerder->can('activate', $otherConnection))->toBeFalse();
});
