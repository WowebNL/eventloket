<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Users\AdminUser;
use App\Models\Users\CoordinatorUser;

class CoordinatorUserPolicy
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
    public function view(User $user, CoordinatorUser $coordinatorUser): bool
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
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CoordinatorUser $coordinatorUser): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($user->role === Role::MunicipalityAdmin || $user->role === Role::ReviewerMunicipalityAdmin) {
            $adminMunicipalityIds = $user->municipalities()->pluck('municipalities.id');

            return $coordinatorUser->municipalities()->whereIn('municipalities.id', $adminMunicipalityIds)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CoordinatorUser $coordinatorUser): bool
    {

        // Admin and ReviewerMunicipalityAdmin can always delete
        if (in_array($user->role, [Role::Admin])) {
            return true;
        }

        // MunicipalityAdmin or ReviewerMunicipalityAdmin can only delete reviewers in their municipalities
        if ($user->role === Role::MunicipalityAdmin || $user->role === Role::ReviewerMunicipalityAdmin) {
            $adminMunicipalityIds = $user->municipalities()->pluck('municipalities.id');

            return $coordinatorUser->municipalities()->whereIn('municipalities.id', $adminMunicipalityIds)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CoordinatorUser $coordinatorUser): bool
    {

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CoordinatorUser $coordinatorUser): bool
    {

        return false;
    }
}
