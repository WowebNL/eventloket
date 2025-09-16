<?php

namespace App\ValueObjects\ObjectsApi;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class FormSubmissionObject implements Arrayable
{
    public readonly ?array $otherParams;

    public readonly ?array $zaakeigenschappen;

    public readonly ?array $zaakeigenschappen_key_value;

    public readonly string $organisation_uuid;

    public readonly string $user_uuid;

    public readonly array $initiator;

    public function __construct(
        public readonly string $uuid,
        public readonly string $type,
        public readonly array $record,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
        $this->organisation_uuid = $this->record['data'][strtolower(config('app.name')).'_organisation_uuid'] ?? '';
        $this->user_uuid = $this->record['data'][strtolower(config('app.name')).'_user_uuid'] ?? '';
        $this->initiator = $this->record['data']['initiator'] ?? [];
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
            'organisation_uuid' => $this->organisation_uuid,
            'user_uuid' => $this->user_uuid,
            'initiator' => $this->initiator,
            'otherParams' => $this->otherParams,
        ];
    }
}
