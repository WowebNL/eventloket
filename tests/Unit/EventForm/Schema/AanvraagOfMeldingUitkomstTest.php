<?php

declare(strict_types=1);

/**
 * De Vergunningplichtig-scan eindigt met een uitkomst-tekst die de
 * organisator vertelt wat er nu gaat gebeuren — vergunning of melding.
 * Bij de migratie naar het ReportQuestion-systeem viel die tekst weg
 * voor gemeenten met `use_new_report_questions = true`, omdat 'ie in
 * de legacy-Group hing. Deze tests bewijzen dat de juiste uitkomst-
 * tekst nu in beide paden verschijnt.
 */

use App\EventForm\Schema\Steps\AanvraagOfMeldingStep;
use App\EventForm\State\FormState;

describe('Legacy-pad (use_new_report_questions !== true)', function () {
    test('contentGoNext volgt FormFieldVisibility zonder afgeleide flag', function () {
        // Lege state → niets bekend over scan → FormFieldVisibility
        // returnt null/false → tekst hidden.
        $state = FormState::empty();

        expect(AanvraagOfMeldingStep::contentGoNextHidden($state))->toBeTrue();
    });

    test('MeldingTekst volgt FormFieldVisibility zonder afgeleide flag', function () {
        $state = FormState::empty();

        expect(AanvraagOfMeldingStep::meldingTekstHidden($state))->toBeTrue();
    });
});

describe('Nieuw ReportQuestion-pad (use_new_report_questions === true)', function () {
    function nieuweModusState(array $extra = []): FormState
    {
        return new FormState(values: array_merge([
            'gemeenteVariabelen' => [
                'use_new_report_questions' => true,
                'report_questions' => [
                    ['id' => 1, 'order' => 1, 'question' => 'Vraag 1'],
                    ['id' => 2, 'order' => 2, 'question' => 'Vraag 2'],
                ],
            ],
        ], $extra));
    }

    test('Eén Nee → vergunning-tekst verschijnt, melding-tekst blijft hidden', function () {
        // Eén 'Nee' op een actieve scan-vraag betekent dat een vergunning
        // nodig is. De organisator moet dat duidelijk te zien krijgen.
        $state = nieuweModusState([
            'reportQuestion_1' => 'Nee',
            'isVergunningaanvraag' => true,
        ]);

        expect(AanvraagOfMeldingStep::contentGoNextHidden($state))->toBeFalse()
            ->and(AanvraagOfMeldingStep::meldingTekstHidden($state))->toBeTrue();
    });

    test('Alle Ja → melding-tekst verschijnt, vergunning-tekst blijft hidden', function () {
        // Alle scan-vragen Ja → meldings-pad. Tekst dat een melding
        // volstaat moet zichtbaar zijn.
        $state = nieuweModusState([
            'reportQuestion_1' => 'Ja',
            'reportQuestion_2' => 'Ja',
        ]);

        expect(AanvraagOfMeldingStep::meldingTekstHidden($state))->toBeFalse()
            ->and(AanvraagOfMeldingStep::contentGoNextHidden($state))->toBeTrue();
    });

    test('Geen antwoorden → beide teksten hidden, scan loopt nog', function () {
        // Geen enkel antwoord ingevuld → niet duidelijk welk pad.
        // Geen tekst tonen, anders is 't misleidend.
        $state = nieuweModusState();

        expect(AanvraagOfMeldingStep::contentGoNextHidden($state))->toBeTrue()
            ->and(AanvraagOfMeldingStep::meldingTekstHidden($state))->toBeTrue();
    });

    test('Eerste vraag Ja maar tweede ongebrachvuld → melding nog niet zichtbaar', function () {
        // Cascade nog bezig: nog niet alle vragen beantwoord. Pas
        // eindoordeel uitspreken als de scan compleet is.
        $state = nieuweModusState([
            'reportQuestion_1' => 'Ja',
        ]);

        expect(AanvraagOfMeldingStep::meldingTekstHidden($state))->toBeTrue();
    });
});
