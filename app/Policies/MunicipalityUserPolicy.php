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
     *
     * Managing municipality user accounts is a settings task, so it is limited
     * to the same roles that may already create and update them. The resources
     * on this model live in a settings cluster or in the navigation only, and a
     * cluster or navigation entry does not gate the route of a resource page.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
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

        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MunicipalityUser $municipalityUser): bool
    {

        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MunicipalityUser $municipalityUser): bool
    {

        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }
}
