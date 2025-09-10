<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Threads\AdviceThread;
use App\Models\User;

class AdviceThreadPolicy
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
    public function view(User $user, AdviceThread $adviceThread): bool
    {
        return match ($user->role) {
            Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer => true,
            Role::Advisor => true,
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
            Role::Advisor => true,
            default => false,
        };
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AdviceThread $adviceThread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AdviceThread $adviceThread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AdviceThread $adviceThread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AdviceThread $adviceThread): bool
    {
        return false;
    }
}
