<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Users\AdminUser;
use App\Models\Users\ReviewerUser;

class ReviewerUserPolicy
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
    public function view(User $user, ReviewerUser $reviewerUser): bool
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
    public function update(User $user, ReviewerUser $reviewerUser): bool
    {
        return in_array($user->role, [Role::Admin, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin]);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReviewerUser $reviewerUser): bool
    {
        // Soft-deleted users cannot perform actions
        if ($user->trashed()) {
            return false;
        }

        // Admin and ReviewerMunicipalityAdmin can always delete
        if (in_array($user->role, [Role::Admin])) {
            return true;
        }

        // MunicipalityAdmin or ReviewerMunicipalityAdmin can only delete reviewers in their municipalities
        if ($user->role === Role::MunicipalityAdmin || $user->role === Role::ReviewerMunicipalityAdmin) {
            $adminMunicipalityIds = $user->municipalities()->pluck('municipalities.id');

            return $reviewerUser->municipalities()->whereIn('municipalities.id', $adminMunicipalityIds)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReviewerUser $reviewerUser): bool
    {
        // Soft-deleted users cannot perform actions
        if ($user->trashed()) {
            return false;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReviewerUser $reviewerUser): bool
    {
        // Soft-deleted users cannot perform actions
        if ($user->trashed()) {
            return false;
        }

        return false;
    }
}
