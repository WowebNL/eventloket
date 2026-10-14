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

    /**
     * ZGW document status for a document that has been archived: its form and
     * content are frozen for archival purposes. It is emphatically not a
     * statement about availability, and the document stays retrievable until it
     * is actually destroyed or transferred (at which point it disappears from
     * the API on its own).
     */
    public const STATUS_GEARCHIVEERD = 'gearchiveerd';

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
     * Whether this document may be shown to and notified about, judged on its
     * status alone. Who may see it is a separate question, answered by the
     * vertrouwelijkheidaanduiding and the user's role.
     *
     * Allowlist: 'definitief', 'gearchiveerd', and documents without an explicit
     * status (our own uploads and legacy documents). The draft statuses
     * (in_bewerking, ter_vaststelling, concept) and any unknown or future status
     * stay hidden.
     *
     * 'gearchiveerd' is included deliberately. In the ZGW standard it marks a
     * document as frozen for archival purposes, not as unavailable; access is
     * governed by the vertrouwelijkheidaanduiding and authorisations, and a
     * document that may genuinely no longer be seen is destroyed or transferred
     * and then simply no longer returned by the API. Excluding it made every
     * document vanish the moment a zaak was closed on a backend that archives on
     * the final status (OneGround/RX Mission does this immediately), which hid
     * the permit and its attachments from the organiser exactly when they need
     * them, and from the behandelaar consulting a closed dossier.
     */
    public function isVastgesteld(): bool
    {
        return $this->status === null
            || $this->status === ''
            || $this->status === self::STATUS_DEFINITIEF
            || $this->status === self::STATUS_GEARCHIVEERD;
    }
}
