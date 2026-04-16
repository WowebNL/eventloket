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

    public function __construct(
        private readonly JsonLogicCompiler $logic = new JsonLogicCompiler,
        private readonly ActionCompiler $actions = new ActionCompiler(new JsonLogicCompiler),
    ) {}

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

        $fileContent = $this->renderClassFile(
            className: $className,
            uuid: $uuid,
            description: $description,
            triggerExpr: $triggerExpr,
            actionStatements: $actionStatements,
        );

        return new GeneratedRule(
            className: $className,
            fileContent: $fileContent,
            uuid: $uuid,
        );
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

    private function renderClassFile(
        string $className,
        string $uuid,
        string $description,
        string $triggerExpr,
        string $actionStatements,
    ): string {
        $descriptionSanitized = str_replace(['*/', "\r\n", "\n"], ['* /', ' ', ' '], $description);

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
}
