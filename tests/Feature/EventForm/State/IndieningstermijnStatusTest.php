<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\EventForm\Support\SafeDateTime;
use Carbon\Carbon;

beforeEach(function () {
    $this->state = new FormState;

    foreach ([
        'watIsDeAantrekkingskrachtVanHetEvenement' => '0.5',
        'watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep' => '0.25',
        'isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid' => '0',
        'isEenDeelVanDeDoelgroepVerminderdZelfredzaam' => '0',
        'isErSprakeVanAanwezigheidVanRisicovolleActiviteiten' => '0',
        'watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep' => '0.5',
        'isErSprakeVanOvernachten' => '0',
        'isErGebruikVanAlcoholEnDrugs' => '0',
        'watIsHetAantalGelijktijdigAanwezigPersonen' => '0',
        'inWelkSeizoenVindtHetEvenementPlaats' => '0.25',
        'inWelkeLocatieVindtHetEvenementPlaats' => '0.25',
        'opWelkSoortOndergrondVindtHetEvenementPlaats' => '0.25',
        'watIsDeTijdsduurVanHetEvenement' => '0',
        'welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing' => '0',
    ] as $key => $value) {
        $this->state->setField($key, $value);
    }
});

test('returns null when risicoclassificatie is not yet determined', function () {
    $state = new FormState;

    $state->setVariable('gemeenteVariabelen', ['indieningstermijn_a' => 10]);
    $state->setField('EvenementStart', now()->addWeeks(12)->toIso8601String());

    expect($state->get('indieningstermijnStatus'))->toBeNull();
});

test('returns null when no indieningstermijn is configured for the classification', function () {
    $this->state->setVariable('gemeenteVariabelen', ['indieningstermijn_b' => 8]);
    $this->state->setField('EvenementStart', now()->addWeeks(12)->toIso8601String());

    // State has classification A, but only B is configured
    expect($this->state->get('risicoClassificatie'))->toBe('A');
    expect($this->state->get('indieningstermijnStatus'))->toBeNull();
});

test('returns null when indieningstermijn is zero', function () {
    $this->state->setVariable('gemeenteVariabelen', ['indieningstermijn_a' => 0]);
    $this->state->setField('EvenementStart', now()->addWeeks(12)->toIso8601String());

    expect($this->state->get('indieningstermijnStatus'))->toBeNull();
});

test('returns null when EvenementStart is not set', function () {
    $this->state->setVariable('gemeenteVariabelen', ['indieningstermijn_a' => 10]);

    expect($this->state->get('indieningstermijnStatus'))->toBeNull();
});

test('returns withinDeadline true when event start is far enough in the future', function () {
    Carbon::setTestNow('2026-06-01');

    $this->state->setVariable('gemeenteVariabelen', ['indieningstermijn_a' => 8]);
    $this->state->setField('EvenementStart', '2026-09-01T10:00:00+02:00');

    $status = $this->state->get('indieningstermijnStatus');

    expect($status)->not->toBeNull();
    expect($status['withinDeadline'])->toBeTrue();
    expect($status['weeks'])->toBe(8);
});

test('returns withinDeadline false when event start is too close', function () {
    Carbon::setTestNow('2026-06-01');

    $this->state->setVariable('gemeenteVariabelen', ['indieningstermijn_a' => 10]);
    $this->state->setField('EvenementStart', '2026-07-15T10:00:00+02:00');

    $status = $this->state->get('indieningstermijnStatus');

    expect($status)->not->toBeNull();
    expect($status['withinDeadline'])->toBeFalse();
    expect($status['weeks'])->toBe(10);
});

