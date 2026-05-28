<?php

declare(strict_types=1);

/**
 * Tests voor `FormFieldVisibility` — de pure-functionele tegenhanger
 * van de oude `setFieldHidden`-rules. Toggle-stijl: state heen-en-weer
 * wisselen leidt na elke wijziging tot het juiste antwoord. Vangt het
 * type bug op dat we vroeger in stepApplicable hadden ("rule fired,
 * conditie weg, state bleef hangen") — pure-functioneel is by
 * construction toggle-veilig, dus dit is een regression-net.
 */

use App\EventForm\State\FormState;

test('NotWithin toggle: false → null → false leidt elke keer tot juiste zichtbaarheid', function () {
    $state = new FormState;

    // Initieel: niets ingevuld → fall-through naar default = hidden.
    expect($state->isFieldHidden('NotWithin'))->toBeNull();

    // Polygon getekend, valt buiten Eventloket → binnenVeiligheidsregio
    // is false → veld moet TONEN.
    $state->setVariable('inGemeentenResponse', ['all' => ['within' => false, 'items' => []]]);
    expect($state->isFieldHidden('NotWithin'))->toBeFalse('NotWithin moet tonen wanneer (deel van) polygon buiten regio valt');

    // Gebruiker tekent een nieuw polygon dat WEL binnen valt →
    // binnenVeiligheidsregio is true → veld moet weer hidden zijn.
    $state->setVariable('inGemeentenResponse', ['all' => ['within' => true, 'items' => [['brk_identification' => 'GM0935', 'name' => 'Maastricht']]]]);
    expect($state->isFieldHidden('NotWithin'))->toBeNull('NotWithin moet weer default-hidden zijn na binnen-regio polygon');

    // En weer terug — moet het opnieuw correct werken zonder dat de
    // eerste 'show' is blijven hangen.
    $state->setVariable('inGemeentenResponse', ['all' => ['within' => false, 'items' => []]]);
    expect($state->isFieldHidden('NotWithin'))->toBeFalse('NotWithin moet weer tonen na opnieuw buiten-regio polygon');
});

test('NotWithin: zonder inGemeentenResponse → null (= default hidden)', function () {
    $state = new FormState;

    expect($state->isFieldHidden('NotWithin'))->toBeNull();
});

test('evenmentenInDeBuurtContent verschijnt zodra evenementenInDeGemeente truthy is', function () {
    // Op de Tijden-stap staat een InfoText `evenmentenInDeBuurtContent`
    // die de organisator waarschuwt voor overlappende evenementen.
    // FormFieldVisibility zegt: tonen (= false) wanneer
    // `evenementenInDeGemeente` truthy is, anders default hidden.
    $state = new FormState;

    // Zonder data → fall-through naar default hidden.
    expect($state->isFieldHidden('evenmentenInDeBuurtContent'))->toBeNull();

    // ServiceFetcher heeft een lijst overlappende evenementen
    // teruggekregen → InfoText moet TONEN.
    $state->setVariable('evenementenInDeGemeente', 'Zomerfestival, Buurtloop');
    expect($state->isFieldHidden('evenmentenInDeBuurtContent'))
        ->toBeFalse('met overlappende evenementen hoort de waarschuwing zichtbaar');

    // Lege string (geen overlap) → niet tonen.
    $state->setVariable('evenementenInDeGemeente', '');
    expect($state->isFieldHidden('evenmentenInDeBuurtContent'))
        ->toBeNull('zonder overlap is de waarschuwing default-hidden');
});
