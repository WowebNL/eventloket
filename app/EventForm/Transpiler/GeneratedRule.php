<?php

declare(strict_types=1);

namespace App\EventForm\Transpiler;

/**
 * Resultaat van `RuleClassGenerator::generate()`: class-naam + file-inhoud.
 * Wordt door de TranspileEventForm-command weggeschreven naar
 * `app/EventForm/Rules/{className}.php`.
 */
final readonly class GeneratedRule
{
    public function __construct(
        public string $className,
        public string $fileContent,
        public string $uuid,
    ) {}
}
