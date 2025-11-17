<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\DefaultAdviceQuestion;
use App\Models\User;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;

class DefaultAdviceQuestionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DefaultAdviceQuestion $defaultAdviceQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $user->canAccessMunicipality($defaultAdviceQuestion->municipality_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DefaultAdviceQuestion $defaultAdviceQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $defaultAdviceQuestion->municipality_id && $user->canAccessMunicipality($defaultAdviceQuestion->municipality_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DefaultAdviceQuestion $defaultAdviceQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $defaultAdviceQuestion->municipality_id && $user->canAccessMunicipality($defaultAdviceQuestion->municipality_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DefaultAdviceQuestion $defaultAdviceQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $defaultAdviceQuestion->municipality_id && $user->canAccessMunicipality($defaultAdviceQuestion->municipality_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DefaultAdviceQuestion $defaultAdviceQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $defaultAdviceQuestion->municipality_id && $user->canAccessMunicipality($defaultAdviceQuestion->municipality_id)) {
            return true;
        }

        return false;
    }
}
