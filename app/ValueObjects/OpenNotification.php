<?php

namespace App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

class OpenNotification implements Arrayable
{
    public function __construct(
        public readonly string $actie,
        public readonly string $kanaal,
        public readonly string $resource,
        public readonly string $hoofdObject,
        public readonly string $resourceUrl,
        public readonly string $aanmaakdatum,
    ) {}

    public function toArray(): array
    {
        return [
            'actie' => $this->actie,
            'kanaal' => $this->kanaal,
            'resource' => $this->resource,
            'hoofdObject' => $this->hoofdObject,
            'resourceUrl' => $this->resourceUrl,
            'aanmaakdatum' => $this->aanmaakdatum,
        ];
    }
}
