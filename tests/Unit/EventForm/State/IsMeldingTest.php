<?php

declare(strict_types=1);

/**
 * `isMelding` is the derived counterpart of `isVergunningaanvraag`: it says
 * the scan concluded that a report suffices. Everything that has to treat
 * the report path differently hangs off it, so it has to be right on both
 * question systems and it has to stay false while the scan is still running.
 */

use App\EventForm\State\FormState;

function isMeldingNieuweModus(array $extra = [], array $gemeenteVariabelen = []): FormState
{
    return new FormState(values: array_merge([
        'gemeenteVariabelen' => array_merge([
            'use_new_report_questions' => true,
            'report_questions' => [
                ['id' => 1, 'order' => 1, 'question' => 'Vraag 1'],
                ['id' => 2, 'order' => 2, 'question' => 'Vraag 2'],
            ],
        ], $gemeenteVariabelen),
    ], $extra));
}

describe('Nieuw ReportQuestion-pad', function () {
    test('alle actieve vragen Ja → melding', function () {
        $state = isMeldingNieuweModus([
            'reportQuestion_1' => 'Ja',
            'reportQuestion_2' => 'Ja',
        ]);

        expect($state->get('isMelding'))->toBeTrue()
            ->and($state->get('isVergunningaanvraag'))->not->toBeTrue();
    });

    test('één Nee → geen melding', function () {
        $state = isMeldingNieuweModus([
            'reportQuestion_1' => 'Ja',
            'reportQuestion_2' => 'Nee',
        ]);

        expect($state->get('isMelding'))->not->toBeTrue();
    });

    test('scan nog niet af → nog geen melding', function () {
        // Halfway through the questions there is no outcome yet. Showing
        // a deadline that rests on a conclusion nobody has reached is
        // misleading.
        $state = isMeldingNieuweModus(['reportQuestion_1' => 'Ja']);

        expect($state->get('isMelding'))->not->toBeTrue();
    });

    test('gemeente zonder actieve vragen → geen melding', function () {
        $state = isMeldingNieuweModus([], ['report_questions' => []]);

        expect($state->get('isMelding'))->not->toBeTrue();
    });
});

describe('Legacy-pad', function () {
    test('wegen-afsluiten Nee → melding', function () {
        $state = new FormState(values: [
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
        ]);

        expect($state->get('isMelding'))->toBeTrue();
    });

    test('wegen-afsluiten Ja → vergunning, dus geen melding', function () {
        $state = new FormState(values: [
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Ja',
        ]);

        expect($state->get('isMelding'))->not->toBeTrue()
            ->and($state->get('isVergunningaanvraag'))->toBeTrue();
    });

    test('een Nee op een scan-vraag → geen melding', function () {
        $state = new FormState(values: [
            'isHetAantalAanwezigenBijUwEvenementMinderDanSdf' => 'Nee',
            'wordenErGebiedsontsluitingswegenEnOfDoorgaandeWegenAfgeslotenVoorHetVerkeer' => 'Nee',
        ]);

        expect($state->get('isMelding'))->not->toBeTrue();
    });

    test('lege state → nog geen uitkomst', function () {
        expect(FormState::empty()->get('isMelding'))->not->toBeTrue();
    });
});

test('vooraankondiging is geen melding', function () {
    // A vooraankondiging registers a preferred date and is converted
    // later on; it belongs to neither path.
    $state = isMeldingNieuweModus([
        'waarvoorWiltUEventloketGebruiken' => 'vooraankondiging',
        'reportQuestion_1' => 'Ja',
        'reportQuestion_2' => 'Ja',
    ]);

    expect($state->get('isMelding'))->not->toBeTrue();
});
