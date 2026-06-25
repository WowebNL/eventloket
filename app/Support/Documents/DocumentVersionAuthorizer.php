<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Enums\Role;
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
 * Wanneer de maker niet te bepalen is, wordt het toevoegen conservatief
 * geblokkeerd om cross-group bewerkingen te voorkomen. Gemeentegebruikers
 * vormen hierop een uitzondering: zij mogen dan wél een nieuwe versie
 * toevoegen.
 */
class DocumentVersionAuthorizer
{
    public static function canAddVersion(User $user, Zaak $zaak, string $documentUuid): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        $creator = self::resolveCreator($documentUuid);

        if ($creator === null) {
            return $user instanceof MunicipalityUser;
        }

        return self::inSameGroup($user, $creator);
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
