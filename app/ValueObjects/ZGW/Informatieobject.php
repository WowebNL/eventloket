<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

/** note: an informatieobject is a document */
class Informatieobject implements Arrayable
{
    /** @phpstan-ignore constructor.unusedParameter */
    public function __construct(
        public readonly string $uuid,
        public readonly string $url,
        public readonly string $creatiedatum,
        public readonly string $titel,
        public readonly string $vertrouwelijkheidaanduiding,
        public readonly string $auteur,
        public readonly string $versie,
        public readonly string $bestandsnaam,
        public readonly string $inhoud,
        public readonly string $beschrijving,
        public readonly string $informatieobjecttype,
        public readonly string $formaat,
        public readonly bool $locked,
        public readonly ?Besluit $besluit = null,
        ...$otherParams
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => $this->url,
            'creatiedatum' => $this->creatiedatum,
            'titel' => $this->titel,
            'vertrouwelijkheidaanduiding' => $this->vertrouwelijkheidaanduiding,
            'auteur' => $this->auteur,
            'versie' => $this->versie,
            'bestandsnaam' => $this->bestandsnaam,
            'inhoud' => $this->inhoud,
            'beschrijving' => $this->beschrijving,
            'informatieobjecttype' => $this->informatieobjecttype,
            'formaat' => $this->formaat,
            'locked' => $this->locked,
            'besluit' => $this->besluit?->toArray(),
        ];
    }
}
