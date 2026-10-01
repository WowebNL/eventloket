<?php

use App\Enums\MunicipalityVariableType;
use App\EventForm\Services\MunicipalityVariablesService;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;

test('indieningstermijn variables exist for municipalities', function () {
    $municipality = Municipality::factory()->create();

    foreach (['indieningstermijn_a', 'indieningstermijn_b', 'indieningstermijn_c'] as $key) {
        MunicipalityVariable::factory()->create([
            'municipality_id' => $municipality->id,
            'key' => $key,
            'type' => MunicipalityVariableType::Number,
            'value' => 0,
            'is_default' => true,
        ]);
    }

    expect(MunicipalityVariable::where('municipality_id', $municipality->id)
        ->whereIn('key', ['indieningstermijn_a', 'indieningstermijn_b', 'indieningstermijn_c'])
        ->count()
    )->toBe(3);
});

test('indieningstermijn variables are exposed via MunicipalityVariablesService', function () {
    $municipality = Municipality::factory()->create();

    MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'indieningstermijn_a',
        'type' => MunicipalityVariableType::Number,
        'value' => 6,
        'is_default' => true,
    ]);

    MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'indieningstermijn_b',
        'type' => MunicipalityVariableType::Number,
        'value' => 10,
        'is_default' => true,
    ]);

    MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'indieningstermijn_c',
        'type' => MunicipalityVariableType::Number,
        'value' => 0,
        'is_default' => true,
    ]);

    $service = app(MunicipalityVariablesService::class);
    $map = $service->forMunicipalityAsKeyValue($municipality);

    expect($map['indieningstermijn_a'])->toBe(6.0);
    expect($map['indieningstermijn_b'])->toBe(10.0);
    expect($map['indieningstermijn_c'])->toBe(0.0);
});

test('indieningstermijn variables can be updated per municipality', function () {
    $municipality = Municipality::factory()->create();

    $variable = MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'indieningstermijn_c',
        'type' => MunicipalityVariableType::Number,
        'value' => 0,
        'is_default' => true,
    ]);

    $variable->update(['value' => 12]);

    $service = app(MunicipalityVariablesService::class);
    $map = $service->forMunicipalityAsKeyValue($municipality);

    expect($map['indieningstermijn_c'])->toBe(12.0);
});

test('de melding-indieningstermijn wordt per gemeente geseed en is weer terug te draaien', function () {
    $municipality = Municipality::factory()->create();

    $bestaat = fn (): bool => MunicipalityVariable::where('municipality_id', $municipality->id)
        ->where('key', 'indieningstermijn_melding')
        ->exists();

    expect($bestaat())->toBeFalse();

    $migration = require database_path('migrations/2026_09_23_100000_seed_indieningstermijn_melding_municipality_variable.php');
    $migration->up();

    $variable = MunicipalityVariable::where('municipality_id', $municipality->id)
        ->where('key', 'indieningstermijn_melding')
        ->first();

    expect($variable)->not->toBeNull()
        ->and($variable->type)->toBe(MunicipalityVariableType::Number)
        ->and($variable->formatted_value)->toBe(0.0)
        ->and($variable->is_default)->toBeTrue();

    // The key reaches the form along the same route as the three
    // classification deadlines.
    $variable->update(['value' => 4]);
    $map = app(MunicipalityVariablesService::class)->forMunicipalityAsKeyValue($municipality);

    expect($map['indieningstermijn_melding'])->toBe(4.0);

    // `down()` touches only its own key: the classification deadlines stay.
    MunicipalityVariable::factory()->create([
        'municipality_id' => $municipality->id,
        'key' => 'indieningstermijn_a',
        'type' => MunicipalityVariableType::Number,
        'value' => 6,
        'is_default' => true,
    ]);

    $migration->down();

    expect($bestaat())->toBeFalse()
        ->and(MunicipalityVariable::where('municipality_id', $municipality->id)
            ->where('key', 'indieningstermijn_a')
            ->exists())->toBeTrue();

    // And this is what "reversible" does and does not mean here. The model
    // soft-deletes, so after `down()` the row still exists and the variables
    // service reads it back with `withTrashed()`. This migration inherits
    // that from its predecessor; it is pinned here so nobody mistakes the
    // rollback for more than it is.
    expect(MunicipalityVariable::withTrashed()
        ->where('municipality_id', $municipality->id)
        ->where('key', 'indieningstermijn_melding')
        ->whereNotNull('deleted_at')
        ->exists())->toBeTrue();
});
