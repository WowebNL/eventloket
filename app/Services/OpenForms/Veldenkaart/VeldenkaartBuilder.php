<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart;

use App\Services\OpenForms\Veldenkaart\Data\FormMeta;
use App\Services\OpenForms\Veldenkaart\Data\RawFormData;
use App\Services\OpenForms\Veldenkaart\Data\VeldenkaartData;
use App\Services\OpenForms\Veldenkaart\Parsers\ComponentParser;
use App\Services\OpenForms\Veldenkaart\Parsers\FormVariableParser;
use App\Services\OpenForms\Veldenkaart\Parsers\LogicRuleParser;
use App\Services\OpenForms\Veldenkaart\Parsers\StepParser;
use App\Services\OpenForms\Veldenkaart\Parsers\TemplateVariableExtractor;

class VeldenkaartBuilder
{
    public function __construct(
        private readonly ComponentParser $componentParser = new ComponentParser,
        private readonly LogicRuleParser $logicParser = new LogicRuleParser,
        private readonly FormVariableParser $variableParser = new FormVariableParser,
        private readonly TemplateVariableExtractor $templateExtractor = new TemplateVariableExtractor,
    ) {}

    public function build(RawFormData $raw): VeldenkaartData
    {
        $variables = $this->variableParser->parse($raw->variables);
        $this->componentParser->buildFieldTypeIndex($raw->formSteps, $variables);

        $stepParser = new StepParser($this->componentParser);
        $steps = $stepParser->parse($raw->formSteps);

        $this->logicParser->buildIndex($steps);
        $logicRules = $this->logicParser->parse($raw->logicRules);

        $templateVariables = $this->templateExtractor->extract($steps, $logicRules);

        $meta = new FormMeta(
            uuid: $this->str($raw->form, 'uuid'),
            slug: $this->str($raw->form, 'slug'),
            name: $this->str($raw->form, 'name'),
            internalName: $this->nullableStr($raw->form, 'internal_name'),
            active: (bool) ($raw->form['active'] ?? false),
            ofRelease: $this->nullableStrFromMeta($raw->meta, 'of_release'),
            ofGitSha: $this->nullableStrFromMeta($raw->meta, 'of_git_sha'),
            generatedAt: now()->toIso8601String(),
            source: $this->nullableStrFromMeta($raw->meta, 'source') ?? 'unknown',
        );

        return new VeldenkaartData(
            meta: $meta,
            steps: $steps,
            logicRules: $logicRules,
            formVariables: $variables,
            templateVariables: $templateVariables,
        );
    }

    /** @param array<string, mixed> $source */
    private function str(array $source, string $key): string
    {
        $value = $source[$key] ?? '';

        return is_string($value) ? $value : '';
    }

    /** @param array<string, mixed> $source */
    private function nullableStr(array $source, string $key): ?string
    {
        $value = $source[$key] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /** @param array<string, mixed> $meta */
    private function nullableStrFromMeta(array $meta, string $key): ?string
    {
        $value = $meta[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
