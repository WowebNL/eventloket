<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Parsers;

use App\Services\OpenForms\Veldenkaart\Data\Field;
use App\Services\OpenForms\Veldenkaart\Data\LogicAction;
use App\Services\OpenForms\Veldenkaart\Data\LogicRule;
use App\Services\OpenForms\Veldenkaart\Data\Step;

class LogicRuleParser
{
    /** @var array<string, string> step uuid → step name */
    private array $stepNames = [];

    /** @var array<string, string> field key → label */
    private array $fieldLabels = [];

    /** @param  list<Step>  $steps */
    public function buildIndex(array $steps): void
    {
        $this->stepNames = [];
        $this->fieldLabels = [];
        foreach ($steps as $step) {
            $this->stepNames[$step->uuid] = $step->name;
            foreach ($step->fields as $field) {
                $this->collectFieldLabels($field);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rawRules
     * @return list<LogicRule>
     */
    public function parse(array $rawRules): array
    {
        $rules = [];
        foreach ($rawRules as $raw) {
            $rules[] = $this->parseRule($raw);
        }

        return $rules;
    }

    /** @param  array<string, mixed>  $raw */
    private function parseRule(array $raw): LogicRule
    {
        $uuid = $this->asString($raw, 'uuid');
        $order = is_int($raw['order'] ?? null) ? $raw['order'] : 0;
        $description = $this->asString($raw, 'description');
        $isAdvanced = (bool) ($raw['is_advanced'] ?? false);

        $triggerFromStepUuid = null;
        $triggerFromStepName = null;
        $tfs = $raw['trigger_from_step'] ?? null;
        if (is_string($tfs) && $tfs !== '') {
            $triggerFromStepUuid = $this->extractUuid($tfs);
            if ($triggerFromStepUuid !== null) {
                $triggerFromStepName = $this->stepNames[$triggerFromStepUuid] ?? null;
            }
        }

        $trigger = $raw['json_logic_trigger'] ?? [];
        if (! is_array($trigger)) {
            $trigger = [];
        }

        $actions = [];
        $rawActions = $raw['actions'] ?? [];
        if (is_array($rawActions)) {
            foreach ($rawActions as $rawAction) {
                if (is_array($rawAction)) {
                    /** @var array<string, mixed> $rawAction */
                    $actions[] = $this->parseAction($rawAction);
                }
            }
        }

        /** @var array<string, mixed>|list<mixed> $trigger */
        return new LogicRule(
            uuid: $uuid,
            order: $order,
            description: $description,
            isAdvanced: $isAdvanced,
            triggerFromStepUuid: $triggerFromStepUuid,
            triggerFromStepName: $triggerFromStepName,
            jsonLogicTrigger: $trigger,
            actions: $actions,
        );
    }

    /** @param  array<string, mixed>  $raw */
    private function parseAction(array $raw): LogicAction
    {
        $uuid = $this->asString($raw, 'uuid');
        $componentKey = $this->stringOrNull($raw['component'] ?? null);
        $variableKey = $this->stringOrNull($raw['variable'] ?? null);
        $formStepUuid = $this->stringOrNull($raw['form_step_uuid'] ?? null);
        $formStepName = $formStepUuid !== null
            ? ($this->stepNames[$formStepUuid] ?? null)
            : null;

        $actionPayload = $raw['action'] ?? [];
        $type = '';
        $payload = [];
        if (is_array($actionPayload)) {
            /** @var array<string, mixed> $actionPayload */
            $type = $this->asString($actionPayload, 'type');
            $payload = $actionPayload;
        }

        $componentLabel = null;
        if ($componentKey !== null && $componentKey !== '') {
            $componentLabel = $this->fieldLabels[$componentKey] ?? null;
        }

        return new LogicAction(
            uuid: $uuid,
            type: $type,
            componentKey: $componentKey,
            componentLabel: $componentLabel,
            variableKey: $variableKey,
            formStepUuid: $formStepUuid,
            formStepName: $formStepName,
            payload: $payload,
        );
    }

    private function collectFieldLabels(Field $field): void
    {
        if ($field->key !== '' && $field->label !== '') {
            $this->fieldLabels[$field->key] = $field->label;
        }
        foreach ($field->children as $child) {
            $this->collectFieldLabels($child);
        }
    }

    private function extractUuid(string $value): ?string
    {
        if (preg_match('/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $value, $m) === 1) {
            return strtolower($m[1]);
        }

        return null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }

    /** @param  array<string, mixed>  $source */
    private function asString(array $source, string $key): string
    {
        $value = $source[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
