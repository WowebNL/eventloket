<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

/** note: an informatieobject is a document */
class InformatieobjectType implements Arrayable
{
    /** @phpstan-ignore constructor.unusedParameter */
    public function __construct(
        public readonly string $uuid,
        public readonly string $url,
        public readonly string $omschrijving,
        public readonly string $vertrouwelijkheidaanduiding,
        ...$otherParams
    ) {}

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'url' => $this->url,
            'omschrijving' => $this->omschrijving,
            'vertrouwelijkheidaanduiding' => $this->vertrouwelijkheidaanduiding,
        ];
    }
}
