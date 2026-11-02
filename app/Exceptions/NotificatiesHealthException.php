<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * A fault found by the scheduled Open Notificaties health check.
 *
 * This is never thrown to abort anything; it is reported so the fault leaves the
 * application through the exception handler and reaches the error tracker like
 * any other defect. That matters more than it sounds: the failure modes this
 * covers all produce no exception of their own, so without something raising one
 * deliberately, a notification pipeline that has stopped working is
 * indistinguishable from one that has nothing to do.
 *
 * The message carries the connection name and the fault, never request data.
 */
class NotificatiesHealthException extends RuntimeException {}
