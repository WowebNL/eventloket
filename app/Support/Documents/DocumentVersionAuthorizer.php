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
 * Bepaalt of een gebruiker een nieuwe versie van een document mag toevoegen.
 *
 * Een nieuwe versie mag alleen worden toegevoegd wanneer de eerste versie van
 * het document is aangemaakt door iemand binnen dezelfde groep als de huidige
 * gebruiker (organisatie, gemeente of adviesdienst). De maker van de eerste
 * versie wordt afgeleid uit de activity log ('created'-event met document_uuid).
 *
 * Of de gebruiker überhaupt documenten aan de zaak mag toevoegen wordt
 * elders bepaald (ZaakPolicy::uploadDocument); deze checker voegt daar de
 * per-document groepsregel aan toe.
 *
 * Twee documenten hebben geen groeps-eigenaar en mogen daarom alleen door de
 * platform-admin worden vervangen: de systeem-aanvraagformulier-PDF, en elk
 * document waarvan de maker niet uit de activity log te bepalen is.
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
