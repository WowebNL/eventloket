<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

class FormVariable implements Arrayable
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $source,
        public readonly string $dataType,
        public readonly string $dataFormat,
        public readonly mixed $initialValue,
        public readonly string $prefillPlugin,
        public readonly string $prefillAttribute,
        public readonly string $prefillIdentifierRole,
        public readonly bool $isSensitiveData,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'source' => $this->source,
            'data_type' => $this->dataType,
            'data_format' => $this->dataFormat,
            'initial_value' => $this->initialValue,
            'prefill_plugin' => $this->prefillPlugin,
            'prefill_attribute' => $this->prefillAttribute,
            'prefill_identifier_role' => $this->prefillIdentifierRole,
            'is_sensitive_data' => $this->isSensitiveData,
        ];
    }
}
