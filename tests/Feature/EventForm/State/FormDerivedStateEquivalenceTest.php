<?php

declare(strict_types=1);

/**
 * Unit-tests voor de gemigreerde derivaties in `FormDerivedState`.
 * Per scenario: een raw FormState-snapshot + een verwachte
 * computed-waarde, gebaseerd op de oorspronkelijke OF-rule-logica.
 *
 * Was vroeger een directe vergelijking tussen oude RulesEngine en
 * nieuwe FormDerivedState; sinds de gemigreerde rules door de engine
 * worden overgeslagen heeft die vergelijking geen zin meer — we
 * checken nu rechtstreeks of de pure-functionele methodes het
 * verwachte resultaat opleveren.
 */

use App\EventForm\State\FormDerivedState;
use App\EventForm\State\FormState;

function derive(string $key, array $rawValues): mixed
{
    return (new FormDerivedState(new FormState(values: $rawValues)))->get($key);
}

test('evenementInGemeentenNamen — geen gemeenten gevonden → lege lijst', function () {
    expect(derive('evenementInGemeentenNamen', [
        'inGemeentenResponse' => ['all' => ['items' => []]],
    ]))->toBe([]);
});

test('evenementInGemeentenNamen — één gemeente in items → lijst met één naam', function () {
    expect(derive('evenementInGemeentenNamen', [
        'inGemeentenResponse' => ['all' => ['items' => [
            ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
        ]]],
    ]))->toBe(['Maastricht']);
});

test('evenementInGemeentenNamen — meerdere gemeenten → alle namen in volgorde', function () {
    expect(derive('evenementInGemeentenNamen', [
        'inGemeentenResponse' => ['all' => ['items' => [
            ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
            ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
            ['brk_identification' => 'GM1903', 'name' => 'Eijsden-Margraten'],
        ]]],
    ]))->toBe(['Maastricht', 'Heerlen', 'Eijsden-Margraten']);
});

test('evenementInGemeentenNamen — geen inGemeentenResponse → lege lijst', function () {
    expect(derive('evenementInGemeentenNamen', []))->toBe([]);
});

test('binnenVeiligheidsregio — pakt all.within door (true/false/null)', function () {
    foreach ([true, false, null] as $within) {
        expect(derive('binnenVeiligheidsregio', [
            'inGemeentenResponse' => ['all' => ['within' => $within, 'items' => []]],
        ]))->toBe($within);
    }
});

test('gemeenten — pakt all.object door', function () {
    expect(derive('gemeenten', [
        'inGemeentenResponse' => ['all' => [
            'items' => [['brk_identification' => 'GM0935', 'name' => 'Maastricht']],
            'object' => ['GM0935' => ['brk_identification' => 'GM0935', 'name' => 'Maastricht']],
        ]],
    ]))->toBe(['GM0935' => ['brk_identification' => 'GM0935', 'name' => 'Maastricht']]);
});

test('routeDoorGemeentenNamen — namen uit line.items', function () {
    expect(derive('routeDoorGemeentenNamen', [
        'inGemeentenResponse' => ['line' => ['items' => [
            ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
            ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
        ]]],
    ]))->toBe(['Maastricht', 'Heerlen']);
});

test('evenementInGemeente — auto-pick bij precies één gevonden gemeente', function () {
    expect(derive('evenementInGemeente', [
        'inGemeentenResponse' => ['all' => ['items' => [
            ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
        ]]],
    ]))->toBe(['brk_identification' => 'GM0935', 'name' => 'Maastricht']);
});

test('evenementInGemeente — userSelectGemeente wint bij ≥2 gevonden', function () {
    expect(derive('evenementInGemeente', [
        'userSelectGemeente' => 'GM0917',
        'inGemeentenResponse' => ['all' => [
            'items' => [
                ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
                ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
            ],
            'object' => [
                'GM0935' => ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
                'GM0917' => ['brk_identification' => 'GM0917', 'name' => 'Heerlen'],
            ],
        ]],
    ]))->toBe(['brk_identification' => 'GM0917', 'name' => 'Heerlen']);
});

test('evenementInGemeente — niets gevonden, niets gekozen → null', function () {
    expect(derive('evenementInGemeente', [
        'inGemeentenResponse' => ['all' => ['items' => []]],
    ]))->toBeNull();
});

test('alcoholvergunning — A5 aan → Ja, anders null', function () {
    expect(derive('alcoholvergunning', [
        'kruisAanWatVanToepassingIsVoorUwEvenementX' => ['A5' => true],
    ]))->toBe('Ja');
    expect(derive('alcoholvergunning', []))->toBeNull();
    expect(derive('alcoholvergunning', [
        'kruisAanWatVanToepassingIsVoorUwEvenementX' => ['A5' => false],
    ]))->toBeNull();
});

test('isVergunningaanvraag — Nee op één scan-vraag → true', function () {
    foreach (['isHetAantalAanwezigenBijUwEvenementMinderDanSdf', 'meldingvraag1', 'meldingvraag5'] as $vraag) {
        expect(derive('isVergunningaanvraag', [$vraag => 'Nee']))->toBeTrue();
    }
});

test('isVergunningaanvraag — wegen-afsluiten Ja → true', function () {
    expect(derive('isVergunningaanvraag', [
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]))->toBeTrue();
});

test('isVergunningaanvraag — alle scan-vragen Ja, wegen Nee → null (= geen vergunning)', function () {
    expect(derive('isVergunningaanvraag', [
        'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Ja',
        'meldingvraag1' => 'Ja',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]))->toBeNull();
});

test('risicoClassificatie — som ≤ 6 → A', function () {
    expect(derive('risicoClassificatie', [
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
    ]))->toBe('A');
});

test('confirmationtext — vooraankondiging → lege string', function () {
    expect(derive('confirmationtext', [
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
    ]))->toBe('');
});

test('confirmationtext — wegen Nee → meldings-bedankt', function () {
    expect(derive('confirmationtext', [
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]))->toBe('Bedankt voor het invullen van de details voor de melding van uw evenement.');
});

test('FormState::get() pakt de gemigreerde waarde uit FormDerivedState, niet uit values-bag', function () {
    // Zelfs wanneer de values-bag een (oude) waarde bevat, moet
    // FormDerivedState winnen — dat is het hele punt van de migratie.
    $state = new FormState(values: [
        'evenementInGemeentenNamen' => ['oude-waarde-uit-engine'],
        'inGemeentenResponse' => ['all' => ['items' => [
            ['brk_identification' => 'GM0935', 'name' => 'Maastricht'],
        ]]],
    ]);

    expect($state->get('evenementInGemeentenNamen'))->toBe(['Maastricht']);
});
