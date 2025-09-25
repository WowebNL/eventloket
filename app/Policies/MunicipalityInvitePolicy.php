<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\MunicipalityInvite;
use App\Models\User;

class MunicipalityInvitePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MunicipalityInvite $municipalityInvite): bool
    {
        return false;
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
    public function update(User $user, MunicipalityInvite $municipalityInvite): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MunicipalityInvite $municipalityInvite): bool
    {
        if ($user->role == Role::Admin) {
            return true;
        }

        if (in_array($user->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin])) {
            // Check if the user has access to one of the municipalities of the municipality invite
            /** @var \App\Models\Users\MunicipalityUser $user */
            return $user->municipalities->pluck('id')
                ->intersect($municipalityInvite->municipalities->pluck('id'))
                ->isNotEmpty();
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MunicipalityInvite $municipalityInvite): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MunicipalityInvite $municipalityInvite): bool
    {
        return false;
    }
}
