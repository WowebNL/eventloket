<?php

declare(strict_types=1);

/**
 * De stap "Aanvullende vragen" bouwt z'n componenten uit de per-gemeente
 * ingestelde vragenlijst in de FormState, niet uit een vaste schema-array.
 */

use App\EventForm\Schema\Steps\AanvullendeVragenStep;
use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;

/**
 * @param  list<array<string, mixed>>  $questions
 * @param  array<string, mixed>  $extraValues
 */
function stepStateWithQuestions(array $questions, array $extraValues = []): FormState
{
    return new FormState(values: array_merge([
        'gemeenteVariabelen' => ['extra_questions' => $questions],
    ], $extraValues));
}

test('geen vragen → geen componenten', function () {
    expect(AanvullendeVragenStep::questionComponents(FormState::empty()))->toBe([]);
});

test('elk vraagtype levert het bijbehorende component op', function () {
    $state = stepStateWithQuestions([
        ['id' => 1, 'type' => 'text', 'label' => 'Toelichting?', 'options' => []],
        ['id' => 2, 'type' => 'radio', 'label' => 'Ja of nee?', 'options' => ['Ja', 'Nee']],
        ['id' => 3, 'type' => 'checkboxes', 'label' => 'Wat past?', 'options' => ['A', 'B']],
    ]);

    $components = AanvullendeVragenStep::questionComponents($state);

    expect($components)->toHaveCount(3)
        ->and($components[0])->toBeInstanceOf(Textarea::class)
        ->and($components[1])->toBeInstanceOf(Radio::class)
        ->and($components[2])->toBeInstanceOf(CheckboxList::class)
        ->and(array_map(fn ($c) => $c->getName(), $components))
        ->toBe(['extraVraag_1', 'extraVraag_2', 'extraVraag_3']);
});

test('een keuzevraag zonder opties wordt weggelaten in plaats van leeg getoond', function () {
    $state = stepStateWithQuestions([
        ['id' => 1, 'type' => 'radio', 'label' => 'Zonder opties?', 'options' => []],
        ['id' => 2, 'type' => 'text', 'label' => 'Wel goed', 'options' => []],
    ]);

    $components = AanvullendeVragenStep::questionComponents($state);

    expect($components)->toHaveCount(1)
        ->and($components[0]->getName())->toBe('extraVraag_2');
});

test('een onbekend vraagtype wordt overgeslagen', function () {
    $state = stepStateWithQuestions([
        ['id' => 1, 'type' => 'datum', 'label' => 'Wanneer?', 'options' => []],
    ]);

    expect(AanvullendeVragenStep::questionComponents($state))->toBe([]);
});

test('het padfilter geldt ook bij het bouwen van de componenten', function () {
    $state = stepStateWithQuestions([
        [
            'id' => 1,
            'type' => 'text',
            'label' => 'Alleen bij melding',
            'options' => [],
            'show_for_aanvraag_types' => [DetermineAanvraagType::MELDING],
        ],
        ['id' => 2, 'type' => 'text', 'label' => 'Altijd', 'options' => []],
    ]);

    $components = AanvullendeVragenStep::questionComponents($state);

    expect($components)->toHaveCount(1)
        ->and($components[0]->getName())->toBe('extraVraag_2');
});
