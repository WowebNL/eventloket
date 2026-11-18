<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a form submission object url does not point at the configured
 * Objects API, so the delete is refused before any request is sent.
 *
 * A refusal is an outcome of its own and not a success: the caller records it,
 * so the destruction report does not claim an object was removed when it was
 * never touched.
 */
class DataObjectHostNotAllowedException extends RuntimeException
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $target,
        public readonly ?string $allowed,
    ) {
        parent::__construct(sprintf(
            'Refused to delete a data object on [%s]: it is not the configured Objects API [%s].',
            $target ?? 'unknown',
            $allowed ?? 'not configured',
        ));
    }
}
