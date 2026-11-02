<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Users\AdminUser;
use App\Models\Users\ArchiveCoordinatorUser;

/**
 * User records are hydrated into the class of their role, so every role needs
 * its own policy: a check against a hydrated ArchiveCoordinatorUser does not
 * fall back to MunicipalityUserPolicy.
 */
class ArchiveCoordinatorUserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ArchiveCoordinatorUser $archiveCoordinatorUser): bool
    {
        return $user instanceof AdminUser;
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
    public function update(User $user, ArchiveCoordinatorUser $archiveCoordinatorUser): bool
    {
        return $this->managesMunicipalityOf($user, $archiveCoordinatorUser);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ArchiveCoordinatorUser $archiveCoordinatorUser): bool
    {
        return $this->managesMunicipalityOf($user, $archiveCoordinatorUser);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ArchiveCoordinatorUser $archiveCoordinatorUser): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ArchiveCoordinatorUser $archiveCoordinatorUser): bool
    {
        return false;
    }

    /**
     * A municipality admin only manages archive users of their own
     * municipalities; an application admin manages all of them.
     */
    private function managesMunicipalityOf(User $user, ArchiveCoordinatorUser $archiveCoordinatorUser): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (! in_array($user->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin])) {
            return false;
        }

        return $archiveCoordinatorUser->municipalities()
            ->whereIn('municipalities.id', $user->municipalities()->pluck('municipalities.id'))
            ->exists();
    }
}
