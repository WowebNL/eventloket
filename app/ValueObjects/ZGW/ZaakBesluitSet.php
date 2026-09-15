<?php

declare(strict_types=1);

namespace App\ValueObjects\ZGW;

use App\Models\Zaak;
use Illuminate\Support\Collection;

/**
 * The besluiten read for a zaak, together with how many of their documents the
 * documents API did not hand over, split by whether that is expected to pass.
 *
 * A besluit is only shown once it carries an established document, so a document
 * that was not handed over can make the besluit itself disappear rather than
 * merely shorten its file list. That is exactly the silent gap a reader cannot
 * spot, which is why the counts travel with the result instead of being dropped
 * on the floor.
 *
 * The split is the same one {@see ZaakDocumentSet} makes: a document the API is
 * not authorised to return stays refused however long the reader waits, while a
 * server error or a timeout is the case where waiting helps.
 *
 * @see Zaak::besluitenForDisplay()
 */
final readonly class ZaakBesluitSet
{
    /**
     * @param  Collection<int, Besluit>  $besluiten  the besluiten the caller may see
     * @param  int  $unavailableDocumentCount  besluit documents the API failed to return for a reason that may pass
     * @param  int  $forbiddenDocumentCount  besluit documents the API is not authorised to return
     */
    public function __construct(
        public Collection $besluiten,
        public int $unavailableDocumentCount = 0,
        public int $forbiddenDocumentCount = 0,
    ) {}

    public function isIncomplete(): bool
    {
        return $this->unavailableDocumentCount > 0 || $this->forbiddenDocumentCount > 0;
    }

    /**
     * Whether a besluit document was left out for a reason that may pass. The
     * only one of the two counts that may reach the screen.
     */
    public function hasUnavailableDocuments(): bool
    {
        return $this->unavailableDocumentCount > 0;
    }

    /**
     * Whether a besluit document was left out because the API is not authorised
     * to hand it over. A yes-or-no on purpose, see {@see Zaak} on why the number
     * must not reach the screen.
     */
    public function hasForbiddenDocuments(): bool
    {
        return $this->forbiddenDocumentCount > 0;
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
     * The same read, narrowed to the besluiten this caller may see. The counts
     * describe the read itself and therefore stay as they are.
     *
     * @param  Collection<int, Besluit>  $besluiten
     */
    public function withBesluiten(Collection $besluiten): self
    {
        return new self($besluiten, $this->unavailableDocumentCount, $this->forbiddenDocumentCount);
    }
}
