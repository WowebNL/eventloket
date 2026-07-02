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
        public readonly string|int $versie,
        public readonly string $bestandsnaam,
        public readonly string $inhoud,
        // Optional in ZGW: OpenZaak returns an empty string, but some backends
        // (e.g. RX Mission) omit it or return null.
        public readonly ?string $beschrijving,
        public readonly string $informatieobjecttype,
        public readonly string $formaat,
        public readonly bool $locked,
        public readonly ?Besluit $besluit = null,
        public readonly ?string $status = null,
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
            'status' => $this->status,
        ];
    }

    /**
     * Whether this document may be shown to and notified about.
     *
     * Explicit ZGW draft statuses (concepts) are hidden. Documents without an
     * explicit status (our own uploads and legacy documents) and the
     * "definitief" status are treated as final, so existing behaviour for the
     * eigen OpenZaak is preserved while concepts from an external ZGW backend
     * (e.g. RX Mission) are filtered out.
     */
    public function isDefinitief(): bool
    {
        return ! in_array($this->status, ['in_bewerking', 'ter_vaststelling', 'concept'], true);
    }
}
