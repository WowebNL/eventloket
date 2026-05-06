<?php

declare(strict_types=1);

use App\EventForm\State\FormState;

describe('FormState', function () {
    test('empty state returns null for any path', function () {
        $state = FormState::empty();

        expect($state->get('whatever'))->toBeNull()
            ->and($state->get('a.b.c'))->toBeNull();
    });

    test('setField stores and get retrieves flat values', function () {
        $state = FormState::empty();
        $state->setField('soortEvenement', 'Markt of braderie');

        expect($state->get('soortEvenement'))->toBe('Markt of braderie');
    });

    test('get with dot notation descends into nested variable', function () {
        $state = FormState::empty();
        $state->setVariable('gemeenteVariabelen', [
            'aanwezigen' => 500,
            'tijdstip_mogelijk_niet_vergunningsplichtig' => ['start' => '09:00', 'end' => '22:00'],
        ]);

        expect($state->get('gemeenteVariabelen.aanwezigen'))->toBe(500)
            ->and($state->get('gemeenteVariabelen.tijdstip_mogelijk_niet_vergunningsplichtig.start'))->toBe('09:00');
    });

    test('selectboxes member access works like OF (object-shape)', function () {
        $state = FormState::empty();
        $state->setField('waarVindtHetEvenementPlaats', ['buiten' => true, 'gebouw' => false]);

        expect($state->get('waarVindtHetEvenementPlaats.buiten'))->toBeTrue()
            ->and($state->get('waarVindtHetEvenementPlaats.gebouw'))->toBeFalse()
            ->and($state->get('waarVindtHetEvenementPlaats.route'))->toBeNull();
    });

    test('selectboxes member access works on Filament CheckboxList-shape (list of values)', function () {
        // Filament bewaart een CheckboxList-state als `[0 => 'buiten', 1 => 'route']`
        // — een indexed list van geselecteerde values. OF verwacht `{buiten: true}`.
        // FormState moet die list transparant kunnen lezen als object.
        $state = FormState::empty();
        $state->setField('waarVindtHetEvenementPlaats', ['buiten', 'route']);

        expect($state->get('waarVindtHetEvenementPlaats.buiten'))->toBeTrue()
            ->and($state->get('waarVindtHetEvenementPlaats.route'))->toBeTrue()
            ->and($state->get('waarVindtHetEvenementPlaats.gebouw'))->toBeFalse();
    });

    test('empty Filament CheckboxList-state returns false for any member', function () {
        $state = FormState::empty();
        $state->setField('sel', []);

        expect($state->get('sel.whatever'))->toBeFalse();
    });

    test('exact-match field with dots takes precedence over dot-descend', function () {
        // Sommige OF velden hebben technisch dots in hun key (theoretisch).
        // Als zo'n key bestaat, hoort die exact te worden opgepakt.
        $state = FormState::empty();
        $state->setField('x.y', 'exact');
        $state->setField('x', ['y' => 'descended']);

        expect($state->get('x.y'))->toBe('exact');
    });

    test('isFieldHidden returns null voor niet-gemigreerde velden', function () {
        // Niet-gemigreerde velden vallen onder de step-default visibility.
        // FormState heeft daar geen mening over.
        $state = FormState::empty();

        expect($state->isFieldHidden('eenWillekeurigVeldZonderRules'))->toBeNull();
    });

    test('step applicable defaults to true zonder rules', function () {
        // Stap-UUID die niet in FormStepApplicability::COMPUTED_STEPS staat
        // → default applicable.
        $state = FormState::empty();

        expect($state->isStepApplicable('risicoscan'))->toBeTrue();
    });

    test('nieuw ReportQuestion-systeem: MeldingStep niet applicable bij vergunning-aanvraag', function () {
        // Eén Nee op een actieve reportQuestion → isVergunningaanvraag = true
        // → MeldingStep mag niet getoond worden.
        $state = new FormState(values: [
            'gemeenteVariabelen' => [
                'use_new_report_questions' => true,
                'report_questions' => [
                    ['id' => 1, 'order' => 1, 'question' => 'Vraag 1'],
                ],
            ],
            'reportQuestion_1' => 'Nee',
        ]);

        // 5f986f16-… = MeldingStep::UUID
        expect($state->isStepApplicable('5f986f16-6a3a-4066-9383-d71f09877f47'))->toBeFalse();
    });

    test('nieuw ReportQuestion-systeem: vergunning-stappen niet applicable bij melding', function () {
        // Alle reportQuestions Ja → melding → vergunning-stappen overslaan.
        $state = new FormState(values: [
            'gemeenteVariabelen' => [
                'use_new_report_questions' => true,
                'report_questions' => [
                    ['id' => 1, 'order' => 1, 'question' => 'Vraag 1'],
                    ['id' => 2, 'order' => 2, 'question' => 'Vraag 2'],
                ],
            ],
            'reportQuestion_1' => 'Ja',
            'reportQuestion_2' => 'Ja',
        ]);

        // 661aabb7-… = VergunningaanvraagVervolgvragenStep
        expect($state->isStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe'))->toBeFalse()
            // 5f986f16-… = MeldingStep is wél applicable (melding-pad)
            ->and($state->isStepApplicable('5f986f16-6a3a-4066-9383-d71f09877f47'))->toBeTrue();
    });

    test('nieuw ReportQuestion-systeem: zonder antwoorden zijn alle stappen applicable', function () {
        // Gebruiker heeft nog niets ingevuld op de scan → niets gespecified
        // → val terug op default applicable.
        $state = new FormState(values: [
            'gemeenteVariabelen' => [
                'use_new_report_questions' => true,
                'report_questions' => [
                    ['id' => 1, 'order' => 1, 'question' => 'Vraag 1'],
                ],
            ],
        ]);

        expect($state->isStepApplicable('5f986f16-6a3a-4066-9383-d71f09877f47'))->toBeTrue()
            ->and($state->isStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe'))->toBeFalse();
    });

    describe('lege stappen overslaan (geen show-condities matched)', function () {
        // Zes stappen hebben show-condities — ze tonen alléén Fieldsets
        // wanneer specifieke vinkjes aangezet zijn op een eerdere vraag.
        // Vinkt de organisator niets aan, dan zou de stap een lege
        // pagina opleveren. Die moet dus overgeslagen worden.

        test('Vervolgvragen-stap: leeg state → niet applicable', function () {
            $state = FormState::empty();

            expect($state->isStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe'))->toBeFalse();
        });

        test('Vervolgvragen-stap: één vinkje aan → applicable', function () {
            $state = FormState::empty();
            $state->setField('kruisAanWatVanToepassingIsVoorUwEvenementX', ['A1' => true]);

            expect($state->isStepApplicable('661aabb7-e927-4a75-8d95-0a665c5d83fe'))->toBeTrue();
        });

        test('ExtraActiviteiten-stap: leeg state → niet applicable', function () {
            $state = FormState::empty();

            expect($state->isStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa'))->toBeFalse();
        });

        test('ExtraActiviteiten-stap: één activiteit aangevinkt → applicable', function () {
            $state = FormState::empty();
            $state->setField('welkeVanDeOnderstaandeActiviteitenVindenVerderNogPlaatsTijdensUwEvenementX', ['A37' => true]);

            expect($state->isStepApplicable('6e285ace-f891-4324-b54e-639c1cfff9fa'))->toBeTrue();
        });

        test('Maatregelen-stap: leeg state → niet applicable', function () {
            $state = FormState::empty();

            expect($state->isStepApplicable('8a5fb30f-287e-41a2-a9bc-e7340bdaaa99'))->toBeFalse();
        });

        test('Maatregelen-stap: één maatregel aangevinkt → applicable', function () {
            $state = FormState::empty();
            $state->setField('kruisAanWelkeOverigeMaatregelenGevolgenVanToepassingZijnVoorUwEvenementX', ['A32' => true]);

            expect($state->isStepApplicable('8a5fb30f-287e-41a2-a9bc-e7340bdaaa99'))->toBeTrue();
        });

        test('Voorwerpen-stap: leeg state → niet applicable', function () {
            $state = FormState::empty();

            expect($state->isStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455'))->toBeFalse();
        });

        test('Voorwerpen-stap: één voorwerp aangevinkt → applicable', function () {
            $state = FormState::empty();
            $state->setField('welkeVoorwerpenGaatUPlaatsenBijUwEvenementX', ['A23' => true]);

            expect($state->isStepApplicable('d790edb5-712a-4f83-87a8-1a86e4831455'))->toBeTrue();
        });

        test('Overig-stap: leeg state → niet applicable', function () {
            $state = FormState::empty();

            expect($state->isStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e'))->toBeFalse();
        });

        test('Overig-stap: één kenmerk aangevinkt → applicable', function () {
            $state = FormState::empty();
            $state->setField('kruisAanWatVoorOverigeKenmerkenVanToepassingZijnVoorUwEvenementX', ['A48' => true]);

            expect($state->isStepApplicable('e8f00982-ee47-4bec-bf31-a5c8d1b05e5e'))->toBeTrue();
        });

        test('Voorzieningen-stap: leeg state → niet applicable', function () {
            // Dit is het concrete scenario uit de feedback: organisator
            // vinkt geen voorzieningen aan, stap moet overgeslagen worden.
            $state = FormState::empty();

            expect($state->isStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84'))->toBeFalse();
        });

        test('Voorzieningen-stap: één voorziening aangevinkt → applicable', function () {
            $state = FormState::empty();
            $state->setField('welkeVoorzieningenZijnAanwezigBijUwEvenement', ['A12' => true]);

            expect($state->isStepApplicable('f4e91db5-fd74-4eba-b818-96ed2cc07d84'))->toBeTrue();
        });

        test('stappen zonder show-condities blijven default applicable bij leeg state', function () {
            // Vragenboom2, Risicoscan en AanvraagOfMelding hebben alleen
            // hide-condities (vooraankondiging / geen wegafsluiting). Bij
            // leeg state moet 'lege' state nog steeds applicable zijn —
            // anders stranden organisators die nog niets ingevuld hebben.
            $state = FormState::empty();

            expect($state->isStepApplicable('ae44ab5b-c068-4ceb-b121-6e6907f78ef9'))->toBeTrue() // Vragenboom2
                ->and($state->isStepApplicable('c75cc256-6729-4684-9f9b-ede6265b3e72'))->toBeTrue() // Risicoscan
                ->and($state->isStepApplicable('d87c01ce-8387-43b0-a8c8-e6cf5abb6da1'))->toBeTrue() // AanvraagOfMelding
                ->and($state->isStepApplicable('5f986f16-6a3a-4066-9383-d71f09877f47'))->toBeTrue(); // MeldingStep
        });
    });

    test('snapshot round-trips through fromSnapshot', function () {
        $state = FormState::empty();
        $state->setField('soortEvenement', 'Markt of braderie');
        $state->setVariable('gemeenteVariabelen', ['aanwezigen' => 100]);
        $state->setSystem('submission_id', 'abc-123');

        $snapshot = $state->toSnapshot();
        $restored = FormState::fromSnapshot($snapshot);

        expect($restored->get('soortEvenement'))->toBe('Markt of braderie')
            ->and($restored->get('gemeenteVariabelen.aanwezigen'))->toBe(100)
            ->and($restored->get('submission_id'))->toBe('abc-123');
    });

    test('absorbFields merges without clearing existing', function () {
        $state = FormState::empty();
        $state->setField('a', 1);
        $state->setField('b', 2);

        $state->absorbFields(['b' => 20, 'c' => 3]);

        expect($state->get('a'))->toBe(1)
            ->and($state->get('b'))->toBe(20)
            ->and($state->get('c'))->toBe(3);
    });

    test('absorbVariables merges without clearing existing', function () {
        $state = FormState::empty();
        $state->setVariable('x', ['n' => 1]);

        $state->absorbVariables(['y' => 'hello']);

        expect($state->get('x.n'))->toBe(1)
            ->and($state->get('y'))->toBe('hello');
    });

    test('system vars are readable via get', function () {
        $state = FormState::empty();
        $state->setSystem('auth_bsn', '123456789');
        $state->setSystem('auth_kvk', '12345678');

        expect($state->get('auth_bsn'))->toBe('123456789')
            ->and($state->get('auth_kvk'))->toBe('12345678');
    });

    test('setVariable and setField share the same bucket (OF-semantics)', function () {
        // In OF's client zijn veld-values en variables dezelfde pool. Een rule
        // die `setVariable('watIsUwVoornaam', 'Eva')` doet, vult daarmee óók
        // het Filament-veld voor die key. fields() moet die waarde zichtbaar
        // maken aan form->fill().
        $state = FormState::empty();
        $state->setVariable('watIsUwVoornaam', 'Eva');
        $state->setField('watIsUwAchternaam', 'Janssen');

        expect($state->get('watIsUwVoornaam'))->toBe('Eva')
            ->and($state->get('watIsUwAchternaam'))->toBe('Janssen')
            ->and($state->fields())->toHaveKeys(['watIsUwVoornaam', 'watIsUwAchternaam']);
    });
});
