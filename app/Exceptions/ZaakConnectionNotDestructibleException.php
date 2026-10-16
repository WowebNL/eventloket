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
 * Also thrown when the connection cannot be established at all. A guard in front
 * of irreversible destruction has to close when it is unsure, not open: without a
 * local zaak row the url host is all there is, and an unknown host or one shared
 * by several connections decides nothing.
 *
 * Destruction is irreversible and aimed at somebody else's production system, so
 * this is a hard stop rather than a skip.
 */
class ZaakConnectionNotDestructibleException extends RuntimeException
{
    /**
     * @param  ?string  $connectionName  The connection the zaak was placed on, or
     *                                   null when that could not be established.
     */
    public function __construct(
        public readonly string $zaakUrl,
        public readonly ?string $connectionName,
    ) {
        $url = $zaakUrl === '' ? 'geen url' : $zaakUrl;

        parent::__construct($connectionName === null
            ? sprintf(
                'Van deze zaak (%s) kon niet worden vastgesteld op welke ZGW-koppeling hij staat, '
                .'dus wordt er niets vernietigd. De host is onbekend of hoort bij meerdere koppelingen.',
                $url,
            )
            : sprintf(
                'Deze zaak (%s) staat op ZGW-koppeling "%s" en niet op de eigen OpenZaak. '
                .'Vernietiging van zaakgegevens op een eigen koppeling van een gemeente loopt via dat zaaksysteem zelf.',
                $url,
                $connectionName,
            ));
    }
}
