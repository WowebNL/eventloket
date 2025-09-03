<?php

namespace App\ValueObjects\ObjectsApi;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class FormSubmissionObject implements Arrayable
{
    public readonly ?array $otherParams;

    public readonly ?array $zaakeigenschappen;

    public readonly ?array $zaakeigenschappen_key_value;

    public function __construct(
        public readonly string $uuid,
        public readonly string $type,
        public readonly array $record,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
        $this->zaakeigenschappen = $this->record['data']['zaakeigenschappen'] ?? null;
        $this->zaakeigenschappen_key_value = $this->zaakeigenschappen
            ? Arr::mapWithKeys($this->zaakeigenschappen, fn ($item) => [key($item) => current($item)])
            : [];
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'record' => $this->record,
            'otherParams' => $this->otherParams,
        ];
    }
}
