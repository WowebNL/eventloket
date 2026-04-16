<?php

declare(strict_types=1);

namespace App\EventForm\Transpiler;

use RuntimeException;

/**
 * Genereert per logic-rule een complete PHP Rule-klasse-tekst.
 *
 * Class-naam komt uit de rule.description (PascalCase, gesaniteerd); valt
 * terug op `Rule<uuidPrefix>` als description leeg is. Bij naam-collisies
 * tussen rules wordt een uuid-suffix toegevoegd om uniciteit te garanderen.
 *
 * De generator is stateful (houdt gebruikte namen bij) en hoort per
 * transpile-run opnieuw geïnstantieerd te worden.
 */
class RuleClassGenerator
{
    /** @var array<string, true> */
    private array $usedNames = [];

    /** @var array<string, list<string>> stepUuid → lijst van veld-keys op die stap */
    private array $stepFieldIndex = [];

    public function __construct(
        private readonly JsonLogicCompiler $logic = new JsonLogicCompiler,
        private readonly ActionCompiler $actions = new ActionCompiler(new JsonLogicCompiler),
        private readonly RuleDependencyAnalyzer $deps = new RuleDependencyAnalyzer,
    ) {}

    /**
     * Voorzie de generator van een stepUuid → veld-keys mapping, zodat
     * `triggerStepUuids()` en `effectStepUuids()` per rule berekend kunnen
     * worden.
     *
     * @param  array<string, list<string>>  $index
     */
    public function withStepFieldIndex(array $index): self
    {
        $this->stepFieldIndex = $index;

        return $this;
    }

    /** @param  array<string, mixed>  $rule */
    public function generate(array $rule): GeneratedRule
    {
        $uuid = (string) ($rule['uuid'] ?? '');
        if ($uuid === '') {
            throw new RuntimeException('Rule is missing uuid');
        }

        $description = (string) ($rule['description'] ?? '');
        $preferredName = $this->buildClassName($description, $uuid);
        $className = $this->ensureUnique($preferredName, $uuid);

        $triggerExpr = $this->logic->compile($rule['json_logic_trigger'] ?? false);
        $actionStatements = $this->compileActions($rule['actions'] ?? []);

        $triggerSteps = $this->resolveTriggerSteps($rule['json_logic_trigger'] ?? null);
        $effectSteps = $this->resolveEffectSteps($rule['actions'] ?? []);

        $fileContent = $this->renderClassFile(
            className: $className,
            uuid: $uuid,
            description: $description,
            triggerExpr: $triggerExpr,
            actionStatements: $actionStatements,
            triggerSteps: $triggerSteps,
            effectSteps: $effectSteps,
        );

        return new GeneratedRule(
            className: $className,
            fileContent: $fileContent,
            uuid: $uuid,
        );
    }

    /**
     * @return list<string>
     */
    private function resolveTriggerSteps(mixed $trigger): array
    {
        if ($this->stepFieldIndex === []) {
            return [];
        }
        $readKeys = $this->deps->readKeys($trigger);
        if ($readKeys === []) {
            return [];
        }

        $steps = [];
        foreach ($this->stepFieldIndex as $stepUuid => $fields) {
            foreach ($readKeys as $key) {
                if (in_array($key, $fields, true)) {
                    $steps[] = $stepUuid;
                    break;
                }
            }
        }

        return array_values(array_unique($steps));
    }

    /**
     * @return list<string>
     */
    private function resolveEffectSteps(mixed $actions): array
    {
        if (! is_array($actions)) {
            return [];
        }

        $steps = [];
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }

            $payload = $action['action'] ?? [];
            $type = is_array($payload) ? ($payload['type'] ?? '') : '';
            if (($type === 'step-applicable' || $type === 'step-not-applicable')
                && is_string($action['form_step_uuid'] ?? null)
                && $action['form_step_uuid'] !== '') {
                $steps[] = $action['form_step_uuid'];
            }

