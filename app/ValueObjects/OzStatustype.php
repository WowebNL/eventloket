<?php

namespace App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

class OzStatustype implements Arrayable
{
    public readonly ?array $otherParams;

    public function __construct(
        public readonly string $uuid,
        public readonly string $url,
        public readonly string $omschrijving,
        public readonly int $volgnummer,
        public readonly bool $isEindstatus,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
    }

    public function isReceived(): bool
    {
        return $this->volgnummer === 1;
    }

    public function isInProgress(): bool
    {
        return $this->volgnummer > 1 && ! $this->isFinalised();
    }

    public function isFinalised(): bool
    {
        return $this->isEindstatus;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => $this->url,
            'omschrijving' => $this->omschrijving,
            'volgnummer' => $this->volgnummer,
            'isEindstatus' => $this->isEindstatus,
        ];
    }
}
