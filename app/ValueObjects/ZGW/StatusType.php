<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

final readonly class StatusType implements Arrayable
{
    public ?array $otherParams;

    public function __construct(
        public string $url,
        public string $omschrijving,
        public string $omschrijvingGeneriek,
        public string $zaaktype,
        public int $volgnummer,
        public bool $isEindstatus,
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
            'zaaktype' => $this->zaaktype,
            'volnummer' => $this->volgnummer,
            'isEindstatus' => $this->isEindstatus,
            'otherParams' => $this->otherParams,
        ];
    }
}
