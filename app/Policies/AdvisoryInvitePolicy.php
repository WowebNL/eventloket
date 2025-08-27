<?php

namespace App\Policies;

use App\Models\AdvisoryInvite;
use App\Models\User;
use App\Models\Users\AdminUser;

class AdvisoryInvitePolicy
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
    public function view(User $user, AdvisoryInvite $advisoryInvite): bool
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
    public function update(User $user, AdvisoryInvite $advisoryInvite): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AdvisoryInvite $advisoryInvite): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AdvisoryInvite $advisoryInvite): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AdvisoryInvite $advisoryInvite): bool
    {
        return false;
    }
}
