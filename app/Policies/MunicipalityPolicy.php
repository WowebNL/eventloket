<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;
use App\Models\Users\ReviewerUser;

class MunicipalityPolicy
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
    public function view(User $user, Municipality $municipality): bool
    {
        if ($user instanceof ReviewerUser || $user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) {
            return $user->canAccessMunicipality($municipality->id);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Municipality $municipality): bool
    {
        return $user->role === Role::Admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Municipality $municipality): bool
    {
        return $user->role === Role::Admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Municipality $municipality): bool
    {
        return $user->role === Role::Admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Municipality $municipality): bool
    {
        return $user->role === Role::Admin;
    }
}
