<?php

namespace App\Policies;

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Users\OrganiserUser;

class OrganisationPolicy
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
    public function view(User $user, Organisation $organisation): bool
    {
        if ($user instanceof OrganiserUser) {
            return $user->canAccessOrganisation($organisation->id);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Organisation $organisation): bool
    {
        if ($organisation->type == OrganisationType::Personal) {
            return false;
        }

        if ($user instanceof OrganiserUser) {
            return $user->canAccessOrganisation($organisation->id, OrganisationRole::Admin);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Organisation $organisation): bool
    {
        if ($organisation->type == OrganisationType::Personal) {
            return false;
        }

        if ($user instanceof OrganiserUser) {
            return $user->canAccessOrganisation($organisation->id, OrganisationRole::Admin);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Organisation $organisation): bool
    {
        if ($organisation->type == OrganisationType::Personal) {
            return false;
        }

        if ($user instanceof OrganiserUser) {
            return $user->canAccessOrganisation($organisation->id, OrganisationRole::Admin);
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Organisation $organisation): bool
    {
        if ($organisation->type == OrganisationType::Personal) {
            return false;
        }

        if ($user instanceof OrganiserUser) {
            return $user->canAccessOrganisation($organisation->id, OrganisationRole::Admin);
        }

        return false;
    }
}
