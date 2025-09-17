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
            Role::MunicipalityAdmin, Role::Reviewer, Role::Organiser => true,
            Role::Advisor => true,
            default => false,
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
            Role::MunicipalityAdmin, Role::Reviewer => true,
            Role::Advisor => true,
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
