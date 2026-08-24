<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when queued work cannot finish because the location service could not
 * be reached. Callers that render a page degrade to "no address" instead: an
 * exception there takes the screen down and there is nothing to retry with.
 * Queued callers do have something to retry with, and for them degrading is
 * the worse option, because the job would report success while quietly leaving
 * data out. Failing keeps the retries and, once those run out, leaves a job in
 * the failed queue instead of a gap nobody notices.
 */
class LocatieserverUnavailableException extends RuntimeException {}
