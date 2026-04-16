<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Renderers;

use App\Services\OpenForms\Veldenkaart\Data\Field;
use App\Services\OpenForms\Veldenkaart\Data\LogicAction;
use App\Services\OpenForms\Veldenkaart\Data\LogicRule;
use App\Services\OpenForms\Veldenkaart\Data\Step;
use App\Services\OpenForms\Veldenkaart\Data\VeldenkaartData;

class MarkdownRenderer
{
    public function render(VeldenkaartData $data): string
    {
        $parts = [];
        $parts[] = $this->renderFrontmatter($data);
        $parts[] = $this->renderHeader($data);
        $parts[] = $this->renderTableOfContents($data);
        $parts[] = $this->renderSummary($data);
        $parts[] = $this->renderFormVariables($data);
        $parts[] = $this->renderTemplateVariables($data);
        $parts[] = $this->renderSteps($data);
        $parts[] = $this->renderLogic($data);

        return implode("\n", array_filter($parts, static fn ($p) => $p !== '')).
            "\n";
    }

    private function renderFrontmatter(VeldenkaartData $data): string
    {
        $meta = $data->meta;
        $lines = [
            '---',
            'form_uuid: '.$meta->uuid,
            'form_slug: '.$meta->slug,
            'form_name: '.$this->yamlString($meta->name),
        ];
        if ($meta->internalName !== null) {
            $lines[] = 'internal_name: '.$this->yamlString($meta->internalName);
        }
        if ($meta->ofRelease !== null) {
            $lines[] = 'of_release: '.$meta->ofRelease;
        }
        if ($meta->ofGitSha !== null) {
            $lines[] = 'of_git_sha: '.$meta->ofGitSha;
        }
        $lines[] = 'generated_at: '.$meta->generatedAt;
        $lines[] = 'source: '.$this->yamlString($meta->source);
        $lines[] = 'totals:';
        $lines[] = '  steps: '.count($data->steps);
        $lines[] = '  fields: '.$data->totalFieldCount();
        $lines[] = '  logic_rules: '.count($data->logicRules);
        $lines[] = '  logic_actions: '.$data->totalActionCount();
        $lines[] = '  form_variables: '.count($data->formVariables);
        $lines[] = '  template_placeholders: '.count($data->templateVariables);
        $lines[] = '---';

        return implode("\n", $lines)."\n";
    }

    private function renderHeader(VeldenkaartData $data): string
    {
        return "# {$data->meta->name} — Veldenkaart\n".
            "\nAutomatisch gegenereerd door `php artisan forms:veldenkaart`. Niet handmatig bewerken — wijzigingen in Open Forms zijn leidend.\n";
    }

    private function renderTableOfContents(VeldenkaartData $data): string
    {
        $toc = ["\n## Inhoud\n"];
        $toc[] = '- [Samenvatting](#samenvatting)';
        $toc[] = '- [Form-variabelen]('.$this->anchor('form-variabelen').') ('.count($data->formVariables).')';
        $toc[] = '- [Template-variabelen]('.$this->anchor('template-variabelen').') ('.count($data->templateVariables).')';
        $toc[] = '- [Stappen]('.$this->anchor('stappen').') ('.count($data->steps).')';
        foreach ($data->steps as $step) {
            $toc[] = '  - ['.$this->escape($this->stepHeading($step)).']('.$this->anchor($this->stepHeading($step)).')';
        }
        $toc[] = '- [Logica]('.$this->anchor('logica').')';
        $counts = $data->actionTypeCounts();
        foreach ($counts as $type => $count) {
            $toc[] = '  - ['.$type.']('.$this->anchor('logica-'.$type).') ('.$count.')';
        }

        return implode("\n", $toc)."\n";
    }

