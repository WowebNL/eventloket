<?php

declare(strict_types=1);

namespace App\EventForm\Transpiler;

final readonly class GeneratedStep
{
    public function __construct(
        public string $className,
        public string $fileContent,
        public string $uuid,
        public int $index,
    ) {}
}
