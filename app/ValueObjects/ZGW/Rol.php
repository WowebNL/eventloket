<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

final readonly class Rol implements Arrayable
{
    /** @phpstan-ignore constructor.unusedParameter */
    public function __construct(
        public string $url,
        public string $uuid,
        public string $betrokkeneType,
        public string $roltype,
        public string $omschrijving,
        public string $omschrijvingGeneriek,
        public array $contactpersoonRol,
        public array $betrokkeneIdentificatie,
        ...$otherParams
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => $this->url,
            'betrokkeneType' => $this->betrokkeneType,
            'roltype' => $this->roltype,
            'omschrijving' => $this->omschrijving,
            'omschrijvingGeneriek' => $this->omschrijvingGeneriek,
            'contactpersoonRol' => $this->contactpersoonRol,
            'betrokkeneIdentificatie' => $this->betrokkeneIdentificatie,
        ];
    }
}
