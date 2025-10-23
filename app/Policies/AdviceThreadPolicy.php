<?php

namespace App\Policies;

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Models\Thread;
use App\Models\Threads\AdviceThread;
use App\Models\User;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityUser;

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

    public function postMessage(User $user, Thread $thread)
    {
        if ($user->role === Role::Advisor) {
            return $thread->assignedUsers->contains($user->id);
        }

        if ($user instanceof MunicipalityUser) {
            return $user->canAccessMunicipality($thread->zaak->zaaktype->municipality_id);
        }

        return false;
    }

    /**
     * Determine whether the user can assign advisors to this thread.
     */
    public function assignAdvisor(User $user, Thread $thread, AdvisorUser $advisorUser): bool
    {
        if ($user instanceof AdvisorUser) {

            // Admins can assign any advisor to any thread
            if ($user->canAccessAdvisory($thread->advisory_id, as: AdvisoryRole::Admin)) {
                return true;
            }

            // Advisors can only assign themselves to threads
            if ($user->canAccessAdvisory($thread->advisory_id) && $user->id === $advisorUser->id) {
                return true;
            }

        }

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
