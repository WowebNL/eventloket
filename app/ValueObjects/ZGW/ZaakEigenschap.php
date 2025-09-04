<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

class ZaakEigenschap implements Arrayable
{
    public readonly ?array $otherParams;

    public function __construct(
        public readonly string $uuid,
        public readonly string $url,
        public readonly string $zaak,
        public readonly string $eigenschap,
        public readonly string $naam,
        public readonly string $waarde,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'naam' => $this->naam,
            'waarde' => $this->waarde,
            'otherParams' => $this->otherParams,
            'eigenschap' => $this->eigenschap,
            'uuid' => $this->uuid,
        ];
    }
}
