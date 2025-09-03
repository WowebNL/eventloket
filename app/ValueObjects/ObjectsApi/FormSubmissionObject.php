<?php

namespace App\ValueObjects\ObjectsApi;

use Illuminate\Contracts\Support\Arrayable;

class FormSubmissionObject implements Arrayable
{
    public readonly ?array $otherParams;

    public readonly ?array $zaakeigenschappen;

    public function __construct(
        public readonly string $uuid,
        public readonly string $type,
        public readonly array $record,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
        $this->zaakeigenschappen = $this->record['data']['zaakeigenschappen'] ?? null;
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
