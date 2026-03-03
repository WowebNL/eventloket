<?php

namespace App\ValueObjects;

use Illuminate\Contracts\Support\Arrayable;

class PostbusAddress implements Arrayable
{
    public function __construct(
        public readonly string $postbusnummer,
        public readonly string $postcode,
        public readonly string $woonplaatsnaam,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            postbusnummer: (string) $data['postbusnummer'],
            postcode: (string) $data['postcode'],
            woonplaatsnaam: (string) $data['woonplaatsnaam'],
        );
    }

    public function weergavenaam(): string
    {
        return "Postbus {$this->postbusnummer}, {$this->postcode} {$this->woonplaatsnaam}";
    }

    public function toArray(): array
    {
        return [
            'postbusnummer' => $this->postbusnummer,
            'postcode' => $this->postcode,
            'woonplaatsnaam' => $this->woonplaatsnaam,
        ];
    }
}
