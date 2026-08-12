<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the gemeente in the FormState is not among the municipalities the
 * location check found for the submitted location. Creating the zaak anyway
 * would put it in the wrong municipality, and with it on the wrong ZGW instance.
 *
 * Its own type so the submit handler can tell the organiser what to do about it:
 * retrying is pointless, the location step has to be revisited.
 */
class GemeenteLocatieMismatchException extends RuntimeException
{
    /**
     * @param  string  $brk  The brk_identification held in the FormState.
     * @param  list<string>  $foundBrkIdentifications  The municipalities found for the submitted location.
     */
    public function __construct(
        public readonly string $brk,
        public readonly array $foundBrkIdentifications,
    ) {
        parent::__construct(sprintf(
            'De gemeente uit de FormState (%s) hoort niet bij de gevonden gemeenten voor de opgegeven locatie (%s).',
            $brk,
            implode(', ', $foundBrkIdentifications),
        ));
    }
}
