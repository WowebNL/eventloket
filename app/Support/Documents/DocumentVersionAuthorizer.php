<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\Role;
use App\Jobs\Submit\UploadSubmissionPdfToZGW;
use App\Models\User;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityUser;
use App\Models\Users\OrganiserUser;
use App\Models\Zaak;
use Spatie\Activitylog\Models\Activity;

/**
 * Decides whether a user may add a new version of a document.
 *
 * The document has to belong to the zaak the version is added through. A new
 * version may then only be added when the first version of the document was
 * created by someone in the same group as the current user (organisation,
 * municipality or advisory). The creator of the first version is derived from
 * the activity log (the 'created' event with the document_uuid).
 *
 * Whether the user may add documents to the zaak at all is decided elsewhere
 * (ZaakPolicy::uploadDocument); this checker adds the per-document group rule
 * on top of that.
 *
 * Two kinds of document have no group owner and may therefore only be replaced
 * by the platform admin: the system aanvraagformulier PDF, and every document
 * whose creator cannot be determined from the activity log.
 */
class DocumentVersionAuthorizer
{
    /**
     * The fixed filename of the system-generated aanvraagformulier PDF, set by
     * {@see UploadSubmissionPdfToZGW}.
     */
    private const AANVRAAGFORMULIER_FILENAME = 'aanvraagformulier.pdf';

    public static function canAddVersion(User $user, Zaak $zaak, string $documentUuid): bool
    {
        // A version is only ever added through a zaak, so the document has to
        // belong to that zaak, for every role. The display set is used because
        // this check also runs per row of the documents table, which keeps
        // rendering when the documents API refuses one of the documents.
        if (! $zaak->documentenForDisplay()->documenten->contains('uuid', $documentUuid)) {
            return false;
        }

        // The platform admin may always add a version, including for the system
        // aanvraagformulier and documents without a resolvable owner.
        if ($user->role === Role::Admin) {
            return true;
        }

        // The aanvraagformulier PDF is a system document without a user-owner;
        // only the platform admin (handled above) may replace it.
        if (self::isSystemAanvraagformulier($documentUuid)) {
            return false;
        }

        $creator = self::resolveCreator($documentUuid);

        // Without a resolvable creator there is no owner, so only the platform
        // admin (handled above) may add a version.
        if ($creator === null) {
            return false;
        }

        return self::inSameGroup($user, $creator);
    }

    /**
     * Whether the document is the system-generated aanvraagformulier PDF.
     * Identified via the immutable 'created' activity-log entry (its filename is
     * the fixed aanvraagformulier filename) rather than the current, mutable
     * bestandsnaam or titel, which change once a new version is uploaded.
     */
    private static function isSystemAanvraagformulier(string $documentUuid): bool
    {
        return Activity::query()
            ->where('log_name', 'document')
            ->where('event', 'created')
            ->where('properties->document_uuid', $documentUuid)
            ->where('properties->filename', self::AANVRAAGFORMULIER_FILENAME)
            ->exists();
    }

    private static function resolveCreator(string $documentUuid): ?User
    {
        $activity = Activity::query()
            ->where('log_name', 'document')
            ->where('event', 'created')
            ->where('properties->document_uuid', $documentUuid)
            ->whereNotNull('causer_id')
            ->oldest()
            ->first();

        $causer = $activity?->causer;

        return $causer instanceof User ? $causer : null;
    }

    private static function inSameGroup(User $user, User $creator): bool
    {
        return match (true) {
            $user instanceof OrganiserUser => $creator instanceof OrganiserUser,
            $user instanceof MunicipalityUser => $creator instanceof MunicipalityUser,
            $user instanceof AdvisorUser => $creator instanceof AdvisorUser
                && $user->advisories->pluck('id')
                    ->intersect($creator->advisories->pluck('id'))
                    ->isNotEmpty(),
            default => false,
        };
    }
}
