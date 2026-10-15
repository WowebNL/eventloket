<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown for one municipality when its doorkomst deelzaak was created and
 * registered, but one or more of the steps after that (its eigenschappen,
 * initiator, documents or initial status) failed.
 *
 * Every step is still tried and the local registration is still completed
 * before this is thrown. The job then counts the municipality as failed, so
 * the incomplete deelzaak stays visible as a failed job. A retry skips the
 * registered deelzaak and does not repair the failed steps.
 */
class DoorkomstDeelzaakIncompleteException extends RuntimeException
{
    /**
     * @param  list<string>  $failedSteps
     */
    public function __construct(
        string $message,
        public readonly string $zaakUrl,
        public readonly array $failedSteps,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param  list<string>  $failedSteps
     */
    public static function forSteps(string $zaakUrl, array $failedSteps, ?Throwable $previous = null): self
    {
        return new self(
            sprintf(
                'Doorkomst deelzaak %s was created, but these steps on it failed: %s.',
                $zaakUrl,
                implode(', ', $failedSteps),
            ),
            $zaakUrl,
            $failedSteps,
            $previous,
        );
    }
}
