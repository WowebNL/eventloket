<?php

declare(strict_types=1);

/**
 * Equivalence-test voor de rules-engine-refactor: vergelijkt voor elke
 * gemigreerde afgeleide variabele de output van de oude RulesEngine
 * (die de variabele via `setVariable` schreef) met de nieuwe
 * `FormDerivedState`-methode (die 'm pure-functioneel berekent).
 *
 * Bedoeld om regressie te vangen tijdens de migratie. Per nieuwe
 * gemigreerde variabele voegen we hier een scenario toe.
 */

use App\EventForm\Rules\RulesEngine;
use App\EventForm\State\FormDerivedState;
use App\EventForm\State\FormState;

/**
 * Voor een gegeven raw FormState-snapshot:
 *   1) draai de oude RulesEngine en haal `setVariable`-resultaat
 *   2) bouw een verse FormState met dezelfde input en haal via
 *      `FormDerivedState::get()` het gemigreerde resultaat
 *   3) assert: identiek
 */
function assertDerivationEquivalent(string $variable, array $rawValues): void
{
    // Pad oud: laat de engine de variabele schrijven.
    $stateOud = new FormState(values: $rawValues);
    app(RulesEngine::class)->evaluate($stateOud);
    // Pak de waarde rechtstreeks uit de values-bag, NIET via get()
    // (die gaat namelijk al via FormDerivedState delegeren).
    $oudeWaarde = $stateOud->fields()[$variable] ?? null;

    // Pad nieuw: skip de engine, laat FormDerivedState rekenen.
    $stateNieuw = new FormState(values: $rawValues);
    $nieuweWaarde = (new FormDerivedState($stateNieuw))->get($variable);

    expect($nieuweWaarde)->toEqual(
        $oudeWaarde,
        "FormDerivedState::{$variable}() levert ander resultaat dan de oude engine voor: ".json_encode($rawValues, JSON_PRETTY_PRINT)
    );
}

test('evenementInGemeentenNamen — geen gemeenten gevonden → lege lijst', function () {
    assertDerivationEquivalent('evenementInGemeentenNamen', [
        'inGemeentenResponse' => ['all' => ['items' => []]],
    ]);
});

test('evenementInGemeentenNamen — één gemeente in items → lijst met één naam', function () {
    assertDerivationEquivalent('evenementInGemeentenNamen', [
        'inGemeentenResponse' => [
            'all' => ['items' => [
                ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
            ]],
        ],
    ]);
});

test('evenementInGemeentenNamen — meerdere gemeenten → alle namen in volgorde', function () {
    assertDerivationEquivalent('evenementInGemeentenNamen', [
        'inGemeentenResponse' => [
            'all' => ['items' => [
                ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
                ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                ['brk_identification' => 'GM1903', 'name' => 'Eijsden-Margraten'],
            ]],
        ],
    ]);
});

test('evenementInGemeentenNamen — geen inGemeentenResponse aanwezig → lege lijst', function () {
    assertDerivationEquivalent('evenementInGemeentenNamen', []);
});

test('binnenVeiligheidsregio — pakt all.within door', function () {
    foreach ([true, false, null] as $within) {
        assertDerivationEquivalent('binnenVeiligheidsregio', [
            'inGemeentenResponse' => ['all' => ['within' => $within, 'items' => []]],
        ]);
    }
});

test('gemeenten — pakt all.object door', function () {
    assertDerivationEquivalent('gemeenten', [
        'inGemeentenResponse' => [
            'all' => [
                'items' => [['brk_identification' => 'GM0935', 'name' => 'Maastricht']],
                'object' => ['GM0935' => ['brk_identification' => 'GM0935', 'name' => 'Maastricht']],
            ],
        ],
    ]);
});

test('routeDoorGemeentenNamen — namen uit line.items', function () {
    assertDerivationEquivalent('routeDoorGemeentenNamen', [
        'inGemeentenResponse' => [
            'line' => ['items' => [
                ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
                ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
            ]],
        ],
    ]);
});

test('evenementInGemeente — auto-pick bij precies één gevonden gemeente', function () {
    assertDerivationEquivalent('evenementInGemeente', [
        'inGemeentenResponse' => [
            'all' => ['items' => [['brk_identification' => 'GM0935', 'name' => 'Maastricht']]],
        ],
    ]);
});

