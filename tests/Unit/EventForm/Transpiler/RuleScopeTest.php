<?php

declare(strict_types=1);

use App\EventForm\Transpiler\RuleClassGenerator;

/**
 * Per-stap scope-analyse: we willen weten bij welke step-UUIDs een rule
 * relevant is (trigger-scope) en welke step-UUIDs z'n effect raakt.
 */
describe('RuleClassGenerator scope', function () {
    test('generates triggerStepUuids() from the fields read in the trigger', function () {
        $rule = [
            'uuid' => 'rule-1',
            'description' => 'Show market question',
            'json_logic_trigger' => ['==' => [['var' => 'soortEvenement'], 'Markt of braderie']],
            'actions' => [],
        ];

        $stepIndex = [
            'step-A' => ['soortEvenement'],
            'step-B' => ['iets-anders'],
        ];

        $generator = (new RuleClassGenerator)->withStepFieldIndex($stepIndex);
        $generated = $generator->generate($rule);

        expect($generated->fileContent)->toContain('public function triggerStepUuids(): array')
            ->and($generated->fileContent)->toContain("'step-A'")
            ->and($generated->fileContent)->not->toContain("'step-B'");
    });

    test('triggerStepUuids from multiple-read rule covers all relevant steps', function () {
        $rule = [
            'uuid' => 'rule-2',
            'description' => 'Multi-step read',
            'json_logic_trigger' => ['and' => [
                ['==' => [['var' => 'fieldA'], 'x']],
                ['==' => [['var' => 'fieldB'], 'y']],
            ]],
            'actions' => [],
        ];

        $stepIndex = [
            'step-A' => ['fieldA'],
            'step-B' => ['fieldB'],
        ];

        $content = (new RuleClassGenerator)->withStepFieldIndex($stepIndex)->generate($rule)->fileContent;

        expect($content)->toContain("'step-A'")
            ->and($content)->toContain("'step-B'");
    });

    test('effectStepUuids contains the step of a property-action target', function () {
        $rule = [
            'uuid' => 'rule-3',
            'description' => 'Hide X',
            'json_logic_trigger' => true,
            'actions' => [
                [
                    'component' => 'locatieSOpKaart',
                    'action' => ['type' => 'property', 'property' => ['value' => 'hidden', 'type' => 'bool'], 'state' => false],
                ],
            ],
        ];

        $stepIndex = ['step-LOC' => ['locatieSOpKaart', 'andere']];

        $content = (new RuleClassGenerator)->withStepFieldIndex($stepIndex)->generate($rule)->fileContent;

        expect($content)->toContain('public function effectStepUuids(): array')
            ->and($content)->toContain("'step-LOC'");
    });

    test('step-applicable action marks the target step as effect-scope', function () {
        $rule = [
            'uuid' => 'rule-4',
            'description' => 'Step X not applicable',
            'json_logic_trigger' => true,
            'actions' => [
                ['form_step_uuid' => 'step-APP', 'action' => ['type' => 'step-not-applicable']],
            ],
        ];

        $content = (new RuleClassGenerator)->generate($rule)->fileContent;

        expect($content)->toContain("'step-APP'");
    });

    test('rules without a step-scope (e.g. system-only reads) emit empty arrays', function () {
        $rule = [
            'uuid' => 'rule-5',
            'description' => 'Global initial setup',
            'json_logic_trigger' => true,
            'actions' => [],
        ];

        $content = (new RuleClassGenerator)->generate($rule)->fileContent;

        expect($content)->toContain('public function triggerStepUuids(): array')
            ->and($content)->toContain('return [];');
    });
});
