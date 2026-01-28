<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Users\AdminUser;
use App\Models\Users\MunicipalityUser;

class MunicipalityUserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MunicipalityUser $municipalityUser): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MunicipalityUser $municipalityUser): bool
    {
        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, MunicipalityUser $municipalityUser): bool
    {
        // Soft-deleted users cannot perform actions
        if ($user->trashed()) {
            return false;
        }

        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MunicipalityUser $municipalityUser): bool
    {
        // Soft-deleted users cannot perform actions
        if ($user->trashed()) {
            return false;
        }

        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MunicipalityUser $municipalityUser): bool
    {
        // Soft-deleted users cannot perform actions
        if ($user->trashed()) {
            return false;
        }

        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }
}
