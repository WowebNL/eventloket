<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Threads\OrganiserThread;
use App\Models\User;

class OrganiserThreadPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return match ($user->role) {
            Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer => true,
            Role::Organiser => true,
            default => false,
        };
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, OrganiserThread $organiserThread): bool
    {
        return match ($user->role) {
            Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer => true,
            Role::Organiser => true,
            default => false,
        };
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return match ($user->role) {
            Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer => true,
            Role::Organiser => true,
            default => false,
        };
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, OrganiserThread $organiserThread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, OrganiserThread $organiserThread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, OrganiserThread $organiserThread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, OrganiserThread $organiserThread): bool
    {
        return false;
    }
}