    private function renderSummary(VeldenkaartData $data): string
    {
        $lines = ["\n## Samenvatting\n"];
        $lines[] = '| Metric | Waarde |';
        $lines[] = '|---|---|';
        $lines[] = '| Formulier | '.$this->escape($data->meta->name).' |';
        $lines[] = '| UUID | `'.$data->meta->uuid.'` |';
        $lines[] = '| Slug | `'.$data->meta->slug.'` |';
        $lines[] = '| OF-versie | '.($data->meta->ofRelease ?? '—').' |';
        $lines[] = '| Bron | '.$this->escape($data->meta->source).' |';
        $lines[] = '| Gegenereerd | '.$data->meta->generatedAt.' |';
        $lines[] = '| Stappen | '.count($data->steps).' |';
        $lines[] = '| Velden (excl. content) | '.$data->totalFieldCount().' |';
        $lines[] = '| Logic rules | '.count($data->logicRules).' |';
        $lines[] = '| Logic actions | '.$data->totalActionCount().' |';

        $counts = $data->actionTypeCounts();
        if ($counts !== []) {
            $lines[] = '';
            $lines[] = '### Logic-acties per type';
            $lines[] = '';
            $lines[] = '| Type | Aantal |';
            $lines[] = '|---|---|';
            foreach ($counts as $type => $count) {
                $lines[] = '| `'.$type.'` | '.$count.' |';
            }
        }

        return implode("\n", $lines)."\n";
    }

    private function renderFormVariables(VeldenkaartData $data): string
    {
        if ($data->formVariables === []) {
            return '';
        }

        $lines = ["\n## Form-variabelen\n"];
        $lines[] = '| Key | Naam | Type | Source | Prefill-plugin | Initial value |';
        $lines[] = '|---|---|---|---|---|---|';
        foreach ($data->formVariables as $variable) {
            $lines[] = sprintf(
                '| `%s` | %s | %s | %s | %s | %s |',
                $variable->key,
                $this->escape($variable->name),
                $variable->dataType,
                $variable->source,
                $variable->prefillPlugin === '' ? '—' : '`'.$variable->prefillPlugin.'`',
                $this->inlineValue($variable->initialValue),
            );
        }

        return implode("\n", $lines)."\n";
    }

    private function renderTemplateVariables(VeldenkaartData $data): string
    {
        if ($data->templateVariables === []) {
            return '';
        }

        $lines = ["\n## Template-variabelen\n"];
        $lines[] = 'Alle `{{ ... }}` placeholders die in labels, descriptions, tooltips of logic-descriptions voorkomen.';
        $lines[] = '';
        $lines[] = '| Placeholder | Voorkomens | Vindplaats (eerste 3) |';
        $lines[] = '|---|---|---|';
        foreach ($data->templateVariables as $tpl) {
            $locations = array_slice($tpl->occurrences, 0, 3);
            $formatted = array_map(
                fn (array $o): string => $this->escape($o['step'].' → '.$o['field_key'].' ('.$o['location'].')'),
                $locations,
            );
            $more = count($tpl->occurrences) > 3 ? ' …(+'.(count($tpl->occurrences) - 3).' meer)' : '';
            $lines[] = sprintf(
                '| `%s` | %d | %s%s |',
                $this->escapeCode($tpl->placeholder),
                count($tpl->occurrences),
                implode('<br>', $formatted),
                $more,
            );
        }

        return implode("\n", $lines)."\n";
    }

    private function renderSteps(VeldenkaartData $data): string
    {
        $lines = ["\n## Stappen\n"];
        foreach ($data->steps as $step) {
            $lines[] = $this->renderStep($step);
        }

        return implode("\n", $lines)."\n";
    }

