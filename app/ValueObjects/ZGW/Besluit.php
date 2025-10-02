<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

final readonly class Besluit implements Arrayable
{
    public ?array $otherParams;

    public function __construct(
        public string $url,
        public string $identificatie,
        public string $besluittype,
        public string $zaak,
        public string $datum,
        public string $toelichting,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'identificatie' => $this->identificatie,
            'besluittype' => $this->besluittype,
            'zaak' => $this->zaak,
            'datum' => $this->datum,
            'toelichting' => $this->toelichting,
            'otherParams' => $this->otherParams,
        ];
    }
}