test('uses correct classification key for B and C', function () {
    Carbon::setTestNow('2026-06-01');

    // Pump up scores to get classification B (sum > 6, <= 9)
    $this->state->setField('watIsDeAantrekkingskrachtVanHetEvenement', '2');
    $this->state->setField('watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep', '1');
    $this->state->setField('isErSprakeVanAanwezigheidVanRisicovolleActiviteiten', '1');
    $this->state->setField('isErGebruikVanAlcoholEnDrugs', '1');
    $this->state->setField('watIsHetAantalGelijktijdigAanwezigPersonen', '0.75');
    $this->state->setField('watIsDeTijdsduurVanHetEvenement', '1');

    expect($this->state->get('risicoClassificatie'))->toBe('B');

    $this->state->setVariable('gemeenteVariabelen', ['indieningstermijn_b' => 12]);
    $this->state->setField('EvenementStart', '2026-10-01T10:00:00+02:00');

    $status = $this->state->get('indieningstermijnStatus');

    expect($status)->not->toBeNull();
    expect($status['withinDeadline'])->toBeTrue();
    expect($status['weeks'])->toBe(12);
});

test('weeksRemaining reflects weeks until event start', function () {
    Carbon::setTestNow('2026-06-01');

    $this->state->setVariable('gemeenteVariabelen', ['indieningstermijn_a' => 4]);
    $this->state->setField('EvenementStart', '2026-08-01T10:00:00+02:00');

    $status = $this->state->get('indieningstermijnStatus');

    expect($status['weeksRemaining'])->toBeGreaterThanOrEqual(8);
});

