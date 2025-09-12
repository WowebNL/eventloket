<?php

namespace App\ValueObjects\Pdok;

use Illuminate\Contracts\Support\Arrayable;

class BagObject implements Arrayable
{
    public readonly ?array $otherParams;

    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $centroide_ll,
        public readonly string $weergavenaam,
        public readonly string $straatnaam,
        public readonly string $postcode,
        public readonly string $huisnummer,
        public readonly string $gemeentecode,
        public readonly string $woonplaatsnaam,
        public readonly string $huisletter = '',
        public readonly string $huisnummertoevoeging = '',
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'centroide_ll' => $this->centroide_ll,
            'weergavenaam' => $this->weergavenaam,
            'straatnaam' => $this->straatnaam,
            'postcode' => $this->postcode,
            'huisnummer' => $this->huisnummer,
            'woonplaatsnaam' => $this->woonplaatsnaam,
            'huisletter' => $this->huisletter,
            'huisnummertoevoeging' => $this->huisnummertoevoeging,
            'otherParams' => $this->otherParams,
        ];
    }
}
