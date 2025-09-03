<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

class CatalogiEigenschap implements Arrayable
{
    public readonly ?array $otherParams;

    public function __construct(
        public readonly string $url,
        public readonly string $naam,
        public readonly string $zaaktype,
        public readonly string $definitie,
        public readonly array $specificatie,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'naam' => $this->naam,
            'zaaktype' => $this->zaaktype,
            'definitie' => $this->definitie,
            'specificatie' => $this->specificatie,
            'otherParams' => $this->otherParams,
        ];
    }
}
