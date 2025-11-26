<?php

namespace App\Policies;

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Models\User;
use App\Models\Users\AdvisorUser;
use Illuminate\Support\Facades\DB;

class AdvisorUserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return match ($user->role) {
            Role::Admin, Role::Advisor, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin => true,
            default => false,
        };
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AdvisorUser $advisorUser): bool
    {
        return match ($user->role) {
            Role::Admin, Role::Advisor, Role::MunicipalityAdmin, Role::ReviewerMunicipalityAdmin => true,
            default => false,
        };
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
    public function update(User $user, AdvisorUser $advisorUser): bool
    {
        if ($user->id == $advisorUser->id) {
            return true;
        }

        if ($user->role == Role::Admin) {
            return true;
        }

        if ($user->role == Role::MunicipalityAdmin || $user->role == Role::ReviewerMunicipalityAdmin) {
            /** @phpstan-ignore-next-line */
            return $advisorUser->advisories->count() == 1 && in_array($advisorUser->advisories->first()->id, $user->municipalities->pluck('id')->toArray());
        }

        if ($user->role == Role::Advisor) {
            return DB::table('advisory_user')
                ->where('user_id', $user->id)
                ->where('role', AdvisoryRole::Admin->value)
                ->whereIn('advisory_id', function ($q) use ($advisorUser) {
                    $q->select('advisory_id')
                        ->from('advisory_user')
                        ->where('user_id', $advisorUser->id);
                })
                ->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AdvisorUser $advisorUser): bool
    {
        // Soft-deleted users cannot perform actions
        if ($user->trashed()) {
            return false;
        }

        if ($user->id == $advisorUser->id) {
            return true;
        }

        if ($user->role == Role::Admin) {
            return true;
        }

        if ($user->role == Role::MunicipalityAdmin || $user->role == Role::ReviewerMunicipalityAdmin) {
            /** @phpstan-ignore-next-line */
            return $advisorUser->advisories->count() == 1 && in_array($advisorUser->advisories->first()->id, $user->municipalities->pluck('id')->toArray());
        }

        if ($user->role == Role::Advisor) {
            $userIsAdminForAdvisory = DB::table('advisory_user')
                ->where('user_id', $user->id)
                ->where('role', AdvisoryRole::Admin->value)
                ->whereIn('advisory_id', function ($q) use ($advisorUser) {
                    $q->select('advisory_id')
                        ->from('advisory_user')
                        ->where('user_id', $advisorUser->id);
                })
                ->exists();

            // Deleting is only allowed when the advisor is in 1 or less advisories.
            if ($userIsAdminForAdvisory) {
                return $advisorUser->advisories->count() <= 1;
            }
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AdvisorUser $advisorUser): bool
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
    public function forceDelete(User $user, AdvisorUser $advisorUser): bool
    {
        // Soft-deleted users cannot perform actions
        if ($user->trashed()) {
            return false;
        }

        return false;
    }
}
