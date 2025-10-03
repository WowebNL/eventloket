<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

final readonly class Besluit implements Arrayable
{
    public ?array $otherParams;

    public ?string $name;

    public function __construct(
        public string $url,
        public string $identificatie,
        public string $besluittype,
        public string $zaak,
        public string $datum,
        public string $toelichting,
        public string $ingangsdatum,
        public string $verzenddatum,
        public ?string $vervaldatum = null,
        public ?BesluitType $besluittypeObject = null,
        public ?Collection $besluitDocumenten = null,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
        if ($this->besluittypeObject) {
            $this->name = $this->besluittypeObject->omschrijving;
        } else {
            $this->name = null;
        }
    }

    public function toArray(): array
    {
        return [
            'url' => $this->url,
            'name' => $this->name,
            'identificatie' => $this->identificatie,
            'besluittype' => $this->besluittype,
            'zaak' => $this->zaak,
            'datum' => $this->datum,
            'toelichting' => $this->toelichting,
            'besluittypeObject' => $this->besluittypeObject?->toArray(),
            'besluitDocumenten' => $this->besluitDocumenten?->map(fn (Informatieobject $doc) => $doc->toArray())->all(),
            'ingangsdatum' => $this->ingangsdatum,
            'verzenddatum' => $this->verzenddatum,
            'vervaldatum' => $this->vervaldatum,
            'otherParams' => $this->otherParams,
        ];
    }

    public function toArrayWithObjects(): array
    {
        return array_merge($this->toArray(), [
            'besluittypeObject' => $this->besluittypeObject,
            'besluitDocumenten' => $this->besluitDocumenten,
        ]);
    }
}
