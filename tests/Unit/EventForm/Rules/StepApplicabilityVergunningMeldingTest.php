<?php

/**
 * Wisselwerking tussen vergunning- en meldingstappen-applicability.
 *
 * Op stap 6 (Vergunningsplichtig scan) staat een Ja/Nee-vraag "wegen
 * afsluiten?". Het antwoord bepaalt hoe de wizard verder loopt:
 *
 *   - "Ja"  → het wordt een vergunning. De Melding-stap (7) is dan
 *             niet meer van toepassing.
 *   - "Nee" → het wordt een melding. De zeven vergunning-stappen
 *             (9 t/m 15) zijn dan niet meer van toepassing.
 *
 * Wanneer de organisator op stap 5 voor "Vooraankondiging" kiest, wint
 * die keuze: beide rules in deze test moeten dan niets doen omdat een
 * andere rule (Rule8f418d89) al meer stappen uitschakelt.
 */

use App\EventForm\Rules\MeldingSchakeltVergunningstappenUit;
use App\EventForm\Rules\RuleRegistry;
use App\EventForm\Rules\VergunningSchakeltMeldingUit;
use App\EventForm\Schema\Steps\MeldingStep;
use App\EventForm\Schema\Steps\VergunningaanvraagMaatregelenStep;
use App\EventForm\Schema\Steps\VergunningaanvraagOverigStep;
use App\EventForm\Schema\Steps\VergunningaanvraagVervolgvragenStep;
use App\EventForm\Schema\Steps\VergunningsaanvraagExtraActiviteitenStep;
use App\EventForm\Schema\Steps\VergunningsaanvraagVoorwerpenStep;
use App\EventForm\Schema\Steps\VergunningsaanvraagVoorzieningenStep;
use App\EventForm\Schema\Steps\Vragenboom2Step;
use App\EventForm\State\FormState;

beforeEach(function () {
    $this->vergunning = new VergunningSchakeltMeldingUit;
    $this->melding = new MeldingSchakeltVergunningstappenUit;
});

test('wegen afsluiten = Ja → Melding-stap wordt niet-applicable', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->vergunning->applies($state))->toBeTrue();
    expect($this->melding->applies($state))->toBeFalse();

    $this->vergunning->apply($state);
    expect($state->isStepApplicable(MeldingStep::UUID))->toBeFalse();
});

test('wegen afsluiten = Nee → alle vergunning-stappen worden niet-applicable', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);

    expect($this->melding->applies($state))->toBeTrue();
    expect($this->vergunning->applies($state))->toBeFalse();

    $this->melding->apply($state);

    // Alle 7 vergunning-stappen op niet-applicable.
    foreach ([
        Vragenboom2Step::UUID,
        VergunningaanvraagVervolgvragenStep::UUID,
        VergunningsaanvraagVoorzieningenStep::UUID,
        VergunningsaanvraagVoorwerpenStep::UUID,
        VergunningaanvraagMaatregelenStep::UUID,
        VergunningsaanvraagExtraActiviteitenStep::UUID,
        VergunningaanvraagOverigStep::UUID,
    ] as $uuid) {
        expect($state->isStepApplicable($uuid))->toBeFalse();
    }

    // Melding-stap blijft applicable (deze rule raakt 'm niet).
    expect($state->isStepApplicable(MeldingStep::UUID))->toBeTrue();
});

test('vooraankondiging-keuze schakelt beide rules uit', function () {
    // De vooraankondiging-rule (Rule8f418d89) is uitgebreider en mag
    // niet conflicteren met deze twee. Beide moeten daarom `applies`
    // op false zetten zodra `vooraankondiging` is gekozen.
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->vergunning->applies($state))->toBeFalse();
    expect($this->melding->applies($state))->toBeFalse();
});

test('beide rules zijn idempotent', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    $this->vergunning->apply($state);
    $this->vergunning->apply($state);
    $this->vergunning->apply($state);

    expect($state->isStepApplicable(MeldingStep::UUID))->toBeFalse();
});

test('rules zitten in RuleRegistry zodat de RulesEngine ze pakt', function () {
    $alle = RuleRegistry::all();
    expect($alle)->toContain(VergunningSchakeltMeldingUit::class);
    expect($alle)->toContain(MeldingSchakeltVergunningstappenUit::class);
});