    private function renderStep(Step $step): string
    {
        $heading = $this->stepHeading($step);
        $lines = [];
        $lines[] = "\n### {$heading}";
        $lines[] = '';
        $lines[] = '> UUID: `'.$step->uuid.'` · slug: `'.$step->slug.'` · velden: '.$step->fieldCountRecursive();
        $lines[] = '';

        if ($step->fields === []) {
            $lines[] = '_Geen velden in deze stap._';

            return implode("\n", $lines);
        }

        $lines[] = '| Key | Type | Label | Verplicht | Hidden | Opties | Validatie | Default | Prefill | Conditie | Custom-conditional |';
        $lines[] = '|---|---|---|---|---|---|---|---|---|---|---|';
        foreach ($step->fields as $field) {
            $lines = array_merge($lines, $this->renderFieldRows($field, 0));
        }

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private function renderFieldRows(Field $field, int $depth): array
    {
        $rows = [];
        $keyDisplay = ($depth > 0 ? str_repeat('—', $depth).' ' : '').'`'.$field->key.'`';
        $labelDisplay = $this->truncateForTable($field->label);
        if ($field->hidden) {
            $labelDisplay .= ' <sub>(hidden)</sub>';
        }
        if ($field->isContent) {
            $labelDisplay = '_content_';
        }

        $rows[] = sprintf(
            '| %s | %s | %s | %s | %s | %s | %s | %s | %s | %s | %s |',
            $keyDisplay,
            $field->type,
            $this->escape($labelDisplay),
            $field->required ? '✓' : '',
            $field->hidden ? '✓' : '',
            $this->formatOptions($field),
            $this->formatValidate($field),
            $this->formatDefault($field),
            $this->formatPrefill($field),
            $this->formatConditional($field),
            $field->customConditional !== null ? '⚠ JS' : '',
        );

        foreach ($field->children as $child) {
            $rows = array_merge($rows, $this->renderFieldRows($child, $depth + 1));
        }

        return $rows;
    }

    private function formatOptions(Field $field): string
    {
        if ($field->options === []) {
            if ($field->optionsSource !== null && str_starts_with($field->optionsSource, 'variable:')) {
                return '_(uit '.substr($field->optionsSource, 9).')_';
            }

            return '';
        }

        $parts = [];
        foreach ($field->options as $o) {
            $parts[] = '`'.$o->value.'`='.$this->escape($o->label);
        }
        $source = $field->optionsSource !== null && str_starts_with($field->optionsSource, 'variable:')
            ? ' <sub>('.substr($field->optionsSource, 9).')</sub>'
            : '';

        return implode('<br>', $parts).$source;
    }

    private function formatValidate(Field $field): string
    {
        $rules = [];
        foreach ($field->validate as $k => $v) {
            if ($k === 'required') {
                continue;
            }
            $rules[] = $k.'='.$this->inlineValue($v);
        }

        return implode(', ', $rules);
    }

    private function formatDefault(Field $field): string
    {
        $v = $field->defaultValue;
        if ($v === null || $v === '' || $v === []) {
            return '';
        }

        return '`'.$this->truncate($this->inlineValue($v), 30).'`';
    }

    private function formatPrefill(Field $field): string
    {
        if ($field->prefill === null) {
            return '';
        }
        $plugin = $field->prefill['plugin'] ?? '';
        $attr = $field->prefill['attribute'] ?? '';

        return '`'.$plugin.'`'.($attr !== '' ? ' → `'.$attr.'`' : '');
    }

    private function formatConditional(Field $field): string
    {
        if ($field->conditional === null) {
            return '';
        }

        return $this->escape($field->conditional->describe());
    }

    private function renderLogic(VeldenkaartData $data): string
    {
        if ($data->logicRules === []) {
            return "\n## Logica\n\n_Geen logic rules._\n";
        }

        $lines = ["\n## Logica\n"];
        $lines[] = 'Alle '.count($data->logicRules).' logic rules uit `/api/v2/forms/'.$data->meta->uuid.'/logic-rules`. Uit deze rules zijn in totaal '.$data->totalActionCount().' acties gegroepeerd per type.';
        $lines[] = '';

        // Group actions per type, maintaining reference to their parent rule.
        /** @var array<string, list<array{rule: LogicRule, action: LogicAction}>> $byType */
        $byType = [];
        foreach ($data->logicRules as $rule) {
            foreach ($rule->actions as $action) {
                $byType[$action->type][] = ['rule' => $rule, 'action' => $action];
            }
        }
        ksort($byType);

        foreach ($byType as $type => $entries) {
            $lines[] = '';
            $lines[] = '### `'.$type.'` ('.count($entries).') {'.$this->anchor('logica-'.$type).'}';
            $lines[] = '';
            $lines[] = $this->renderLogicTable($type, $entries);
        }

        return implode("\n", $lines)."\n";
    }

    /** @param list<array{rule: LogicRule, action: LogicAction}> $entries */
    private function renderLogicTable(string $type, array $entries): string
    {
        $lines = [];
        $lines[] = '| # | Rule | Trigger stap | Target | Extra | Trigger (JsonLogic) |';
        $lines[] = '|---|---|---|---|---|---|';
        $i = 1;
        foreach ($entries as $entry) {
            $rule = $entry['rule'];
            $action = $entry['action'];
            $trigger = $this->formatJsonLogicInline($rule->jsonLogicTrigger);
            $ruleLabel = $rule->description === '' ? '`'.substr($rule->uuid, 0, 8).'…`' : $this->truncate($rule->description, 60);
            $triggerStep = $rule->triggerFromStepName ?? '—';
            $target = $this->formatActionTarget($action);
            $extra = $this->formatActionExtra($action);
            $lines[] = sprintf(
                '| %d | %s | %s | %s | %s | %s |',
                $i,
                $this->escape($ruleLabel),
                $this->escape($triggerStep),
                $target,
                $extra,
                $trigger,
            );
            $i++;
        }

        return implode("\n", $lines);
    }

    private function formatActionTarget(LogicAction $action): string
    {
        if ($action->componentKey !== null && $action->componentKey !== '') {
            $label = $action->componentLabel !== null
                ? ' ('.$this->escape($this->truncate($action->componentLabel, 40)).')'
                : '';

            return '`'.$action->componentKey.'`'.$label;
        }
        if ($action->variableKey !== null && $action->variableKey !== '') {
            return 'var: `'.$action->variableKey.'`';
        }
        if ($action->formStepUuid !== null) {
            $name = $action->formStepName ?? substr($action->formStepUuid, 0, 8).'…';

            return 'stap: '.$this->escape($name);
        }

        return '—';
    }

    private function formatActionExtra(LogicAction $action): string
    {
        $payload = $action->payload;

        switch ($action->type) {
            case 'property':
                $prop = $payload['property'] ?? null;
                $state = $payload['state'] ?? null;
                if (is_array($prop) && isset($prop['value'])) {
                    return '`'.$prop['value'].'` = `'.$this->inlineValue($state).'`';
                }

                return '';

            case 'variable':
                $value = $payload['value'] ?? null;

                return '`'.$this->truncate($this->inlineValue($value), 60).'`';

            case 'set-registration-backend':
                return '`'.($payload['value'] ?? '').'`';

            case 'fetch-from-service':
                return '';

            case 'step-applicable':
            case 'step-not-applicable':
                return '';

            default:
                unset($payload['type']);

                return $this->truncate($this->inlineValue($payload), 60);
        }
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $trigger
     */
    private function formatJsonLogicInline(array $trigger): string
    {
        $encoded = json_encode($trigger, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return '';
        }

        return '<code>'.htmlspecialchars($this->truncate($encoded, 120), ENT_QUOTES).'</code>';
    }

    private function stepHeading(Step $step): string
    {
        return 'Stap '.$step->index.': '.$step->name;
    }

    private function truncate(string $value, int $max): string
    {
        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max - 1).'…';
    }

    private function truncateForTable(string $value): string
    {
        // Labels keep their full form elsewhere (JSON sidecar). For the
        // markdown table we preserve the whole label but normalize newlines.
        return trim(str_replace(["\r\n", "\n", '|'], [' ', ' ', '\\|'], $value));
    }

    private function inlineValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return '—';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? '(niet codeerbaar)' : $encoded;
    }

    private function yamlString(string $value): string
    {
        if ($value === '' || str_contains($value, ':') || str_contains($value, '#') || str_contains($value, '\'') || str_contains($value, '"')) {
            return '"'.str_replace('"', '\\"', $value).'"';
        }

        return $value;
    }

    private function anchor(string $text): string
    {
        $slug = strtolower($text);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;

        return '#'.trim($slug, '-');
    }

    private function escape(string $value): string
    {
        return str_replace(['|', "\n", "\r"], ['\\|', ' ', ' '], $value);
    }

    private function escapeCode(string $value): string
    {
        return str_replace('`', '\`', $value);
    }
}