test('evenementInGemeente — userSelectGemeente wint bij ≥2 gevonden', function () {
    assertDerivationEquivalent('evenementInGemeente', [
        'userSelectGemeente' => 'GM0917',
        'inGemeentenResponse' => [
            'all' => [
                'items' => [
                    ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
                    ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                ],
                'object' => [
                    'GM0935' => ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
                    'GM0917' => ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
                ],
            ],
        ],
    ]);
});

test('evenementInGemeente — niets gevonden, niets gekozen → null', function () {
    assertDerivationEquivalent('evenementInGemeente', [
        'inGemeentenResponse' => ['all' => ['items' => []]],
    ]);
});

test('alcoholvergunning — A5-checkbox aan → Ja', function () {
    assertDerivationEquivalent('alcoholvergunning', [
        'kruisAanWatVanToepassingIsVoorUwEvenementX' => ['A5' => true],
    ]);
});

test('alcoholvergunning — A5 uit of afwezig → null', function () {
    assertDerivationEquivalent('alcoholvergunning', []);
    assertDerivationEquivalent('alcoholvergunning', [
        'kruisAanWatVanToepassingIsVoorUwEvenementX' => ['A5' => false],
    ]);
});

test('isVergunningaanvraag — Nee op één scan-vraag → true', function () {
    foreach ([
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf',
        'meldingvraag1',
        'meldingvraag5',
    ] as $vraag) {
        assertDerivationEquivalent('isVergunningaanvraag', [$vraag => 'Nee']);
    }
});

test('isVergunningaanvraag — wegen-afsluiten Ja → true', function () {
    assertDerivationEquivalent('isVergunningaanvraag', [
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);
});

test('isVergunningaanvraag — alle scan-vragen Ja, wegen Nee → null (= geen vergunning)', function () {
    assertDerivationEquivalent('isVergunningaanvraag', [
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Ja',
        'meldingvraag1' => 'Ja',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);
});

test('risicoClassificatie — som ≤ 6 → A', function () {
    // 14 vragen, allemaal score 0 → som 0 → A.
    assertDerivationEquivalent('risicoClassificatie', [
        'watIsDeAantrekkingskrachtVanHetEvenement' => '0.5',
        'watIsDeBelangrijksteLeeftijdscategorieVanDeDoelgroep' => '0.5',
        'isErSprakeVanZanwezigheidVanPolitiekeAandachtEnOfMediageniekheid' => '0',
        'isEenDeelVanDeDoelgroepVerminderdZelfredzaam' => '0',
        'isErSprakeVanAanwezigheidVanRisicovolleActiviteiten' => '0',
        'watIsHetGrootsteDeelVanDeSamenstellingVanDeDoelgroep' => '0.5',
        'isErSprakeVanOvernachten' => '0',
        'isErGebruikVanAlcoholEnDrugs' => '0',
        'watIsHetAantalGelijktijdigAanwezigPersonen' => '0',
        'inWelkSeizoenVindtHetEvenementPlaats' => '0.5',
        'inWelkeLocatieVindtHetEvenementPlaats' => '0.25',
        'opWelkSoortOndergrondVindtHetEvenementPlaats' => '0.25',
        'watIsDeTijdsduurVanHetEvenement' => '0.5',
        'welkeBeschikbaarheidVanAanEnAfvoerwegenIsVanToepassing' => '0',
    ]);
});

test('confirmationtext — vooraankondiging → lege string', function () {
    assertDerivationEquivalent('confirmationtext', [
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
    ]);
});

test('confirmationtext — wegen Nee → meldings-bedankt', function () {
    assertDerivationEquivalent('confirmationtext', [
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);
});

test('FormState::get() pakt de gemigreerde waarde uit FormDerivedState, niet uit values-bag', function () {
    // Zelfs wanneer de values-bag een (oude) waarde bevat, moet
    // FormDerivedState winnen — dat is het hele punt van de migratie.
    $state = new FormState(values: [
        'evenementInGemeentenNamen' => ['oude-waarde-uit-engine'],
        'inGemeentenResponse' => [
            'all' => ['items' => [
                ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
            ]],
        ],
    ]);

    expect($state->get('evenementInGemeentenNamen'))->toBe(['Maastricht']);
});