            $componentKey = is_string($action['component'] ?? null) ? $action['component'] : '';
            if ($componentKey !== '' && $this->stepFieldIndex !== []) {
                foreach ($this->stepFieldIndex as $stepUuid => $fields) {
                    if (in_array($componentKey, $fields, true)) {
                        $steps[] = $stepUuid;
                        break;
                    }
                }
            }
        }

        return array_values(array_unique($steps));
    }

    private function compileActions(mixed $actions): string
    {
        if (! is_array($actions)) {
            return '';
        }

        $lines = [];
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }
            /** @var array<string, mixed> $action */
            $statement = $this->actions->compile($action);
            if ($statement !== '') {
                $lines[] = '        '.$statement;
            }
        }

        return implode("\n", $lines);
    }

    private function buildClassName(string $description, string $uuid): string
    {
        $description = trim($description);
        if ($description === '') {
            return 'Rule'.$this->uuidPrefix($uuid);
        }

        // Strip template-placeholders die description soms bevatten.
        $description = preg_replace('/\{\{[^}]+\}\}/', '', $description) ?? $description;

        // Split op non-word chars; behoud ASCII-letters/digits.
        $transliterated = $this->transliterate($description);
        $parts = preg_split('/[^a-zA-Z0-9]+/', $transliterated) ?: [];
        $parts = array_values(array_filter($parts, static fn ($p): bool => $p !== ''));

        if ($parts === []) {
            return 'Rule'.$this->uuidPrefix($uuid);
        }

        $name = implode('', array_map(static fn (string $p): string => ucfirst(strtolower($p)), $parts));

        // Kap op redelijke lengte zodat file-namen niet absurd lang worden.
        if (strlen($name) > 80) {
            $name = substr($name, 0, 80);
        }

        if (! preg_match('/^[A-Za-z]/', $name)) {
            $name = 'Rule'.$name;
        }

        return $name;
    }

    private function ensureUnique(string $name, string $uuid): string
    {
        if (! isset($this->usedNames[$name])) {
            $this->usedNames[$name] = true;

            return $name;
        }

        $candidate = $name.$this->uuidPrefix($uuid);
        $i = 2;
        while (isset($this->usedNames[$candidate])) {
            $candidate = $name.$this->uuidPrefix($uuid).$i;
            $i++;
        }
        $this->usedNames[$candidate] = true;

        return $candidate;
    }

    private function uuidPrefix(string $uuid): string
    {
        $clean = preg_replace('/[^A-Za-z0-9]/', '', $uuid) ?? $uuid;

        return ucfirst(strtolower(substr($clean, 0, 8)));
    }

    private function transliterate(string $value): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

        return $converted === false ? $value : $converted;
    }

    /**
     * @param  list<string>  $triggerSteps
     * @param  list<string>  $effectSteps
     */
    private function renderClassFile(
        string $className,
        string $uuid,
        string $description,
        string $triggerExpr,
        string $actionStatements,
        array $triggerSteps,
        array $effectSteps,
    ): string {
        $descriptionSanitized = str_replace(['*/', "\r\n", "\n"], ['* /', ' ', ' '], $description);
        $triggerStepsPhp = $this->renderStepArrayLiteral($triggerSteps);
        $effectStepsPhp = $this->renderStepArrayLiteral($effectSteps);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace App\\EventForm\\Rules;

        use App\\EventForm\\State\\FormState;

        /**
         * @openforms-rule-uuid {$uuid}
         * @openforms-rule-description {$descriptionSanitized}
         */
        final class {$className} implements Rule
        {
            public function identifier(): string
            {
                return '{$uuid}';
            }

            public function triggerStepUuids(): array
            {
                return {$triggerStepsPhp};
            }

            public function effectStepUuids(): array
            {
                return {$effectStepsPhp};
            }

            public function applies(FormState \$s): bool
            {
                return (bool) ({$triggerExpr});
            }

            public function apply(FormState \$s): void
            {
        {$actionStatements}
            }
        }

        PHP;
    }

    /** @param  list<string>  $steps */
    private function renderStepArrayLiteral(array $steps): string
    {
        if ($steps === []) {
            return '[]';
        }
        $items = array_map(static fn (string $s): string => var_export($s, true), $steps);

        return '['.implode(', ', $items).']';
    }
}
