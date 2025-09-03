<?php

namespace App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

class OzZaaktype implements Arrayable
{
    public readonly ?array $otherParams;

    public function __construct(
        public readonly string $uuid,
        public readonly string $identificatie,
        public readonly string $url,
        public readonly string $omschrijving,
        public readonly string $doorlooptijd,
        public readonly string $servicenorm,
        // private readonly array $_expand = [],
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'identificatie' => $this->identificatie,
            'url' => $this->url,
            'omschrijving' => $this->omschrijving,
            'doorlooptijd' => $this->doorlooptijd,
            'servicenorm' => $this->servicenorm,
        ];
    }
}
