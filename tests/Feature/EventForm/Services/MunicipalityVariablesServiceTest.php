<?php

declare(strict_types=1);

use App\Enums\MunicipalityVariableType;
use App\EventForm\Services\MunicipalityVariablesService;
use App\Models\Municipality;
use App\Models\MunicipalityVariable;
use App\Models\ReportQuestion;

beforeEach(function () {
    $this->municipality = Municipality::factory()->create(['brk_identification' => 'GM0882']);
    // De MunicipalityObserver seedt 8 default-variabelen bij `created`
    // (aanwezigen, muziektijden, etc.). Voor deze service-unit-tests
    // wissen we ze zodat de assertions over een schone state werken.
    $this->municipality->variables()->forceDelete();
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

test('key-value map bevat use_new_report_questions=false en lege report_questions als gemeente nog niet migrated', function () {
    // Default: use_new_report_questions is false; report_questions blijft leeg
    // zodat het formulier het oude pad rendert.
    $this->municipality->update(['use_new_report_questions' => false]);

    $result = $this->service->forMunicipalityAsKeyValue($this->municipality);

    expect($result['use_new_report_questions'])->toBeFalse()
        ->and($result['report_questions'])->toBe([]);
});

test('key-value map bevat actieve report_questions in volgorde wanneer migrated', function () {
    // Een nieuwe Municipality krijgt via MunicipalityObserver 10 default
    // ReportQuestions auto-aangemaakt. Voor deze test wissen we die om
    // expliciete fixture-data te kunnen plaatsen + één inactieve te
    // bewijzen dat-ie weggefilterd wordt.
    $this->municipality->reportQuestions()->delete();
    $this->municipality->update(['use_new_report_questions' => true]);

    ReportQuestion::create([
        'municipality_id' => $this->municipality->id,
        'order' => 2,
        'question' => 'Tweede vraag',
        'is_active' => true,
    ]);
    ReportQuestion::create([
        'municipality_id' => $this->municipality->id,
        'order' => 1,
        'question' => 'Eerste vraag',
        'is_active' => true,
    ]);
    ReportQuestion::create([
        'municipality_id' => $this->municipality->id,
        'order' => 3,
        'question' => 'Inactieve vraag — moet eruit gefilterd',
        'is_active' => false,
    ]);

    $result = $this->service->forMunicipalityAsKeyValue($this->municipality);

    expect($result['use_new_report_questions'])->toBeTrue()
        ->and($result['report_questions'])->toHaveCount(2)
        ->and($result['report_questions'][0]['order'])->toBe(1)
        ->and($result['report_questions'][0]['question'])->toBe('Eerste vraag')
        ->and($result['report_questions'][1]['order'])->toBe(2)
        ->and($result['report_questions'][1]['question'])->toBe('Tweede vraag');
});
