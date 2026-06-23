<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;

class LocationPolicy
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
    public function view(User $user, Location $location): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $user->canAccessMunicipality($location->municipality_id)) {
            return true;
        }

        return false;
    }

    /**
     * Role-level pre-filter: only municipality admins may create locations.
     * Cross-municipality creation is prevented by Filament's tenant observeTenancyModelCreation()
     * hook, which calls municipality()->associate($tenant) on every creating event within the
     * municipality panel — overriding any municipality_id supplied in form data.
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
    public function update(User $user, Location $location): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $user->canAccessMunicipality($location->municipality_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Location $location): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $user->canAccessMunicipality($location->municipality_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Location $location): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $user->canAccessMunicipality($location->municipality_id)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Location $location): bool
    {
        if ($user->role === Role::Admin) {
            return true;
        }

        if (($user instanceof MunicipalityAdminUser || $user instanceof ReviewerMunicipalityAdminUser) && $user->canAccessMunicipality($location->municipality_id)) {
            return true;
        }

        return false;
    }
}
