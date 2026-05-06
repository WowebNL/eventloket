<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

class TemplateVariable implements Arrayable
{
    /**
     * @param  list<array<string, string>>  $occurrences  each occurrence has keys: step, field_key, location
     */
    public function __construct(
        public readonly string $placeholder,
        public readonly string $root,
        public readonly string $accessor,
        public readonly array $occurrences,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'placeholder' => $this->placeholder,
            'root' => $this->root,
            'accessor' => $this->accessor,
            'occurrence_count' => count($this->occurrences),
            'occurrences' => $this->occurrences,
        ];
    }
}
