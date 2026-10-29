<?php

namespace App\Policies\Archiving;

use App\Enums\Role;
use App\Models\Archiving\DestructionListItem;
use App\Models\User;
use App\Models\Users\MunicipalityUser;

class DestructionListItemPolicy
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
    public function view(User $user, DestructionListItem $destructionListItem): bool
    {
        return in_array($user->role, [Role::ArchiveCoordinator, Role::ArchiveReviewer])
            && $this->canAccessMunicipality($user, $destructionListItem);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === Role::ArchiveCoordinator;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DestructionListItem $destructionListItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DestructionListItem $destructionListItem): bool
    {
        return $user->role === Role::ArchiveCoordinator
            && $this->canAccessMunicipality($user, $destructionListItem)
            && $destructionListItem->destructionList->status->isEditable();
    }

    private function canAccessMunicipality(User $user, DestructionListItem $destructionListItem): bool
    {
        return $user instanceof MunicipalityUser
            && $user->canAccessMunicipality($destructionListItem->destructionList->municipality_id);
    }
}
