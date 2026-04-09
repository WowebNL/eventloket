<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\ReportQuestion;
use App\Models\User;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;

class ReportQuestionPolicy
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
    public function view(User $user, ReportQuestion $reportQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) {
            return $user->canAccessMunicipality($reportQuestion->municipality_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // ReportQuestions can't be created, only updated (they are seeded)
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReportQuestion $reportQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) {
            return $user->canAccessMunicipality($reportQuestion->municipality_id);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReportQuestion $reportQuestion): bool
    {
        // ReportQuestions can't be deleted, only deactivated
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ReportQuestion $reportQuestion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ReportQuestion $reportQuestion): bool
    {
        return false;
    }
}
