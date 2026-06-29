<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\Models\Zaak;
use App\ValueObjects\ZGW\Informatieobject;

/**
 * Decides whether a document is a submission document (version 1): the
 * generated application PDF (recognisable by its filename) or an attachment the
 * organiser uploaded through the form (recognisable via the zaak's
 * form_state_snapshot).
 *
 * Used both to suppress notifications for the initial submission documents and
 * to keep those files visible to the organiser when the bestanden tab is
 * otherwise disabled for a connection.
 */
final class SubmissionDocumentDetector
{
    public static function isSubmissionDocument(Informatieobject $informatieobject, Zaak $zaak): bool
    {
        if ($informatieobject->bestandsnaam === 'aanvraagformulier.pdf') {
            return true;
        }

        $values = $zaak->form_state_snapshot['values'] ?? [];
        foreach ($values as $value) {
            if (is_string($value) && $value !== '' && basename($value) === $informatieobject->bestandsnaam) {
                return true;
            }
            if (is_array($value)) {
                foreach ($value as $entry) {
                    if (is_string($entry) && $entry !== '' && basename($entry) === $informatieobject->bestandsnaam) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
