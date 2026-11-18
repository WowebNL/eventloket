<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Zaak;

/**
 * What a user may do with the documents of one zaak, as decided by
 * {@see Zaak::documentAccessFor()}. It describes the documents tab and
 * the document routes; which documents are listed is still decided by the
 * vertrouwelijkheid filter per role.
 */
enum ZaakDocumentAccess
{
    /**
     * The documents tab is shown for the zaak's connection. What the user may
     * then do is decided by the policies, exactly as before this setting.
     */
    case Full;

    /**
     * The organiser on a connection with the documents tab switched off: only
     * the files delivered with the application, without adding files.
     */
    case SubmissionOnly;

    /**
     * An advisory that may view any zaak, on a connection with the documents
     * tab switched off and read-only documents for advisories switched on:
     * view and download, no uploads and no new versions.
     */
    case ReadOnly;

    /**
     * No documents tab and no access to the documents of the zaak through it.
     */
    case None;

    /**
     * Whether the documents tab is shown with this access.
     */
    public function showsDocumentsTab(): bool
    {
        return $this === self::Full || $this === self::ReadOnly;
    }
}
