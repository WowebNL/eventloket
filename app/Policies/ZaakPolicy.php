<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Zaak;

class ZaakPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return match ($user->role) {
            Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer, Role::Organiser => true,
            Role::Advisor => true,
            Role::Admin => true,
        };
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Zaak $zaak): bool
    {
        if ($user instanceof \App\Models\Users\OrganiserUser) {
            return $user->canAccessOrganisation($zaak->organisation_id);
        }

        return match ($user->role) {
            Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer => true,
            Role::Advisor => true,
            Role::Admin => true,
            default => false,
        };
    }

    public function viewActivity(User $user, Zaak $zaak)
    {
        return match ($user->role) {
            /** @phpstan-ignore-next-line */
            Role::Reviewer, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin => $user->canAccessMunicipality($zaak->zaaktype->municipality_id),
            Role::Admin => true,
            default => false,
        };
    }

    public function uploadDocument(User $user, Zaak $zaak)
    {
        if ($user instanceof \App\Models\Users\AdvisorUser) {
            $advisoryIds = $zaak->adviceThreads->pluck('advisory_id');
            $userAdvisoryIds = $user->advisories->pluck('id');

            return $advisoryIds->intersect($userAdvisoryIds)->isNotEmpty();
        }

        return match ($user->role) {
            /** @phpstan-ignore-next-line */
            Role::Reviewer, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin => $user->canAccessMunicipality($zaak->zaaktype->municipality_id),
            Role::Admin => true,
            default => false,
        };
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Zaak $zaak): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Zaak $zaak): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Zaak $zaak): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Zaak $zaak): bool
    {
        return false;
    }
}
