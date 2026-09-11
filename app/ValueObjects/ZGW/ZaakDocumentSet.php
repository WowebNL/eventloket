<?php

declare(strict_types=1);

namespace App\ValueObjects\ZGW;

use App\Models\Zaak;
use Illuminate\Support\Collection;

/**
 * The documents read for a zaak, together with how many of them the documents
 * API refused to hand over.
 *
 * A read that skips a refused document returns an incomplete list, and anything
 * showing that list has to be able to say so: a gap that looks like a complete
 * list is worse than an error. Callers that cannot tolerate a gap never ask for
 * a skipping read at all.
 *
 * @see Zaak::documentenForDisplay()
 */
final readonly class ZaakDocumentSet
{
    /**
     * @param  Collection<int, Informatieobject>  $documenten  the documents the caller may see
     * @param  int  $readableCount  documents that were read, before the status and role filters
     * @param  int  $unreadableCount  documents the documents API refused to hand over
     */
    public function __construct(
        public Collection $documenten,
        public int $readableCount = 0,
        public int $unreadableCount = 0,
    ) {}

    public function isIncomplete(): bool
    {
        return $this->unreadableCount > 0;
    }

    /**
     * How many documents the zaak holds at all, before the status and role
     * filters run. Refused documents count: they exist, they just could not be
     * read, and leaving them out here would let a screen claim the zaak has no
     * documents yet.
     */
    public function totalCount(): int
    {
        return $this->readableCount + $this->unreadableCount;
    }

    /**
     * The same read, narrowed to the documents this caller may see. The counts
     * describe the read itself and therefore stay as they are.
     *
     * @param  Collection<int, Informatieobject>  $documenten
     */
    public function withDocumenten(Collection $documenten): self
    {
        return new self($documenten, $this->readableCount, $this->unreadableCount);
    }
}
