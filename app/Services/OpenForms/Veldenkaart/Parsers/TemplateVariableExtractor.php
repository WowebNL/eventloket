<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Parsers;

use App\Services\OpenForms\Veldenkaart\Data\Field;
use App\Services\OpenForms\Veldenkaart\Data\LogicRule;
use App\Services\OpenForms\Veldenkaart\Data\Step;
use App\Services\OpenForms\Veldenkaart\Data\TemplateVariable;

/**
 * Scant alle strings in de genormaliseerde data op `{{ ... }}` placeholders
 * en groepeert ze op root-variabele (bijv. `gemeenteVariabelen.*`).
 */
class TemplateVariableExtractor
{
    /** @var array<string, array<string, list<array<string, string>>>> */
    private array $occurrencesByPlaceholder = [];

    /**
     * @param  list<Step>  $steps
     * @param  list<LogicRule>  $logicRules
     * @return list<TemplateVariable>
     */
    public function extract(array $steps, array $logicRules): array
    {
        $this->occurrencesByPlaceholder = [];

        foreach ($steps as $step) {
            foreach ($step->fields as $field) {
                $this->scanField($step, $field);
            }
        }

        foreach ($logicRules as $rule) {
            if ($rule->description !== '') {
                $this->record(
                    $rule->description,
                    $rule->triggerFromStepName ?? '(geen stap)',
                    'logic:'.$rule->uuid,
                    'rule-description',
                );
            }
        }

        $result = [];
        ksort($this->occurrencesByPlaceholder);
        foreach ($this->occurrencesByPlaceholder as $placeholder => $bucket) {
            $occurrences = [];
            foreach ($bucket as $list) {
                foreach ($list as $entry) {
                    $occurrences[] = $entry;
                }
            }

            [$root, $accessor] = $this->splitPlaceholder($placeholder);
            $result[] = new TemplateVariable(
                placeholder: '{{ '.$placeholder.' }}',
                root: $root,
                accessor: $accessor,
                occurrences: $occurrences,
            );
        }

        // Sort by root, then accessor for stability.
        usort($result, static function (TemplateVariable $a, TemplateVariable $b): int {
            return [$a->root, $a->accessor] <=> [$b->root, $b->accessor];
        });

        return $result;
    }

    private function scanField(Step $step, Field $field): void
    {
        $stepLabel = 'stap '.$step->index.': '.$step->name;
        $fieldKey = $field->key !== '' ? $field->key : '(naamloos)';

        $this->record($field->label, $stepLabel, $fieldKey, 'label');
        $this->record($field->description, $stepLabel, $fieldKey, 'description');
        $this->record($field->tooltip, $stepLabel, $fieldKey, 'tooltip');
        if ($field->contentHtml !== null) {
            $this->record($field->contentHtml, $stepLabel, $fieldKey, 'content.html');
        }

        foreach ($field->children as $child) {
            $this->scanField($step, $child);
        }
    }

    private function record(string $text, string $step, string $fieldKey, string $location): void
    {
        if ($text === '') {
            return;
        }

        if (preg_match_all('/\{\{\s*([^{}]+?)\s*\}\}/', $text, $matches) === false) {
            return;
        }

        foreach ($matches[1] as $placeholder) {
            $placeholder = trim($placeholder);
            if ($placeholder === '') {
                continue;
            }

            $this->occurrencesByPlaceholder[$placeholder][$step.'|'.$fieldKey.'|'.$location][] = [
                'step' => $step,
                'field_key' => $fieldKey,
                'location' => $location,
            ];
        }
    }

    /** @return array{string, string} */
    private function splitPlaceholder(string $placeholder): array
    {
        // Separate first identifier from remainder.
        $parts = preg_split('/[.\s|(]/', $placeholder, 2);
        $root = is_array($parts) && isset($parts[0]) ? $parts[0] : $placeholder;
        $accessor = $placeholder === $root ? '' : substr($placeholder, strlen($root) + 1);

        return [$root, $accessor];
    }
}
