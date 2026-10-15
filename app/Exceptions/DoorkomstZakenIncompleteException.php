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
     * @param  bool  $retriedAutomatically  false when the job is marked as failed without a retry
     */
    public static function forHoofdzaak(int|string $zaakId, array $failedMunicipalities, int $attempted, ?Throwable $previous = null, bool $retriedAutomatically = true): self
    {
        return new self(
            sprintf(
                'Doorkomst deelzaken could not be created or completed for %d of the %d municipalities the route of zaak %s passes (%s); %s',
                count($failedMunicipalities),
                $attempted,
                (string) $zaakId,
                implode(', ', $failedMunicipalities),
                $retriedAutomatically
                    ? 'a retry only creates the ones that are still missing.'
                    : 'at least one deelzaak was created but is incomplete, so the job is not retried automatically. A retry does not repair an incomplete deelzaak; a deelzaak that is still missing is created by retrying this failed job or by running zaak:create-doorkomst-zaken for the zaak.',
            ),
            $zaakId,
            $failedMunicipalities,
            $previous,
        );
    }
}
