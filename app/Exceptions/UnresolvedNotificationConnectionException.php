<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an incoming notification cannot be attributed to any of the ZGW
 * connections configured for its host: it carries nothing to tell them apart and
 * none of them was able to read the resource.
 *
 * The job is meant to fail on this: a notification we accepted but cannot read
 * needs a human, and failing keeps it replayable once the cause is fixed.
 */
class UnresolvedNotificationConnectionException extends RuntimeException
{
    /**
     * @param  array<string, int|null>  $attempts  connection name => HTTP status of the
     *                                             read attempt, or null when the attempt
     *                                             never reached the instance
     */
    public function __construct(
        string $message,
        public readonly array $attempts = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Whether every connection that could own this resource reports it as gone.
     *
     * That is a different situation from "nobody may read it": the resource has
     * been destroyed, which some channels handle by matching local rows instead.
     */
    public function allCandidatesReportGone(): bool
    {
        if ($this->attempts === []) {
            return false;
        }

        foreach ($this->attempts as $status) {
            if (! in_array($status, [404, 410], true)) {
                return false;
            }
        }

        return true;
    }
}
