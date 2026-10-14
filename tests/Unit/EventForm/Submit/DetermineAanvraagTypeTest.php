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

use App\Enums\ZaaktypeRole;
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

    expect($this->determine->forState($state))->toBe(ZaaktypeRole::Vooraankondiging);
});

test('wegen afsluiten = Nee → melding', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
    ]);

    expect($this->determine->forState($state))->toBe(ZaaktypeRole::Melding);
});

test('wegen afsluiten = Ja → evenementenvergunning', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
        'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
    ]);

    expect($this->determine->forState($state))->toBe(ZaaktypeRole::Vergunning);
});

test('geen wegen-antwoord ingevuld → evenementenvergunning (OF-default)', function () {
    $state = new FormState(values: [
        'waarvoorWiltUEventloketGebruiken' => 'evenement',
    ]);

    expect($this->determine->forState($state))->toBe(ZaaktypeRole::Vergunning);
});

test('volledig lege FormState → evenementenvergunning (OF-default)', function () {
    $state = FormState::empty();

    expect($this->determine->forState($state))->toBe(ZaaktypeRole::Vergunning);
});

describe('Nieuw ReportQuestion-pad (use_new_report_questions = true)', function () {
    function reportQuestionState(array $extra = []): FormState
    {
        return new FormState(values: array_merge([
            'waarvoorWiltUEventloketGebruiken' => 'evenement',
            'gemeenteVariabelen' => [
                'use_new_report_questions' => true,
                'report_questions' => [
                    ['id' => 1, 'order' => 1, 'question' => 'Vraag 1'],
                    ['id' => 2, 'order' => 2, 'question' => 'Vraag 2'],
                ],
            ],
        ], $extra));
    }

    test('alle reportQuestions Ja → melding', function () {
        // Identieke semantiek als de uitkomst-tekst op de scan-stap:
        // alle vragen Ja = scan compleet zonder knock-out = melding-pad.
        $state = reportQuestionState([
            'reportQuestion_1' => 'Ja',
            'reportQuestion_2' => 'Ja',
        ]);

        expect((new DetermineAanvraagType)->forState($state))->toBe(ZaaktypeRole::Melding);
    });

    test('één reportQuestion Nee → vergunning', function () {
        $state = reportQuestionState([
            'reportQuestion_1' => 'Nee',
        ]);

        expect((new DetermineAanvraagType)->forState($state))->toBe(ZaaktypeRole::Vergunning);
    });

    test('halve cascade (geen Nee maar nog niet alle Ja) → vergunning (default)', function () {
        $state = reportQuestionState([
            'reportQuestion_1' => 'Ja',
            // reportQuestion_2 nog niet beantwoord
        ]);

        expect((new DetermineAanvraagType)->forState($state))->toBe(ZaaktypeRole::Vergunning);
    });

    test('geen antwoorden ingevuld → vergunning', function () {
        $state = reportQuestionState();

        expect((new DetermineAanvraagType)->forState($state))->toBe(ZaaktypeRole::Vergunning);
    });

    test('vooraankondiging-keuze wint ook in nieuw pad', function () {
        $state = reportQuestionState([
            'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
            'reportQuestion_1' => 'Nee',
            'reportQuestion_2' => 'Nee',
        ]);

        expect((new DetermineAanvraagType)->forState($state))->toBe(ZaaktypeRole::Vooraankondiging);
    });

    test('legacy wegen-veld wordt genegeerd in nieuw pad', function () {
        // In de nieuwe modus wordt `wordenErGebiedsontsluitings…` niet
        // ingevuld; als 't ergens toch op 'Nee' staat moet dat geen
        // melding meer triggeren — anders kruisen we de paden.
        $state = reportQuestionState([
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
            'reportQuestion_1' => 'Nee',
        ]);

        expect((new DetermineAanvraagType)->forState($state))->toBe(ZaaktypeRole::Vergunning);
    });
});
