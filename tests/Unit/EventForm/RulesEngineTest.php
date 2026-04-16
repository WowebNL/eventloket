<?php

declare(strict_types=1);

use App\EventForm\Rules\Rule;
use App\EventForm\Rules\RulesEngine;
use App\EventForm\State\FormState;

/**
 * Minimale test-rule die een callable wrappt.
 */
final class CallableRule implements Rule
{
    /**
     * @param  callable(FormState): bool  $applies
     * @param  callable(FormState): void  $apply
     */
    public function __construct(
        private readonly string $id,
        private $applies,
        private $apply,
    ) {}

    public function identifier(): string
    {
        return $this->id;
    }

    public function applies(FormState $state): bool
    {
        return ($this->applies)($state);
    }

    public function apply(FormState $state): void
    {
        ($this->apply)($state);
    }
}

describe('RulesEngine', function () {
    test('runs all rules once to fixpoint on flat dependencies', function () {
        $state = FormState::empty();
        $state->setField('soortEvenement', 'Markt of braderie');

        $engine = new RulesEngine([
            new CallableRule(
                'show-markt',
                fn (FormState $s) => $s->get('soortEvenement') === 'Markt of braderie',
                fn (FormState $s) => $s->setFieldHidden('marktVraag', false),
            ),
        ]);

        $engine->evaluate($state);

        expect($state->isFieldHidden('marktVraag'))->toBeFalse();
    });

    test('re-evaluates until fixpoint when rules trigger other rules', function () {
        $state = FormState::empty();
        $state->setField('trigger', true);

        $engine = new RulesEngine([
            // Rule 1 schrijft variable X
            new CallableRule(
                'set-x',
                fn (FormState $s) => $s->get('trigger') === true,
                fn (FormState $s) => $s->setVariable('x', 'ready'),
            ),
            // Rule 2 gebruikt variable X om stap op niet-applicable te zetten
            new CallableRule(
                'mark-step-na-when-x-ready',
                fn (FormState $s) => $s->get('x') === 'ready',
                fn (FormState $s) => $s->setStepApplicable('melding', false),
            ),
        ]);

        $engine->evaluate($state);

        expect($state->isStepApplicable('melding'))->toBeFalse();
    });

    test('stable state converges after 1 pass', function () {
        $state = FormState::empty();
        $engine = new RulesEngine([
            new CallableRule(
                'noop',
                fn () => false,
                fn () => null,
            ),
        ]);

        $engine->evaluate($state);

        expect(true)->toBeTrue(); // no exception
    });

    test('throws when a rule causes oscillation across passes', function () {
        $state = FormState::empty();
        $state->setVariable('counter', 0);

        // Eén rule die onvoorwaardelijk de counter verhoogt — dat zorgt voor
        // een veranderende snapshot na elke pass, dus nooit een fixpoint.
        $engine = new RulesEngine(
            rules: [
                new CallableRule(
                    'always-increment',
                    fn () => true,
                    fn (FormState $s) => $s->setVariable(
                        'counter',
                        (int) ($s->get('counter') ?? 0) + 1,
                    ),
                ),
            ],
            maxPasses: 3,
        );

        expect(fn () => $engine->evaluate($state))
            ->toThrow(RuntimeException::class, 'oscillating');
    });

    test('selectboxes-style dot-access in trigger works', function () {
        $state = FormState::empty();
        $state->setField('waarVindtHetEvenementPlaats', ['buiten' => true]);

        $engine = new RulesEngine([
            new CallableRule(
                'show-locatieSOpKaart-when-buiten',
                fn (FormState $s) => $s->get('waarVindtHetEvenementPlaats.buiten') === true,
                fn (FormState $s) => $s->setFieldHidden('locatieSOpKaart', false),
            ),
        ]);

        $engine->evaluate($state);

        expect($state->isFieldHidden('locatieSOpKaart'))->toBeFalse();
    });
});
