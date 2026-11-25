<?php

declare(strict_types=1);

namespace App\Services\Zgw;

use App\EventForm\Schema\FileUploadFields;
use App\Models\Zaak;
use App\ValueObjects\ZGW\Informatieobject;

/**
 * Decides whether a document is a submission document (version 1): the
 * generated application PDF (recognisable by its filename) or an attachment the
 * organiser uploaded through the form (recognisable via the zaak's
 * form_state_snapshot).
 *
 * isSubmissionDocument() suppresses notifications for the initial submission
 * documents. The organiser's document boundary (documents tab switched off)
 * does not use it: that recognises the submission documents by uuid, see
 * Zaak::isOrganiserSubmissionDocument(), with matchesSubmittedFileName() only
 * as a fallback for zaken submitted before those uploads were logged.
 */
final class SubmissionDocumentDetector
{
    /**
     * The file name of the generated application PDF.
     */
    private const AANVRAAGFORMULIER_FILENAME = 'aanvraagformulier.pdf';

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

    /**
     * Name-based recognition for the organiser's document boundary, used only
     * for a zaak whose submission uploads were never logged with their uuid
     * (see {@see Zaak::isOrganiserSubmissionDocument()}).
     *
     * Stricter than {@see isSubmissionDocument()}: a document without a file
     * name never matches, and only the values of the form's file upload fields
     * (the stored path and the original file name kept next to it) count, not
     * every value the organiser typed into the form.
     */
    public static function matchesSubmittedFileName(Informatieobject $informatieobject, Zaak $zaak): bool
    {
        $bestandsnaam = $informatieobject->bestandsnaam;

        if ($bestandsnaam === '') {
            return false;
        }

        if ($bestandsnaam === self::AANVRAAGFORMULIER_FILENAME) {
            return true;
        }

        $values = $zaak->form_state_snapshot['values'] ?? [];
        if (! is_array($values)) {
            return false;
        }

        foreach (FileUploadFields::keys() as $key) {
            foreach ([$values[$key] ?? null, $values["{$key}_namen"] ?? null] as $value) {
                $candidates = is_array($value) ? $value : [$value];
                foreach ($candidates as $candidate) {
                    if (is_string($candidate) && $candidate !== '' && basename($candidate) === $bestandsnaam) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
