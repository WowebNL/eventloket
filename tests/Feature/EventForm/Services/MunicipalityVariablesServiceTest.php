<?php

declare(strict_types=1);

use App\Enums\MunicipalityVariableType;
use App\EventForm\Services\MunicipalityVariablesService;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create(['brk_identification' => 'GM0882']);
    $this->service = new MunicipalityVariablesService;
});

test('returns empty array when no variables', function () {
    $result = $this->service->forMunicipality($this->municipality);

    expect($result)->toBe([]);
});

test('returns list of variables with formatted fields', function () {
    MunicipalityVariable::factory()->create([
        'municipality_id' => $this->municipality->id,
        'key' => 'aanwezigen',
        'name' => 'Max aanwezigen',
        'type' => MunicipalityVariableType::Number,
        'value' => 500,
    ]);

    $result = $this->service->forMunicipality($this->municipality);

    expect($result)->toHaveCount(1)
        ->and($result[0])->toHaveKeys(['id', 'name', 'key', 'type', 'value', 'is_default']);
});

test('key-value map flattens for direct use in FormState', function () {
    MunicipalityVariable::factory()->create([
        'municipality_id' => $this->municipality->id,
        'key' => 'aanwezigen',
        'type' => MunicipalityVariableType::Number,
        'value' => 500,
    ]);
    MunicipalityVariable::factory()->create([
        'municipality_id' => $this->municipality->id,
        'key' => 'melding_alcohol',
        'type' => MunicipalityVariableType::Text,
        'value' => 'Geen alcohol voor 18',
    ]);

    $result = $this->service->forMunicipalityAsKeyValue($this->municipality);

    expect($result)->toHaveKeys(['aanwezigen', 'melding_alcohol']);
});
