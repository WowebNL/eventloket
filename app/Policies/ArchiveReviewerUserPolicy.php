<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Users\AdminUser;
use App\Models\Users\ArchiveReviewerUser;

/**
 * User records are hydrated into the class of their role, so every role needs
 * its own policy: a check against a hydrated ArchiveReviewerUser does not
 * fall back to MunicipalityUserPolicy.
 */
class ArchiveReviewerUserPolicy
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
    public function view(User $user, ArchiveReviewerUser $archiveReviewerUser): bool
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
    public function update(User $user, ArchiveReviewerUser $archiveReviewerUser): bool
    {
        return $this->managesMunicipalityOf($user, $archiveReviewerUser);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ArchiveReviewerUser $archiveReviewerUser): bool
    {
        return $this->managesMunicipalityOf($user, $archiveReviewerUser);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ArchiveReviewerUser $archiveReviewerUser): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ArchiveReviewerUser $archiveReviewerUser): bool
    {
        return false;
    }

    /**
     * A municipality admin only manages archive users of their own
     * municipalities; an application admin manages all of them.
     */
    private function managesMunicipalityOf(User $user, ArchiveReviewerUser $archiveReviewerUser): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (! in_array($user->role, [Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin])) {
            return false;
        }

        return $archiveReviewerUser->municipalities()
            ->whereIn('municipalities.id', $user->municipalities()->pluck('municipalities.id'))
            ->exists();
    }
}
