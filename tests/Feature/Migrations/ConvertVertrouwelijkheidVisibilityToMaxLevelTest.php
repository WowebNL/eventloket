<?php

use App\Models\Municipality;
use App\Models\MunicipalityZgwConnection;
use Illuminate\Database\Migrations\Migration;

/**
 * Load the data migration under test. It is an anonymous class, so it has to be
 * required rather than instantiated by name.
 */
function visibilityMaxMigration(): Migration
{
    return require database_path('migrations/2026_08_21_090000_convert_vertrouwelijkheid_visibility_to_max_level.php');
}

function connectionWithMap(?array $map): MunicipalityZgwConnection
{
    return MunicipalityZgwConnection::factory()
        ->for(Municipality::factory())
        ->create(['vertrouwelijkheid_map' => $map]);
}

it('converts a stored set of levels to the maximum it expressed', function () {
    $connection = connectionWithMap([
        'visibility' => [
            'organiser' => ['openbaar'],
            'advisor' => ['openbaar', 'beperkt_openbaar'],
            'reviewer' => ['openbaar', 'beperkt_openbaar', 'intern'],
            'coordinator' => ['openbaar', 'beperkt_openbaar', 'intern'],
        ],
        'upload_default' => ['organiser' => 'openbaar', 'system' => 'openbaar'],
    ]);

    visibilityMaxMigration()->up();

    expect($connection->fresh()->vertrouwelijkheid_map)->toEqual([
        'visibility' => [
            'organiser' => 'openbaar',
            'advisor' => 'beperkt_openbaar',
            'reviewer' => 'intern',
            'coordinator' => 'intern',
        ],
        // Everything outside the visibility map is left untouched.
        'upload_default' => ['organiser' => 'openbaar', 'system' => 'openbaar'],
    ]);
});

it('takes the most confidential level regardless of the order in the set', function () {
    $connection = connectionWithMap([
        'visibility' => [
            'reviewer' => ['confidentieel', 'openbaar', 'vertrouwelijk'],
        ],
    ]);

    visibilityMaxMigration()->up();

    expect($connection->fresh()->vertrouwelijkheid_map['visibility']['reviewer'])
        ->toBe('confidentieel');
});

it('drops an entry that names no level of the standard', function () {
    $connection = connectionWithMap([
        'visibility' => ['organiser' => [], 'advisor' => ['nonsense']],
        'upload_default' => ['system' => 'openbaar'],
    ]);

    visibilityMaxMigration()->up();

    // A role without an entry falls back to the hardcoded defaults, which is
    // what an unreadable entry meant anyway.
    expect($connection->fresh()->vertrouwelijkheid_map)
        ->toEqual(['upload_default' => ['system' => 'openbaar']]);
});

it('leaves a map that already holds maxima untouched, and runs twice safely', function () {
    $map = [
        'visibility' => ['organiser' => 'openbaar', 'reviewer' => 'intern'],
        'upload_default' => ['system' => 'openbaar'],
    ];

    $connection = connectionWithMap($map);

    visibilityMaxMigration()->up();
    visibilityMaxMigration()->up();

    expect($connection->fresh()->vertrouwelijkheid_map)->toEqual($map);
});

it('ignores connections without a map', function () {
    $connection = connectionWithMap(null);

    visibilityMaxMigration()->up();

    expect($connection->fresh()->vertrouwelijkheid_map)->toBeNull();
});

it('expands a maximum back into the levels it covers on rollback', function () {
    $connection = connectionWithMap([
        'visibility' => ['organiser' => 'openbaar', 'reviewer' => 'intern'],
    ]);

    visibilityMaxMigration()->down();

    expect($connection->fresh()->vertrouwelijkheid_map['visibility'])->toEqual([
        'organiser' => ['openbaar'],
        'reviewer' => ['openbaar', 'beperkt_openbaar', 'intern'],
    ]);
});
