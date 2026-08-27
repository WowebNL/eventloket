<?php

declare(strict_types=1);

/**
 * `ExtraQuestions` is de enige plek die het padfilter van de aanvullende
 * vragen uitvoert. Zowel de wizardstap als de rapportage leunen erop, dus
 * een fout hier lekt een antwoord op een niet meer geldende vraag de PDF in.
 */

use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;
use App\EventForm\Support\ExtraQuestions;

/**
 * @param  list<array<string, mixed>>  $questions
 * @param  array<string, mixed>  $extraValues
 */
function fixtureStateWithExtraQuestions(array $questions, array $extraValues = []): FormState
{
    return new FormState(values: array_merge([
        'gemeenteVariabelen' => ['extra_questions' => $questions],
    ], $extraValues));
}

/** @param array<string, mixed> $overrides */
function fixtureExtraQuestion(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'order' => 1,
        'type' => 'text',
        'label' => 'Wat wilt u nog kwijt?',
        'helper_text' => null,
        'options' => [],
        'is_required' => false,
        'show_for_aanvraag_types' => [],
    ], $overrides);
}

test('geen extra_questions in de state → lege lijst', function () {
    expect(ExtraQuestions::forState(FormState::empty()))->toBe([])
        ->and(ExtraQuestions::hasAny(FormState::empty()))->toBeFalse();
});

test('een lege padselectie betekent: geldt voor ieder pad', function () {
    $state = fixtureStateWithExtraQuestions([fixtureExtraQuestion(['show_for_aanvraag_types' => []])]);

    expect(ExtraQuestions::forState($state))->toHaveCount(1)
        ->and(ExtraQuestions::hasAny($state))->toBeTrue();
});

test('een ontbrekende padselectie betekent ook: geldt voor ieder pad', function () {
    $question = fixtureExtraQuestion();
    unset($question['show_for_aanvraag_types']);

    expect(ExtraQuestions::forState(fixtureStateWithExtraQuestions([$question])))->toHaveCount(1);
});

test('een vraag alleen voor meldingen valt weg bij een vergunningaanvraag', function () {
    // Zonder ReportQuestion-antwoorden is `vergunning` de veilige default
    // van DetermineAanvraagType.
    $state = fixtureStateWithExtraQuestions([
        fixtureExtraQuestion(['id' => 1, 'show_for_aanvraag_types' => [DetermineAanvraagType::MELDING]]),
        fixtureExtraQuestion(['id' => 2, 'show_for_aanvraag_types' => [DetermineAanvraagType::VERGUNNING]]),
    ]);

    $questions = ExtraQuestions::forState($state);

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['id'])->toBe(2);
});

test('een vraag voor vooraankondiging verschijnt alleen op dat pad', function () {
    $question = fixtureExtraQuestion(['show_for_aanvraag_types' => [DetermineAanvraagType::VOORAANKONDIGING]]);

    $vergunning = fixtureStateWithExtraQuestions([$question]);
    $vooraankondiging = fixtureStateWithExtraQuestions([$question], [
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
    ]);

    expect(ExtraQuestions::forState($vergunning))->toBe([])
        ->and(ExtraQuestions::forState($vooraankondiging))->toHaveCount(1);
});

test('een vraag voor twee paden verschijnt op allebei', function () {
    $question = fixtureExtraQuestion([
        'show_for_aanvraag_types' => [DetermineAanvraagType::VERGUNNING, DetermineAanvraagType::VOORAANKONDIGING],
    ]);

    $vergunning = fixtureStateWithExtraQuestions([$question]);
    $vooraankondiging = fixtureStateWithExtraQuestions([$question], [
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
    ]);

    expect(ExtraQuestions::forState($vergunning))->toHaveCount(1)
        ->and(ExtraQuestions::forState($vooraankondiging))->toHaveCount(1);
});

test('vragen behouden de volgorde waarin ze in de state staan', function () {
    $state = fixtureStateWithExtraQuestions([
        fixtureExtraQuestion(['id' => 7, 'order' => 1]),
        fixtureExtraQuestion(['id' => 3, 'order' => 2]),
        fixtureExtraQuestion(['id' => 9, 'order' => 3]),
    ]);

    expect(array_column(ExtraQuestions::forState($state), 'id'))->toBe([7, 3, 9]);
});

test('vragen zonder id of type worden overgeslagen', function () {
    $state = fixtureStateWithExtraQuestions([
        ['label' => 'Geen id of type'],
        fixtureExtraQuestion(['id' => 5]),
        'geen array',
    ]);

    expect(ExtraQuestions::forState($state))->toHaveCount(1);
});

test('de veldsleutel krijgt het extraVraag_-voorvoegsel', function () {
    expect(ExtraQuestions::fieldKey(fixtureExtraQuestion(['id' => 42])))->toBe('extraVraag_42')
        ->and(ExtraQuestions::FIELD_PREFIX)->toBe('extraVraag_');
});
