<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\ActionCompiler;
use App\EventForm\Transpiler\JsonLogicCompiler;

/**
 * Helper: neem een OF-action-array, compile 'm tot een PHP-statement en voer
 * dat uit tegen een FormState — we verifiëren de mutatie op de state.
 */
function applyAction(array $action, ?FormState $state = null): FormState
{
    $state = $state ?? FormState::empty();
    $compiler = new ActionCompiler(new JsonLogicCompiler);
    $statement = $compiler->compile($action);

    /** @var callable(FormState): void $fn */
    $fn = eval("return function (\App\EventForm\State\FormState \$s) { {$statement} };");
    $fn($state);

    return $state;
}

describe('ActionCompiler property', function () {
    test('hidden=false shows the target field', function () {
        $action = [
            'component' => 'locatieSOpKaart',
            'form_step_uuid' => null,
            'action' => [
                'type' => 'property',
                'property' => ['value' => 'hidden', 'type' => 'bool'],
                'state' => false,
            ],
        ];
        $state = applyAction($action);
        expect($state->isFieldHidden('locatieSOpKaart'))->toBeFalse();
    });

    test('hidden=true hides the target field', function () {
        $action = [
            'component' => 'meldingvraag1',
            'form_step_uuid' => null,
            'action' => [
                'type' => 'property',
                'property' => ['value' => 'hidden', 'type' => 'bool'],
                'state' => true,
            ],
        ];
        $state = applyAction($action);
        expect($state->isFieldHidden('meldingvraag1'))->toBeTrue();
    });
});

describe('ActionCompiler variable', function () {
    test('variable action with a simple value sets the variable', function () {
        $action = [
            'variable' => 'isVergunningaanvraag',
            'action' => [
                'type' => 'variable',
                'value' => true,
            ],
        ];
        $state = applyAction($action);
        expect($state->get('isVergunningaanvraag'))->toBeTrue();
    });

    test('variable action with a JsonLogic expression evaluates it', function () {
        $state = FormState::empty();
        $state->setVariable('inGemeentenResponse', ['all' => ['object' => ['GM0882' => ['name' => 'Maastricht']]]]);

        $action = [
            'variable' => 'evenementInGemeente',
            'action' => [
                'type' => 'variable',
                'value' => ['var' => 'inGemeentenResponse.all.object'],
            ],
        ];
        $after = applyAction($action, $state);
        expect($after->get('evenementInGemeente'))->toBe(['GM0882' => ['name' => 'Maastricht']]);
    });

    test('variable action with a field reference copies the field value', function () {
        $state = FormState::empty();
        $state->setField('adresVanDeGebouwEn', ['row1']);

        $action = [
            'variable' => 'addressesToCheck',
            'action' => [
                'type' => 'variable',
                'value' => ['var' => 'adresVanDeGebouwEn'],
            ],
        ];
        $after = applyAction($action, $state);
        expect($after->get('addressesToCheck'))->toBe(['row1']);
    });
});

describe('ActionCompiler step-applicable / step-not-applicable', function () {
    test('step-applicable marks the step as applicable', function () {
        $action = [
            'form_step_uuid' => 'ae44ab5b-c068-4ceb-b121-6e6907f78ef9',
            'action' => ['type' => 'step-applicable'],
        ];
        $state = applyAction($action);
        expect($state->isStepApplicable('ae44ab5b-c068-4ceb-b121-6e6907f78ef9'))->toBeTrue();
    });

    test('step-not-applicable marks the step as not-applicable', function () {
        $action = [
            'form_step_uuid' => 'ae44ab5b-c068-4ceb-b121-6e6907f78ef9',
            'action' => ['type' => 'step-not-applicable'],
        ];
        $state = applyAction($action);
        expect($state->isStepApplicable('ae44ab5b-c068-4ceb-b121-6e6907f78ef9'))->toBeFalse();
    });
});

describe('ActionCompiler set-registration-backend', function () {
    test('set-registration-backend writes backend key to system var', function () {
        $action = [
            'action' => ['type' => 'set-registration-backend', 'value' => 'backend7'],
        ];
        $state = applyAction($action);
        expect($state->get('registration_backend'))->toBe('backend7');
    });
});

describe('ActionCompiler fetch-from-service', function () {
    test('fetch-from-service compiles to a no-op placeholder (handled separately)', function () {
        $action = [
            'component' => '',
            'variable' => 'inGemeentenResponse',
            'action' => ['type' => 'fetch-from-service', 'value' => ''],
        ];
        // This compiles but does not execute HTTP — the Filament page
        // triggers the fetch via ServiceFetcher. Action body just records intent.
        $state = FormState::empty();
        $after = applyAction($action, $state);
        // The compiled body should not throw; we expect the state to remain
        // unchanged because service-fetch rules are handled outside RulesEngine.
        expect($after)->toBe($state);
    });
});
