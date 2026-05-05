<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Parsers;

use App\Services\OpenForms\Veldenkaart\Data\Step;

class StepParser
{
    public function __construct(
        private readonly ComponentParser $componentParser,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rawSteps
     * @return list<Step>
     */
    public function parse(array $rawSteps): array
    {
        $steps = [];
        foreach ($rawSteps as $raw) {
            $configuration = $raw['configuration'] ?? [];
            $components = [];
            if (is_array($configuration) && isset($configuration['components']) && is_array($configuration['components'])) {
                /** @var list<array<string, mixed>> $components */
                $components = array_values(array_filter(
                    $configuration['components'],
                    static fn ($c): bool => is_array($c),
                ));
            }

            $slug = $this->str($raw, 'slug');
            $name = $this->str($raw, 'name');
            $internalName = $this->str($raw, 'internal_name');
            $uuid = $this->str($raw, 'uuid');
            $index = is_int($raw['index'] ?? null) ? $raw['index'] : 0;
            $isReusable = (bool) ($raw['is_reusable'] ?? false);
            $loginRequired = (bool) ($raw['login_required'] ?? false);

            if ($name === '' && is_string($raw['form_definition']['name'] ?? null)) {
                $name = $raw['form_definition']['name'];
            }

            $fields = $this->componentParser->parseStepComponents($components, $slug);

            $steps[] = new Step(
                index: $index,
                uuid: $uuid,
                slug: $slug,
                name: $name,
                internalName: $internalName !== '' ? $internalName : null,
                loginRequired: $loginRequired,
                isReusable: $isReusable,
                fields: $fields,
            );
        }

        usort($steps, static fn (Step $a, Step $b): int => $a->index <=> $b->index);

        return $steps;
    }

    /** @param array<string, mixed> $source */
    private function str(array $source, string $key): string
    {
        $value = $source[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
