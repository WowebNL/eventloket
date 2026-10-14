<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the zaaktype url of a submit belongs to another ZGW instance than
 * the connection the zaak is about to be created on.
 *
 * This is the backstop behind the connection fallback, not the first line of
 * defence: when a municipality's own connection is unusable the resolver routes
 * the zaak to the main connection and the zaaktype is resolved on that same
 * connection, so a submit still succeeds. Reaching this exception therefore
 * means that consistency could not be restored, and creating the zaak anyway
 * would register it against a zaaktype the receiving instance does not know.
 *
 * Its own type, and a message that names both sides, because the alternative is
 * a bare validation error from the receiving instance that says nothing about
 * which two configurations disagree.
 */
class ZaaktypeConnectionMismatchException extends RuntimeException
{
    public function __construct(
        public readonly string $zaaktypeUrl,
        public readonly string $connectionName,
        public readonly int|string|null $zaaktypeId = null,
        public readonly int|string|null $municipalityId = null,
    ) {
        parent::__construct(sprintf(
            'Het zaaktype van deze aanvraag (%s) hoort niet bij de ZGW-koppeling waarop de zaak wordt aangemaakt ("%s"). '
            .'De zaak is niet aangemaakt; controleer de koppeling van gemeente %s en het zaaktype (%s).',
            $zaaktypeUrl === '' ? 'geen url' : $zaaktypeUrl,
            $connectionName,
            $municipalityId === null ? 'onbekend' : (string) $municipalityId,
            $zaaktypeId === null ? 'onbekend' : (string) $zaaktypeId,
        ));
    }
}
