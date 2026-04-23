<?php

/**
 * `DetermineAanvraagType` moet exact dezelfde uitkomst geven als de
 * content-template op stap 17 "Type aanvraag" — dat is de kanonieke bron
 * die aan de aanvrager getoond wordt en dus leidend moet zijn voor de
 * registratie in OpenZaak.
 *
 * De template-expressie in OF:
 *   - `waarvoorWiltUEventloketGebruiken == 'vooraankondiging'`  → Vooraankondiging
 *   - `wordenErGebiedsontsluitingswegen...VoorHetVerkeer == 'Nee'`  → Melding
 *   - anders                                                         → Evenementenvergunning
 *
 * Let op de "anders"-tak: een lege of niet-ingevulde waarde op het
 * wegen-veld betekent dus automatisch *vergunning*. Dat lijkt streng,
 * maar volgt OF; melding is een expliciete keuze ("Nee, ik sluit geen
 * wegen af"), geen stilzwijgende default.
 */

use App\EventForm\State\FormState;
use App\EventForm\Submit\DetermineAanvraagType;

beforeEach(function () {
    $this->determine = new DetermineAanvraagType;
});

test('keuze "vooraankondiging" op stap 5 → aanvraag is een vooraankondiging', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
        // Zelfs als het "Nee" op wegen afsluiten is, wint de
        // vooraankondiging-keuze — die staat voor in de if-else-keten.
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);

    expect($this->determine->forState($state))->toBe(DetermineAanvraagType::VOORAANKONDIGING);
});

test('wegen afsluiten = Nee → melding', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);

    expect($this->determine->forState($state))->toBe(DetermineAanvraagType::MELDING);
});

test('wegen afsluiten = Ja → evenementenvergunning', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->determine->forState($state))->toBe(DetermineAanvraagType::VERGUNNING);
});

test('geen wegen-antwoord ingevuld → evenementenvergunning (OF-default)', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
    ]);

    expect($this->determine->forState($state))->toBe(DetermineAanvraagType::VERGUNNING);
});

test('volledig lege FormState → evenementenvergunning (OF-default)', function () {
    $state = FormState::empty();

    expect($this->determine->forState($state))->toBe(DetermineAanvraagType::VERGUNNING);
});
