<?php

declare(strict_types=1);

/**
 * Regressie-test voor de "switch-back-laat-stappen-uit-staan"-bug:
 *
 * Wanneer een gebruiker op stap 5 voor 'vooraankondiging' kiest, worden
 * 10 vervolgstappen via rules op niet-applicable gezet. Switcht 'ie
 * terug naar 'evenement', dan moeten die stappen WEER applicable
 * worden — exact zoals fields-die-verborgen-werden-door-een-rule
 * weer zichtbaar worden zodra de rule niet meer triggert.
 *
 * Voor de fix: `RulesEngine::evaluate*()` resette wel
 * `fieldHiddenOverrides` maar niet `stepApplicable`. Eenmaal op
 * `false` gezet, bleef een stap doorgestreept ook nadat de gebruiker
 * z'n antwoord ongedaan maakte.
 *
 * Na de fix: voor elke pass leegt de engine ook `stepApplicable`,
 * waarna alleen rules-die-nu-matchen het opnieuw mogen bijstellen.
 */

use App\EventForm\Rules\RulesEngine;
use App\EventForm\Schema\Steps\AanvraagOfMeldingStep;
use App\EventForm\Schema\Steps\BijlagenStep;
use App\EventForm\Schema\Steps\MeldingStep;
use App\EventForm\Schema\Steps\Vragenboom2Step;
use App\EventForm\State\FormState;

test('vooraankondiging-keuze ongedaan maken → uitgezette stappen zijn weer applicable', function () {
    $engine = app(RulesEngine::class);
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
    ]);

    // Eerste pass: 'vooraankondiging' → veel stappen op niet-applicable.
    $engine->evaluate($state);

    // Sanity-check dat de rule echt afgegaan is — anders test deze regressie
    // iets anders dan we denken.
    expect($state->isStepApplicable(MeldingStep::UUID))->toBeFalse(
        'Voorwaarde voor de regressie-test: vooraankondiging zou MeldingStep niet-applicable moeten maken'
    );
    expect($state->isStepApplicable(Vragenboom2Step::UUID))->toBeFalse();

    // Gebruiker switcht terug naar 'evenement'. Engine evalueert opnieuw.
    $state->setField('waarvoorWiltUEventloketGebruiken', 'evenement');
    $engine->evaluate($state);

    // Nu moeten de stappen weer applicable zijn. Geen rule heeft 'm
    // op false gehouden — vooraankondiging-rule fireert niet meer en
    // er is geen andere rule die hier 'false' zegt voor de melding-route
    // (wegen-afsluiten staat niet op 'Nee').
    expect($state->isStepApplicable(MeldingStep::UUID))->toBeTrue('MeldingStep moet weer applicable zijn');
    expect($state->isStepApplicable(Vragenboom2Step::UUID))->toBeTrue('Vragenboom2Step moet weer applicable zijn');
    expect($state->isStepApplicable(BijlagenStep::UUID))->toBeTrue('BijlagenStep moet weer applicable zijn');
    expect($state->isStepApplicable(AanvraagOfMeldingStep::UUID))->toBeTrue('AanvraagOfMeldingStep moet weer applicable zijn');
});
