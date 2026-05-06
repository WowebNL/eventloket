<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Parsers;

use App\Services\OpenForms\Veldenkaart\Data\Condition;
use App\Services\OpenForms\Veldenkaart\Data\Field;
use App\Services\OpenForms\Veldenkaart\Data\FieldOption;
use App\Services\OpenForms\Veldenkaart\Data\FormVariable;

/**
 * Walks a FormIO component tree (inclusief columns, fieldset, editgrid)
 * en levert genormaliseerde Field-DTO's op.
 */
class ComponentParser
{
    /** @var array<string, string> */
    private array $fieldTypeIndex = [];

    /** @var array<string, FormVariable> */
    private array $variablesByKey = [];

    /**
     * @param  list<array<string, mixed>>  $formSteps
     * @param  list<FormVariable>  $variables
     */
    public function buildFieldTypeIndex(array $formSteps, array $variables): void
    {
        $this->fieldTypeIndex = [];
        foreach ($formSteps as $step) {
            $components = $step['configuration']['components'] ?? [];
            if (is_array($components)) {
                $this->indexComponents($components);
            }
        }

        $this->variablesByKey = [];
        foreach ($variables as $v) {
            $this->variablesByKey[$v->key] = $v;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $components
     * @return list<Field>
     */
    public function parseStepComponents(array $components, string $stepSlug): array
    {
        $fields = [];
        foreach ($components as $component) {
            $fields[] = $this->parseComponent($component, $stepSlug, 0);
        }

        return $fields;
    }

    /** @param  array<string, mixed>  $component */
    private function parseComponent(array $component, string $stepSlug, int $depth): Field
    {
        $type = $this->asString($component, 'type');
        $key = $this->asString($component, 'key');
        $label = $this->asString($component, 'label');
        $hidden = $this->asBool($component, 'hidden');
        $description = $this->asString($component, 'description');
        $tooltip = $this->asString($component, 'tooltip');

        $validate = [];
        if (isset($component['validate']) && is_array($component['validate'])) {
            /** @var array<string, mixed> $validate */
            $validate = $this->normalizeValidate($component['validate']);
        }

        $required = (bool) ($validate['required'] ?? false);

        $options = $this->parseOptions($component);
        $optionsSource = $this->detectOptionsSource($component);

        $defaultValue = $component['defaultValue'] ?? null;

        $prefill = null;
        if (isset($component['prefill']) && is_array($component['prefill'])) {
            $prefill = $this->normalizePrefill($component['prefill']);
        }

        $conditional = $this->parseConditional($component);
        $customConditional = is_string($component['customConditional'] ?? null)
            ? ($component['customConditional'] === '' ? null : $component['customConditional'])
            : null;

        $isContent = $type === 'content';
        $contentHtml = null;
        if ($isContent && is_string($component['html'] ?? null)) {
            $contentHtml = $component['html'];
        }

        $children = $this->parseChildren($component, $stepSlug, $depth + 1);

        return new Field(
            key: $key,
            type: $type,
            label: $label,
            required: $required,
            hidden: $hidden,
            description: $description,
            tooltip: $tooltip,
            options: $options,
            optionsSource: $optionsSource,
            validate: $validate,
            defaultValue: $defaultValue,
            prefill: $prefill,
            conditional: $conditional,
            customConditional: $customConditional,
            depth: $depth,
            stepSlug: $stepSlug,
            children: $children,
            isContent: $isContent,
            contentHtml: $contentHtml,
        );
    }

    /**
     * @param  array<string, mixed>  $component
     * @return list<Field>
     */
    private function parseChildren(array $component, string $stepSlug, int $depth): array
    {
        $children = [];
        $type = $component['type'] ?? null;

        // Most containers use `.components[]`.
        if (isset($component['components']) && is_array($component['components'])) {
            foreach ($component['components'] as $child) {
                if (is_array($child)) {
                    /** @var array<string, mixed> $child */
                    $children[] = $this->parseComponent($child, $stepSlug, $depth);
                }
            }
        }

        // `columns` nest their children under `.columns[].components[]`.
        if ($type === 'columns' && isset($component['columns']) && is_array($component['columns'])) {
            foreach ($component['columns'] as $column) {
                if (! is_array($column)) {
                    continue;
                }
                $cols = $column['components'] ?? null;
                if (! is_array($cols)) {
                    continue;
                }
                foreach ($cols as $child) {
                    if (is_array($child)) {
                        /** @var array<string, mixed> $child */
                        $children[] = $this->parseComponent($child, $stepSlug, $depth);
                    }
                }
            }
        }

        return $children;
    }

    /**
     * @param  array<string, mixed>  $component
     * @return list<FieldOption>
     */
    private function parseOptions(array $component): array
    {
        // 1a. Inline `values` list (used by radio / selectboxes).
        $options = $this->extractValueList($component['values'] ?? null, 'manual');
        if ($options !== []) {
            return $options;
        }

        // 1b. `data.values[]` — used by <select> components.
        $data = $component['data'] ?? null;
        if (is_array($data)) {
            $options = $this->extractValueList($data['values'] ?? null, 'manual');
            if ($options !== []) {
                return $options;
            }
        }

        // 2. Variable-backed options (openForms.dataSrc == "variable").
        $openForms = $component['openForms'] ?? null;
        if (is_array($openForms)) {
            $dataSrc = $openForms['dataSrc'] ?? null;
            $itemsExpression = $openForms['itemsExpression'] ?? null;
            if ($dataSrc === 'variable' && is_array($itemsExpression)) {
                $varKey = $itemsExpression['var'] ?? null;
                if (is_string($varKey) && isset($this->variablesByKey[$varKey])) {
                    return $this->optionsFromVariable($this->variablesByKey[$varKey]);
                }
                if (is_string($varKey)) {
                    // Unknown variable — still emit a placeholder option so it's discoverable.
                    return [new FieldOption($varKey, '(onbekende variabele)', 'variable:'.$varKey)];
                }
            }
        }

        return [];
    }

    /** @return list<FieldOption> */
    private function extractValueList(mixed $raw, string $source): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $options = [];
        foreach ($raw as $value) {
            if (! is_array($value)) {
                continue;
            }
            $v = $value['value'] ?? null;
            $l = $value['label'] ?? null;
            if (is_string($v) && $v !== '' && is_string($l)) {
                $options[] = new FieldOption($v, $l, $source);
            }
        }

        return $options;
    }

    /** @return list<FieldOption> */
    private function optionsFromVariable(FormVariable $variable): array
    {
        $source = 'variable:'.$variable->key;
        $initial = $variable->initialValue;

        if (! is_array($initial)) {
            return [];
        }

        $options = [];
        foreach ($initial as $entry) {
            if (is_string($entry)) {
                $options[] = new FieldOption($entry, $entry, $source);
            } elseif (is_array($entry) && count($entry) >= 2) {
                $value = $entry[0] ?? null;
                $label = $entry[1] ?? null;
                if (is_string($value) && is_string($label)) {
                    $options[] = new FieldOption($value, $label, $source);
                }
            }
        }

        return $options;
    }

    /** @param  array<string, mixed>  $component */
    private function detectOptionsSource(array $component): ?string
    {
        $openForms = $component['openForms'] ?? null;
        if (is_array($openForms)) {
            $dataSrc = $openForms['dataSrc'] ?? null;
            if (is_string($dataSrc)) {
                $expr = $openForms['itemsExpression'] ?? null;
                if ($dataSrc === 'variable' && is_array($expr) && is_string($expr['var'] ?? null)) {
                    return 'variable:'.$expr['var'];
                }

                return $dataSrc;
            }
        }

        $values = $component['values'] ?? null;
        if (is_array($values) && $values !== []) {
            return 'manual';
        }

        return null;
    }

    /** @param  array<string, mixed>  $component */
    private function parseConditional(array $component): ?Condition
    {
        $conditional = $component['conditional'] ?? null;
        if (! is_array($conditional)) {
            return null;
        }

        $show = $conditional['show'] ?? null;
        $when = $conditional['when'] ?? null;
        $eq = $conditional['eq'] ?? '';

        if (! is_bool($show) || ! is_string($when) || $when === '') {
            return null;
        }

        $targetType = $this->fieldTypeIndex[$when] ?? 'unknown';
        $isSelectboxesMember = $targetType === 'selectboxes';

        return new Condition(
            show: $show,
            when: $when,
            equals: is_string($eq) ? $eq : (is_scalar($eq) ? (string) $eq : ''),
            targetType: $targetType,
            isSelectboxesMember: $isSelectboxesMember,
        );
    }

    /**
     * @param  array<string, mixed>  $validate
     * @return array<string, mixed>
     */
    private function normalizeValidate(array $validate): array
    {
        $keep = [
            'required', 'minLength', 'maxLength', 'pattern', 'min', 'max',
            'customMessage', 'multiple', 'unique', 'plugins', 'custom',
            'customPrivate', 'strictDateValidation', 'onlyAvailableItems',
        ];

        $result = [];
        foreach ($keep as $key) {
            if (! array_key_exists($key, $validate)) {
                continue;
            }
            $val = $validate[$key];
            // Skip empty / default values so the markdown output is terse.
            if ($val === '' || $val === false || $val === null || $val === []) {
                continue;
            }
            $result[$key] = $val;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $prefill
     * @return array<string, mixed>|null
     */
    private function normalizePrefill(array $prefill): ?array
    {
        $plugin = $prefill['plugin'] ?? null;
        $attribute = $prefill['attribute'] ?? null;
        $identifierRole = $prefill['identifierRole'] ?? null;

        if (! is_string($plugin) || $plugin === '') {
            // No active prefill configured.
            return null;
        }

        return [
            'plugin' => $plugin,
            'attribute' => is_string($attribute) ? $attribute : '',
            'identifier_role' => is_string($identifierRole) ? $identifierRole : 'main',
        ];
    }

    /** @param  list<array<string, mixed>>  $components */
    private function indexComponents(array $components): void
    {
        foreach ($components as $component) {
            $key = $component['key'] ?? null;
            $type = $component['type'] ?? null;
            if (is_string($key) && $key !== '' && is_string($type)) {
                $this->fieldTypeIndex[$key] = $type;
            }

            if (isset($component['components']) && is_array($component['components'])) {
                /** @var list<array<string, mixed>> $nested */
                $nested = $component['components'];
                $this->indexComponents($nested);
            }

            if (($component['type'] ?? null) === 'columns' && is_array($component['columns'] ?? null)) {
                foreach ($component['columns'] as $column) {
                    if (is_array($column) && is_array($column['components'] ?? null)) {
                        /** @var list<array<string, mixed>> $nested */
                        $nested = $column['components'];
                        $this->indexComponents($nested);
                    }
                }
            }
        }
    }

    /** @param  array<string, mixed>  $source */
    private function asString(array $source, string $key): string
    {
        $value = $source[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** @param  array<string, mixed>  $source */
    private function asBool(array $source, string $key): bool
    {
        return (bool) ($source[$key] ?? false);
    }
}
