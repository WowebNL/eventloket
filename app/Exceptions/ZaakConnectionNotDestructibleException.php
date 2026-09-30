<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Archiving\EligibleZaakFinder;
use RuntimeException;

/**
 * Thrown when the archive module is asked to destroy a zaak that does not live
 * on the application's own OpenZaak (the "main" ZGW connection).
 *
 * Eventloket is not the zorgdrager for a municipality that runs its own ZGW
 * instance: that instance is its archive of record, with its own selectielijst
 * and its own destruction procedure, and the client credentials it issues us are
 * not authorised to delete there. Such a zaak is filtered out long before this
 * point ({@see EligibleZaakFinder}), so reaching this
 * exception means a destruction list item carries a url that no longer resolves
 * to main — a zaaktype recoupled to an own instance, or a hand-made item.
 *
 * Destruction is irreversible and aimed at somebody else's production system, so
 * this is a hard stop rather than a skip.
 */
class ZaakConnectionNotDestructibleException extends RuntimeException
{
    public function __construct(
        public readonly string $zaakUrl,
        public readonly string $connectionName,
    ) {
        parent::__construct(sprintf(
            'Deze zaak (%s) staat op ZGW-koppeling "%s" en niet op de eigen OpenZaak. '
            .'Vernietiging van zaakgegevens op een eigen koppeling van een gemeente loopt via dat zaaksysteem zelf.',
            $zaakUrl === '' ? 'geen url' : $zaakUrl,
            $connectionName,
        ));
    }
}
