<?php

namespace App\ValueObjects\ZGW;

use Illuminate\Contracts\Support\Arrayable;

/** note: an informatieobject is a document */
class Informatieobject implements Arrayable
{
    /**
     * ZGW document status for a finalised document. We send this on every
     * document we push to a ZGW connection so behandelaars and downstream
     * systems treat our uploads as final rather than drafts.
     */
    public const STATUS_DEFINITIEF = 'definitief';

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
     * Strict allowlist: only the 'definitief' status and documents without an
     * explicit status (our own uploads and legacy documents) are shown. Every
     * other status is hidden. This includes the draft statuses (in_bewerking,
     * ter_vaststelling, concept), 'gearchiveerd' (archived documents must never
     * be shown, not even to users who may otherwise see documents), and any
     * unknown or future status.
     */
    public function isDefinitief(): bool
    {
        return $this->status === null
            || $this->status === ''
            || $this->status === self::STATUS_DEFINITIEF;
    }
}
