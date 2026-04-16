<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

class VeldenkaartData implements Arrayable
{
    /**
     * @param  list<Step>  $steps
     * @param  list<LogicRule>  $logicRules
     * @param  list<FormVariable>  $formVariables
     * @param  list<TemplateVariable>  $templateVariables
     */
    public function __construct(
        public readonly FormMeta $meta,
        public readonly array $steps,
        public readonly array $logicRules,
        public readonly array $formVariables,
        public readonly array $templateVariables,
    ) {}

    public function totalFieldCount(): int
    {
        $count = 0;
        foreach ($this->steps as $step) {
            $count += $step->fieldCountRecursive();
        }

        return $count;
    }

    public function totalActionCount(): int
    {
        $count = 0;
        foreach ($this->logicRules as $rule) {
            $count += count($rule->actions);
        }

        return $count;
    }

    /** @return array<string, int> */
    public function actionTypeCounts(): array
    {
        /** @var array<string, int> $counts */
        $counts = [];
        foreach ($this->logicRules as $rule) {
            foreach ($rule->actions as $action) {
                $counts[$action->type] = ($counts[$action->type] ?? 0) + 1;
            }
        }

        ksort($counts);

        return $counts;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'meta' => $this->meta->toArray(),
            'totals' => [
                'steps' => count($this->steps),
                'fields' => $this->totalFieldCount(),
                'logic_rules' => count($this->logicRules),
                'logic_actions' => $this->totalActionCount(),
                'logic_actions_by_type' => $this->actionTypeCounts(),
                'form_variables' => count($this->formVariables),
                'template_placeholders' => count($this->templateVariables),
            ],
            'form_variables' => array_map(
                static fn (FormVariable $v): array => $v->toArray(),
                $this->formVariables,
            ),
            'template_variables' => array_map(
                static fn (TemplateVariable $v): array => $v->toArray(),
                $this->templateVariables,
            ),
            'steps' => array_map(static fn (Step $s): array => $s->toArray(), $this->steps),
            'logic_rules' => array_map(
                static fn (LogicRule $r): array => $r->toArray(),
                $this->logicRules,
            ),
        ];
    }
}
