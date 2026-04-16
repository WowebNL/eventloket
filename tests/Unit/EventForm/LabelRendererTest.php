<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\EventForm\Template\LabelRenderer;

describe('LabelRenderer', function () {
    test('returns unchanged when no placeholders', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();

        expect($renderer->render('Hallo wereld', $state))->toBe('Hallo wereld');
    });

    test('substitutes a simple field placeholder', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();
        $state->setField('watIsDeNaamVanHetEvenementVergunning', 'Koningsdag 2026');

        $template = 'Wat voor soort evenement is {{ watIsDeNaamVanHetEvenementVergunning }}?';

        expect($renderer->render($template, $state))
            ->toBe('Wat voor soort evenement is Koningsdag 2026?');
    });

    test('substitutes nested variable via dot notation', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();
        $state->setVariable('gemeenteVariabelen', ['aanwezigen' => 500]);

        $template = 'Minder dan {{ gemeenteVariabelen.aanwezigen }} personen?';

        expect($renderer->render($template, $state))
            ->toBe('Minder dan 500 personen?');
    });

    test('unknown placeholder becomes empty string', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();

        expect($renderer->render('Hallo {{ onbekend }}!', $state))
            ->toBe('Hallo !');
    });

    test('join filter with separator works on arrays', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();
        $state->setVariable('routeDoorGemeentenNamen', ['Maastricht', 'Heerlen', 'Kerkrade']);

        $template = 'Route door: {{ routeDoorGemeentenNamen|join:", " }}';

        expect($renderer->render($template, $state))
            ->toBe('Route door: Maastricht, Heerlen, Kerkrade');
    });

    test('join filter falls back gracefully on scalar', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();
        $state->setVariable('x', 'geen array');

        expect($renderer->render('{{ x|join:", " }}', $state))->toBe('geen array');
    });

    test('booleans are rendered as true/false', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();
        $state->setVariable('isActive', true);

        expect($renderer->render('{{ isActive }}', $state))->toBe('true');
    });

    test('get_value tag reads a nested key from a variable', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();
        $state->setVariable('evenementInGemeente', ['name' => 'Maastricht', 'brk_identification' => 'GM0882']);

        $template = "U gaat verder voor de gemeente: {% get_value evenementInGemeente 'name' %}";

        expect($renderer->render($template, $state))
            ->toBe('U gaat verder voor de gemeente: Maastricht');
    });

    test('get_value tag with double-quoted key works too', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();
        $state->setVariable('gemeenteVariabelen', ['aanwezigen' => 500]);

        expect($renderer->render('Limiet: {% get_value gemeenteVariabelen "aanwezigen" %} personen', $state))
            ->toBe('Limiet: 500 personen');
    });

    test('get_value tag returns empty when variable or key is missing', function () {
        $renderer = new LabelRenderer;
        $state = FormState::empty();

        expect($renderer->render("Leeg: {% get_value onbekend 'x' %}!", $state))
            ->toBe('Leeg: !');
    });
});
