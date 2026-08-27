<?php

declare(strict_types=1);

/**
 * De stap "Aanvullende vragen" bouwt z'n schema uit een Closure. De
 * reflectie-walk van `SubmissionReport` ziet daar niets van, dus zonder de
 * eigen tak in `extractEntries()` zouden deze antwoorden noch in de
 * samenvatting noch in de PDF terechtkomen.
 */

use App\EventForm\Reporting\SubmissionReport;
use App\EventForm\Schema\Steps\AanvullendeVragenStep;
use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;

/**
 * @param  list<array<string, mixed>>  $questions
 * @param  array<string, mixed>  $answers
 */
function reportStateWithExtraQuestions(array $questions, array $answers = []): FormState
{
    return new FormState(values: array_merge([
        'gemeenteVariabelen' => ['extra_questions' => $questions],
    ], $answers));
}

/** @param list<array{title: string, entries: mixed}> $sections */
function extraQuestionEntries(array $sections): array
{
    return $sections === [] ? [] : $sections[0]['entries'];
}

test('geen aanvullende vragen → geen sectie in het rapport', function () {
    $sections = app(SubmissionReport::class)->build(
        FormState::empty(),
        [AanvullendeVragenStep::make()],
    );

    expect($sections)->toBe([]);
});

test('beantwoorde vragen komen als sectie met label en waarde terug', function () {
    $state = reportStateWithExtraQuestions(
        [
            ['id' => 1, 'type' => 'text', 'label' => 'Wat wilt u nog kwijt?', 'options' => []],
            ['id' => 2, 'type' => 'radio', 'label' => 'Komt er een tent?', 'options' => ['Ja', 'Nee']],
        ],
        [
            'extraVraag_1' => 'Er komt een springkussen.',
            'extraVraag_2' => 'Ja',
        ],
    );

    $sections = app(SubmissionReport::class)->build($state, [AanvullendeVragenStep::make()]);

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['title'])->toBe('Aanvullende vragen')
        ->and($sections[0]['entries'])->toBe([
            ['label' => 'Wat wilt u nog kwijt?', 'value' => 'Er komt een springkussen.'],
            ['label' => 'Komt er een tent?', 'value' => 'Ja'],
        ]);
});

test('een checkboxes-antwoord met meerdere waarden wordt komma-gescheiden getoond', function () {
    $state = reportStateWithExtraQuestions(
        [['id' => 5, 'type' => 'checkboxes', 'label' => 'Welke voorzieningen?', 'options' => ['Toiletten', 'EHBO', 'Beveiliging']]],
        ['extraVraag_5' => ['Toiletten', 'Beveiliging']],
    );

    $entries = extraQuestionEntries(app(SubmissionReport::class)->build($state, [AanvullendeVragenStep::make()]));

    expect($entries)->toBe([
        ['label' => 'Welke voorzieningen?', 'value' => 'Toiletten, Beveiliging'],
    ]);
});

test('onbeantwoorde vragen verschijnen niet in het rapport', function () {
    $state = reportStateWithExtraQuestions(
        [
            ['id' => 1, 'type' => 'text', 'label' => 'Beantwoord', 'options' => []],
            ['id' => 2, 'type' => 'text', 'label' => 'Niet beantwoord', 'options' => []],
        ],
        ['extraVraag_1' => 'Wel iets'],
    );

    $entries = extraQuestionEntries(app(SubmissionReport::class)->build($state, [AanvullendeVragenStep::make()]));

    expect($entries)->toBe([['label' => 'Beantwoord', 'value' => 'Wel iets']]);
});

test('een antwoord op een uitgefilterde vraag belandt niet in het rapport', function () {
    // De organisator beantwoordde de melding-vraag en zette daarna het pad
    // om naar een vergunningaanvraag. Het antwoord blijft in de state staan;
    // de walk kijkt niet naar `hidden`, dus het padfilter moet hier opnieuw
    // toegepast worden.
    $state = reportStateWithExtraQuestions(
        [
            [
                'id' => 1,
                'type' => 'text',
                'label' => 'Alleen bij een melding',
                'options' => [],
                'show_for_aanvraag_types' => [DetermineAanvraagType::MELDING],
            ],
        ],
        ['extraVraag_1' => 'Oud antwoord'],
    );

    $sections = app(SubmissionReport::class)->build($state, [AanvullendeVragenStep::make()]);

    expect($sections)->toBe([]);
});

test('een meerkeuzevraag met een boolean-waarde levert geen "1" op in het rapport', function () {
    // Zo'n waarde ontstaat wanneer Livewire een checkbox-groep als één
    // boolean bindt (state was geen array). `(string) true` is "1", en dat
    // zou als antwoord in de samenvatting en de PDF belanden.
    $state = reportStateWithExtraQuestions(
        [['id' => 1, 'type' => 'checkboxes', 'label' => 'Welke voorzieningen?', 'options' => ['A', 'B']]],
        ['extraVraag_1' => true],
    );

    expect(app(SubmissionReport::class)->build($state, [AanvullendeVragenStep::make()]))->toBe([]);
});

test('een tekstblok met een boolean-waarde levert ook geen "1" op', function () {
    $state = reportStateWithExtraQuestions(
        [['id' => 1, 'type' => 'text', 'label' => 'Toelichting?', 'options' => []]],
        ['extraVraag_1' => true],
    );

    expect(app(SubmissionReport::class)->build($state, [AanvullendeVragenStep::make()]))->toBe([]);
});

test('een vraag zonder label verschijnt niet in het rapport', function () {
    $state = reportStateWithExtraQuestions(
        [['id' => 1, 'type' => 'text', 'label' => '   ', 'options' => []]],
        ['extraVraag_1' => 'Antwoord zonder vraag'],
    );

    expect(app(SubmissionReport::class)->build($state, [AanvullendeVragenStep::make()]))->toBe([]);
});
