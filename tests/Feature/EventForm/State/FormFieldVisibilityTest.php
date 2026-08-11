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

test('contentRouteDoorkuistMeerdereGemeenteInfo verschijnt bij een route door ≥2 gemeenten met een bepaalde gemeente', function () {
    // Deze InfoText ("de route doorkruist gemeente X en Y, u vult in voor X,
    // de overige gemeenten worden geïnformeerd") stond permanent hidden: de
    // OF-rule eiste `userSelectGemeente11`, een veld dat in de OF-export niet
    // bestaat. De bedoelde conditie is route-door-≥2-gemeenten plus een
    // bepaalde gemeente.
    $state = new FormState;

    // Zonder route → default hidden.
    expect($state->isFieldHidden('contentRouteDoorkuistMeerdereGemeenteInfo'))->toBeNull();

    // Route door 1 gemeente → nog steeds hidden (dan valt er niets te melden).
    $state->setVariable('inGemeentenResponse', [
        'all' => [
            'items' => [['brk_identification' => 'GM0917', 'name' => 'Heerlen']],
            'object' => ['GM0917' => ['brk_identification' => 'GM0917', 'name' => 'Heerlen']],
        ],
        'line' => ['items' => [['brk_identification' => 'GM0917', 'name' => 'Heerlen']]],
    ]);
    expect($state->isFieldHidden('contentRouteDoorkuistMeerdereGemeenteInfo'))->toBeNull();
    // Met één gemeente doet `content200` het werk ("U gaat verder voor …").
    expect($state->isFieldHidden('content200'))->toBeFalse();

    // Route door 2 gemeenten, keuze gemaakt → tonen, en `content200` wijkt.
    $state->setVariable('inGemeentenResponse', [
        'all' => [
            'items' => [
                ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
            ],
            'object' => [
                'GM0917' => ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                'GM0935' => ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
            ],
        ],
        'line' => ['items' => [
            ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
            ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
        ]],
    ]);
    $state->setVariable('userSelectGemeente', 'GM0917');

    expect($state->isFieldHidden('contentRouteDoorkuistMeerdereGemeenteInfo'))
        ->toBeFalse('route door 2 gemeenten met keuze hoort de route-tekst te tonen')
        ->and($state->isFieldHidden('content200'))
        ->toBeNull('content200 wijkt voor de uitgebreidere route-tekst');
});

test('contentRouteDoorkuistMeerdereGemeenteInfo blijft hidden zolang er geen gemeente bepaald is', function () {
    // De tekst noemt de gemeente bij naam; zonder keuze bij ≥2 gemeenten is
    // `evenementInGemeente` null en zou er een lege naam in de zin staan.
    $state = new FormState;
    $state->setVariable('inGemeentenResponse', [
        'all' => [
            'items' => [
                ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
            ],
            'object' => [
                'GM0917' => ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                'GM0935' => ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
            ],
        ],
        'line' => ['items' => [
            ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
            ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
        ]],
    ]);

    expect($state->isFieldHidden('contentRouteDoorkuistMeerdereGemeenteInfo'))->toBeNull();
});
