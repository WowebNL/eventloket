<?php

declare(strict_types=1);

namespace App\Services\OpenForms\Veldenkaart\Data;

use Illuminate\Contracts\Support\Arrayable;

class LogicAction implements Arrayable
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $uuid,
        public readonly string $type,
        public readonly ?string $componentKey,
        public readonly ?string $componentLabel,
        public readonly ?string $variableKey,
        public readonly ?string $formStepUuid,
        public readonly ?string $formStepName,
        public readonly array $payload,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'component_key' => $this->componentKey,
            'component_label' => $this->componentLabel,
            'variable_key' => $this->variableKey,
            'form_step_uuid' => $this->formStepUuid,
            'form_step_name' => $this->formStepName,
            'payload' => $this->payload,
        ];
    }
}
