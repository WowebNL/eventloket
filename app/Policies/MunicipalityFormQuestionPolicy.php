<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\MunicipalityFormQuestion;
use App\Models\User;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;

class MunicipalityFormQuestionPolicy
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
    public function view(User $user, MunicipalityFormQuestion $municipalityFormQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) {
            return $user->canAccessMunicipality($municipalityFormQuestion->municipality_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     *
     * Unlike report questions these are not seeded, so a municipality has to be
     * able to add its own.
     */
    public function create(User $user): bool
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
     * Determine whether the user can update the model.
     */
    public function update(User $user, MunicipalityFormQuestion $municipalityFormQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) {
            return $user->canAccessMunicipality($municipalityFormQuestion->municipality_id);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Deleting is safe because the question list is frozen into the zaak's
     * form state snapshot on submit, so PDFs of submitted aanvragen keep
     * rendering their questions.
     */
    public function delete(User $user, MunicipalityFormQuestion $municipalityFormQuestion): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if ($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) {
            return $user->canAccessMunicipality($municipalityFormQuestion->municipality_id);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, MunicipalityFormQuestion $municipalityFormQuestion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, MunicipalityFormQuestion $municipalityFormQuestion): bool
    {
        return false;
    }
}
