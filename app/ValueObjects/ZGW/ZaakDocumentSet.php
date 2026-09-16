<?php

declare(strict_types=1);

namespace App\ValueObjects\ZGW;

use App\Models\Zaak;
use Illuminate\Support\Collection;

/**
 * The documents read for a zaak, together with how many of them the documents
 * API did not hand over, split by whether that is expected to pass.
 *
 * A read that skips a document returns an incomplete list, and anything showing
 * that list has to be able to say so: a gap that looks like a complete list is
 * worse than an error. Callers that cannot tolerate a gap never ask for a
 * skipping read at all.
 *
 * The split matters to the reader. A document the API is not authorised to hand
 * over is refused every time it is asked for, so telling the reader to try again
 * later promises something that is never going to happen; a server error or a
 * timeout is the case where trying again later is exactly right.
 *
 * @see Zaak::documentenForDisplay()
 */
final readonly class ZaakDocumentSet
{
    /**
     * @param  Collection<int, Informatieobject>  $documenten  the documents the caller may see
     * @param  int  $readableCount  documents that were read, before the status and role filters
     * @param  int  $unavailableCount  documents the documents API failed to return for a reason that may pass
     * @param  int  $forbiddenCount  documents the documents API is not authorised to return
     */
    public function __construct(
        public Collection $documenten,
        public int $readableCount = 0,
        public int $unavailableCount = 0,
        public int $forbiddenCount = 0,
    ) {}

    public function isIncomplete(): bool
    {
        return $this->unavailableCount > 0 || $this->forbiddenCount > 0;
    }

    /**
     * Whether a document was left out because the API would not return it for a
     * reason that may pass. This is the only count that may reach the screen: it
     * describes a transient gap in a list the reader is entitled to see.
     */
    public function hasUnavailable(): bool
    {
        return $this->unavailableCount > 0;
    }

    /**
     * Whether a document was left out because the API is not authorised to hand
     * it over. Deliberately a yes-or-no and not a count, see {@see Zaak} on why
     * the number must not reach the screen.
     */
    public function hasForbidden(): bool
    {
        return $this->forbiddenCount > 0;
    }

    /**
     * How many documents the zaak holds at all, before the status and role
     * filters run. Documents that were not handed over count: they exist, they
     * just could not be read, and leaving them out here would let a screen claim
     * the zaak has no documents yet.
     */
    public function totalCount(): int
    {
        return $this->readableCount + $this->unavailableCount + $this->forbiddenCount;
    }

    /**
     * The same read, narrowed to the documents this caller may see. The counts
     * describe the read itself and therefore stay as they are.
     *
     * @param  Collection<int, Informatieobject>  $documenten
     */
    public function withDocumenten(Collection $documenten): self
    {
        return new self($documenten, $this->readableCount, $this->unavailableCount, $this->forbiddenCount);
    }
}
