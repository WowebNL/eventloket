<?php

namespace App\Policies;

use App\Enums\AdviceStatus;
use App\Enums\Role;
use App\Models\Thread;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Users\AdminUser;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityUser;

class ThreadPolicy
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
    public function view(User $user, Thread $thread): bool
    {
        if ($user instanceof AdminUser) {
            return true;
        }

        if ($user instanceof MunicipalityUser) {
            return $user->canAccessMunicipality($thread->zaak->zaaktype->municipality_id);
        }

        if ($user instanceof AdvisorUser && $thread instanceof AdviceThread) {
            return $thread->advice_status !== AdviceStatus::Concept
                && $user->canAccessAdvisory($thread->advisory_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return match ($user->role) {
            Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin, Role::Reviewer => true,
            default => false,
        };
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Thread $thread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Thread $thread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Thread $thread): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Thread $thread): bool
    {
        return false;
    }
}