test('exactly on deadline boundary is within deadline', function () {
    Carbon::setTestNow('2026-06-01');

    $this->state->setVariable('gemeenteVariabelen', ['indieningstermijn_a' => 8]);
    // Event starts exactly 8 weeks from now
    $this->state->setField('EvenementStart', '2026-07-27T00:00:00+02:00');

    $status = $this->state->get('indieningstermijnStatus');

    expect($status['withinDeadline'])->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
|
| The report path has no risk classification, so until now someone filing
| one never saw a deadline at all. It has a municipality variable of its
| own, and `basedOn` tells the places that show the deadline where it came
| from.
|
*/

function meldingState(array $values = [], array $gemeenteVariabelen = []): FormState
{
    return new FormState(values: array_merge([
        'gemeenteVariabelen' => array_merge([
            'indieningstermijn_melding' => 4,
        ], $gemeenteVariabelen),
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
        'EvenementStart' => '2026-09-01T10:00:00+02:00',
    ], $values));
}

test('een melding krijgt een termijn uit de melding-variabele van de gemeente', function () {
    Carbon::setTestNow('2026-06-01');

    $status = meldingState()->get('indieningstermijnStatus');

    expect($status)->not->toBeNull()
        ->and($status['weeks'])->toBe(4)
        ->and($status['basedOn'])->toBe('report')
        ->and($status['withinDeadline'])->toBeTrue();
});

test('een melding buiten de termijn wordt als zodanig herkend', function () {
    Carbon::setTestNow('2026-08-20');

    $status = meldingState()->get('indieningstermijnStatus');

    expect($status)->not->toBeNull()
        ->and($status['basedOn'])->toBe('report')
        ->and($status['withinDeadline'])->toBeFalse();
});

test('een melding krijgt een termijn zonder dat er een risicoscan is ingevuld', function () {
    Carbon::setTestNow('2026-06-01');

    $state = meldingState();

    // This is exactly the gap being closed: no classification, and
    // therefore no deadline either.
    expect($state->get('risicoClassificatie'))->toBeNull()
        ->and($state->get('indieningstermijnStatus'))->not->toBeNull();
});

test('een melding zonder ingestelde termijn krijgt geen status', function () {
    Carbon::setTestNow('2026-06-01');

    $state = meldingState(gemeenteVariabelen: ['indieningstermijn_melding' => 0]);

    expect($state->get('indieningstermijnStatus'))->toBeNull();
});

test('het nieuwe vragensysteem levert dezelfde melding-termijn op', function () {
    Carbon::setTestNow('2026-06-01');

    $state = new FormState(values: [
        'gemeenteVariabelen' => [
            'use_new_report_questions' => true,
            'report_questions' => [
                ['id' => 1, 'order' => 1, 'question' => 'Vraag 1'],
                ['id' => 2, 'order' => 2, 'question' => 'Vraag 2'],
            ],
            'indieningstermijn_melding' => 4,
        ],
        'reportQuestion_1' => 'Ja',
        'reportQuestion_2' => 'Ja',
        'EvenementStart' => '2026-09-01T10:00:00+02:00',
    ]);

    $status = $state->get('indieningstermijnStatus');

    expect($status)->not->toBeNull()
        ->and($status['weeks'])->toBe(4)
        ->and($status['basedOn'])->toBe('report');
});

test('een lopende scan levert nog geen melding-termijn op', function () {
    Carbon::setTestNow('2026-06-01');

    $state = new FormState(values: [
        'gemeenteVariabelen' => [
            'use_new_report_questions' => true,
            'report_questions' => [
                ['id' => 1, 'order' => 1, 'question' => 'Vraag 1'],
                ['id' => 2, 'order' => 2, 'question' => 'Vraag 2'],
            ],
            'indieningstermijn_melding' => 4,
        ],
        'reportQuestion_1' => 'Ja',
        'EvenementStart' => '2026-09-01T10:00:00+02:00',
    ]);

    expect($state->get('indieningstermijnStatus'))->toBeNull();
});

test('een vergunningaanvraag blijft op de risicoclassificatie rusten', function () {
    Carbon::setTestNow('2026-06-01');

    // The same municipality also has a report deadline configured; it
    // must not take over on the permit path.
    $this->state->setVariable('gemeenteVariabelen', [
        'indieningstermijn_a' => 8,
        'indieningstermijn_melding' => 4,
    ]);
    $this->state->setField('EvenementStart', '2026-09-01T10:00:00+02:00');

    $status = $this->state->get('indieningstermijnStatus');

    expect($this->state->get('risicoClassificatie'))->toBe('A')
        ->and($status['weeks'])->toBe(8)
        ->and($status['basedOn'])->toBe('classification');
});

/*
|--------------------------------------------------------------------------
| An unreadable start date
|--------------------------------------------------------------------------
|
| The date parse used to sit behind the classification gate: without all
| fourteen risk-scan fields filled it was never reached. The report path has
| no classification, so it is now reachable from a step that re-renders on
| every keystroke and from the queued job that builds the PDF. An unreadable
| value has to mean "no deadline" there, never an exception.
|
*/

test('een onleesbare startdatum geeft geen termijn op het meldingpad', function () {
    Carbon::setTestNow('2026-06-01');

    foreach (['geen-datum', '20256-09-20T16:00', 'null', '   '] as $rommel) {
        $state = meldingState(['EvenementStart' => $rommel]);

        expect($state->get('indieningstermijnStatus'))->toBeNull("startdatum {$rommel}");
    }
});

test('een onleesbare startdatum geeft geen termijn op het vergunningpad', function () {
    Carbon::setTestNow('2026-06-01');

    $this->state->setVariable('gemeenteVariabelen', ['indieningstermijn_a' => 8]);

    foreach (['geen-datum', '20256-09-20T16:00'] as $rommel) {
        $this->state->setField('EvenementStart', $rommel);

        expect($this->state->get('indieningstermijnStatus'))->toBeNull("startdatum {$rommel}");
    }
});

test('een startdatum met tijdzone uit een prefill levert nog steeds een termijn', function () {
    // A form prefilled from an existing case gets the stored ISO string
    // straight into `EvenementStart`, timezone designator and all. That
    // shape is not on the SafeDateTime whitelist, so without a fallback the
    // deadline would silently disappear here.
    Carbon::setTestNow('2026-06-01');

    expect(SafeDateTime::parse('2026-09-01T10:00:00+02:00'))->toBeNull();

    $status = meldingState(['EvenementStart' => '2026-09-01T10:00:00+02:00'])->get('indieningstermijnStatus');

    expect($status)->not->toBeNull()
        ->and($status['weeks'])->toBe(4)
        ->and($status['basedOn'])->toBe('report');
});

test('een startdatum in picker-formaat levert een termijn', function () {
    Carbon::setTestNow('2026-06-01');

    $status = meldingState(['EvenementStart' => '2026-09-01T10:00'])->get('indieningstermijnStatus');

    expect($status)->not->toBeNull()
        ->and($status['basedOn'])->toBe('report');
});
