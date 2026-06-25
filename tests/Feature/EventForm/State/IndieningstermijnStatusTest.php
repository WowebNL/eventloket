<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
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
