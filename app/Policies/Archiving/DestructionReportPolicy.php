<?php

namespace App\Policies\Archiving;

use App\Enums\Role;
use App\Models\Archiving\DestructionReport;
use App\Models\User;
use App\Models\Users\MunicipalityUser;

/**
 * Destruction reports are immutable legal records: read only, no create,
 * update or delete through any interactive path.
 */
class DestructionReportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::ArchiveCoordinator, Role::ArchiveReviewer]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DestructionReport $destructionReport): bool
    {
        return in_array($user->role, [Role::ArchiveCoordinator, Role::ArchiveReviewer])
            && $user instanceof MunicipalityUser
            && $user->canAccessMunicipality($destructionReport->municipality_id);
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
    public function update(User $user, DestructionReport $destructionReport): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DestructionReport $destructionReport): bool
    {
        return false;
    }
}
