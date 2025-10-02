<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

final readonly class BesluitType implements Arrayable
{
    public ?array $otherParams;

    public function __construct(
        public string $url,
        public string $omschrijving,
        public string $omschrijvingGeneriek,
        public array $zaaktypen,
        public array $informatieobjecttypen,
        public string $toelichting,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'omschrijving' => $this->omschrijving,
            'omschrijvingGeneriek' => $this->omschrijvingGeneriek,
            'zaaktypen' => $this->zaaktypen,
            'informatieobjecttypen' => $this->informatieobjecttypen,
            'toelichting' => $this->toelichting,
            'otherParams' => $this->otherParams,
        ];
    }
}
