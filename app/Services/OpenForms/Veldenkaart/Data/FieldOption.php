<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

class FieldOption implements Arrayable
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly ?string $source = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'value' => $this->value,
            'label' => $this->label,
            'source' => $this->source,
        ], static fn ($v) => $v !== null);
    }
}
