<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown at the end of a doorkomst run in which one or more municipalities did
 * not get a complete deelzaak, after every other municipality on the route has
 * been handled.
 *
 * The job isolates a failure per municipality so one refusing ZGW API does not
 * cost the rest of the route its deelzaken, and then fails with this exception
 * so the missing or incomplete ones stay visible as a failed job. A retry is
 * safe: deelzaken that are already registered are skipped, so a retry only
 * creates the ones that are still missing; it does not repair an incomplete
 * one ({@see DoorkomstDeelzaakIncompleteException}). The first underlying
 * failure is kept as the previous exception.
 */
class DoorkomstZakenIncompleteException extends RuntimeException
{
    /**
     * @param  list<string>  $failedMunicipalities  BRK identifications of the municipalities without a complete deelzaak
     */
    public function __construct(
        string $message,
        public readonly int|string $zaakId,
        public readonly array $failedMunicipalities,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param  list<string>  $failedMunicipalities
     */
    public static function forHoofdzaak(int|string $zaakId, array $failedMunicipalities, int $attempted, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Doorkomst deelzaken could not be created or completed for %d of the %d municipalities the route of zaak %s passes (%s); a retry only creates the ones that are still missing.',
                count($failedMunicipalities),
                $attempted,
                (string) $zaakId,
                implode(', ', $failedMunicipalities),
            ),
            $zaakId,
            $failedMunicipalities,
            $previous,
        );
    }
}
