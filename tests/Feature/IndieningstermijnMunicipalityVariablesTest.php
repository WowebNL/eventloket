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
