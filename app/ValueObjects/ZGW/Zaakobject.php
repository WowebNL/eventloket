<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

class Zaakobject implements Arrayable
{
    public readonly ?array $otherParams;

    public function __construct(
        public readonly string $uuid,
        public readonly string $zaak,
        public readonly string $object,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'zaak' => $this->zaak,
            'object' => $this->object,
            'otherParams' => $this->otherParams,
        ];
    }
}
