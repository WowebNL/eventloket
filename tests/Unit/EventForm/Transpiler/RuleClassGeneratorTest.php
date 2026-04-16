<?php

declare(strict_types=1);

use App\EventForm\State\FormState;
use App\EventForm\Transpiler\RuleClassGenerator;

/**
 * Helper: compile een rule en eval 'm in een anonieme namespace zodat we de
 * gegenereerde klasse kunnen instantiëren in unit-tests zonder filesystem.
 * We strippen de `<?php` + namespace-regel zodat de class onder een unieke
 * test-namespace geplaatst wordt (voorkomt re-declaration-errors tussen tests).
 */
function loadRuleClass(array $rule, string &$namespace = null): string
{
    $generator = new RuleClassGenerator;
    $generated = $generator->generate($rule);

    $namespace = 'RuleTest_'.bin2hex(random_bytes(4));
    $body = preg_replace('/^<\?php\s*/', '', $generated->fileContent);
    $body = preg_replace('/declare\(strict_types=1\);\s*/', '', $body);
    // Vervang namespace; laat `use`-statements staan — die leveren Rule + FormState.
    $body = preg_replace('/namespace App\\\\EventForm\\\\Rules;/', "namespace {$namespace};", $body);
    // De class implementeert `Rule`, maar Rule zit in App\EventForm\Rules\ — voeg die import toe.
    $body = preg_replace(
        '/(namespace '.preg_quote($namespace, '/').';)/',
        "$1\nuse App\\EventForm\\Rules\\Rule;",
        $body,
    );

    eval($body);

    return $namespace.'\\'.$generated->className;
}

describe('RuleClassGenerator', function () {
    test('generates a class name derived from the rule description', function () {
        $rule = [
            'uuid' => 'faa5fae6-c19f-4a8b-b138-a7b98fa44b95',
            'description' => 'Show locatie op kaart when buiten is selected',
            'json_logic_trigger' => ['==' => [['var' => 'waarVindtHetEvenementPlaats.buiten'], true]],
            'actions' => [
                [
                    'component' => 'locatieSOpKaart',
                    'action' => ['type' => 'property', 'property' => ['value' => 'hidden', 'type' => 'bool'], 'state' => false],
                ],
            ],
        ];

        $generated = (new RuleClassGenerator)->generate($rule);

        expect($generated->className)->toBe('ShowLocatieOpKaartWhenBuitenIsSelected');
    });

    test('falls back to a UUID-prefixed name when description is empty', function () {
        $rule = [
            'uuid' => 'faa5fae6-c19f-4a8b-b138-a7b98fa44b95',
            'description' => '',
            'json_logic_trigger' => true,
            'actions' => [],
        ];

        $generated = (new RuleClassGenerator)->generate($rule);

        expect($generated->className)->toStartWith('Rule');
    });

    test('ensures unique class names across multiple rules with the same description', function () {
        $rule = [
            'uuid' => 'aaaaaaaa-0000-0000-0000-000000000001',
            'description' => 'Mark step as not applicable',
            'json_logic_trigger' => true,
            'actions' => [
                ['form_step_uuid' => 'step-A', 'action' => ['type' => 'step-not-applicable']],
            ],
        ];
        $other = [...$rule, 'uuid' => 'aaaaaaaa-0000-0000-0000-000000000002'];

        $generator = new RuleClassGenerator;
        $first = $generator->generate($rule);
        $second = $generator->generate($other);

        expect($first->className)->not->toBe($second->className);
    });

    test('generated class has applies() that returns true when trigger matches', function () {
        $rule = [
            'uuid' => 'test-apply-trigger-01',
            'description' => 'Shows locatie when buiten',
            'json_logic_trigger' => ['==' => [['var' => 'waarVindtHetEvenementPlaats.buiten'], true]],
            'actions' => [
                [
                    'component' => 'locatieSOpKaart',
                    'action' => ['type' => 'property', 'property' => ['value' => 'hidden', 'type' => 'bool'], 'state' => false],
                ],
            ],
        ];

        $fqcn = loadRuleClass($rule);
        $instance = new $fqcn;
        $state = FormState::empty();
        $state->setField('waarVindtHetEvenementPlaats', ['buiten' => true]);

        expect($instance->applies($state))->toBeTrue();

        $otherState = FormState::empty();
        $otherState->setField('waarVindtHetEvenementPlaats', ['buiten' => false]);
        expect($instance->applies($otherState))->toBeFalse();
    });

    test('generated class has apply() that mutates state', function () {
        $rule = [
            'uuid' => 'test-apply-action-02',
            'description' => 'Toggles hidden property',
            'json_logic_trigger' => true,
            'actions' => [
                [
                    'component' => 'locatieSOpKaart',
                    'action' => ['type' => 'property', 'property' => ['value' => 'hidden', 'type' => 'bool'], 'state' => false],
                ],
            ],
        ];

        $fqcn = loadRuleClass($rule);
        $instance = new $fqcn;
        $state = FormState::empty();
        $instance->apply($state);

        expect($state->isFieldHidden('locatieSOpKaart'))->toBeFalse();
    });

    test('generated class executes multiple actions in order', function () {
        $rule = [
            'uuid' => 'test-multi-action-03',
            'description' => 'Sets two variables at once',
            'json_logic_trigger' => true,
            'actions' => [
                ['variable' => 'isVergunningaanvraag', 'action' => ['type' => 'variable', 'value' => true]],
                ['form_step_uuid' => 'step-melding', 'action' => ['type' => 'step-not-applicable']],
            ],
        ];

        $fqcn = loadRuleClass($rule);
        $instance = new $fqcn;
        $state = FormState::empty();
        $instance->apply($state);

        expect($state->get('isVergunningaanvraag'))->toBeTrue()
            ->and($state->isStepApplicable('step-melding'))->toBeFalse();
    });

    test('identifier() returns the rule uuid', function () {
        $rule = [
            'uuid' => 'abc-123-xyz',
            'description' => 'Has identifier',
            'json_logic_trigger' => false,
            'actions' => [],
        ];

        $fqcn = loadRuleClass($rule);
        $instance = new $fqcn;

        expect($instance->identifier())->toBe('abc-123-xyz');
    });
});
