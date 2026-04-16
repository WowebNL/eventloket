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

    test('field hidden override is stored and retrieved', function () {
        $state = FormState::empty();

        expect($state->isFieldHidden('locatieSOpKaart'))->toBeNull();

        $state->setFieldHidden('locatieSOpKaart', false);
        expect($state->isFieldHidden('locatieSOpKaart'))->toBeFalse();

        $state->setFieldHidden('locatieSOpKaart', true);
        expect($state->isFieldHidden('locatieSOpKaart'))->toBeTrue();
    });

    test('step applicable defaults to true when not set', function () {
        $state = FormState::empty();

        expect($state->isStepApplicable('risicoscan'))->toBeTrue();

        $state->setStepApplicable('risicoscan', false);
        expect($state->isStepApplicable('risicoscan'))->toBeFalse();
    });

    test('snapshot round-trips through fromSnapshot', function () {
        $state = FormState::empty();
        $state->setField('soortEvenement', 'Markt of braderie');
        $state->setVariable('gemeenteVariabelen', ['aanwezigen' => 100]);
        $state->setSystem('submission_id', 'abc-123');
        $state->setFieldHidden('locatieSOpKaart', false);
        $state->setStepApplicable('melding', false);

        $snapshot = $state->toSnapshot();
        $restored = FormState::fromSnapshot($snapshot);

        expect($restored->get('soortEvenement'))->toBe('Markt of braderie')
            ->and($restored->get('gemeenteVariabelen.aanwezigen'))->toBe(100)
            ->and($restored->get('submission_id'))->toBe('abc-123')
            ->and($restored->isFieldHidden('locatieSOpKaart'))->toBeFalse()
            ->and($restored->isStepApplicable('melding'))->toBeFalse();
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
});
