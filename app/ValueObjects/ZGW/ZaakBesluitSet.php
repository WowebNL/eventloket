<?php

declare(strict_types=1);

namespace App\ValueObjects\ZGW;

use App\Models\Zaak;
use Illuminate\Support\Collection;

/**
 * The besluiten read for a zaak, together with how many of their documents the
 * documents API refused to hand over.
 *
 * A besluit is only shown once it carries an established document, so a refused
 * document can make the besluit itself disappear rather than merely shorten its
 * file list. That is exactly the silent gap a reader cannot spot, which is why
 * the count travels with the result instead of being dropped on the floor.
 *
 * @see Zaak::besluitenForDisplay()
 */
final readonly class ZaakBesluitSet
{
    /**
     * @param  Collection<int, Besluit>  $besluiten  the besluiten the caller may see
     * @param  int  $unreadableDocumentCount  besluit documents the documents API refused
     */
    public function __construct(
        public Collection $besluiten,
        public int $unreadableDocumentCount = 0,
    ) {}

    public function isIncomplete(): bool
    {
        return $this->unreadableDocumentCount > 0;
    }

    /**
     * Whether the besluiten tab has anything to say: a besluit to show, or the
     * fact that one of its documents is missing. Without the second half a zaak
     * whose only besluit fell away would hide the tab altogether, and the reader
     * would never learn that there is a besluit they cannot see.
     */
    public function hasSomethingToShow(): bool
    {
        return $this->besluiten->isNotEmpty() || $this->isIncomplete();
    }

    /**
     * The same read, narrowed to the besluiten this caller may see. The count
     * describes the read itself and therefore stays as it is.
     *
     * @param  Collection<int, Besluit>  $besluiten
     */
    public function withBesluiten(Collection $besluiten): self
    {
        return new self($besluiten, $this->unreadableDocumentCount);
    }
}
