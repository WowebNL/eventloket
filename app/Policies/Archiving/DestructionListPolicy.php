<?php

namespace App\Policies\Archiving;

use App\Enums\DestructionListStatus;
use App\Enums\Role;
use App\Models\Archiving\DestructionList;
use App\Models\User;
use App\Models\Users\MunicipalityUser;

class DestructionListPolicy
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
    public function view(User $user, DestructionList $destructionList): bool
    {
        return in_array($user->role, [Role::ArchiveCoordinator, Role::ArchiveReviewer])
            && $this->canAccessMunicipality($user, $destructionList);
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
    public function update(User $user, DestructionList $destructionList): bool
    {
        return $user->role === Role::ArchiveCoordinator
            && $this->canAccessMunicipality($user, $destructionList)
            && $destructionList->status->isEditable();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DestructionList $destructionList): bool
    {
        return $user->role === Role::ArchiveCoordinator
            && $this->canAccessMunicipality($user, $destructionList)
            && $destructionList->status->isEditable();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DestructionList $destructionList): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DestructionList $destructionList): bool
    {
        return false;
    }

    /**
     * Determine whether the user can submit the list for review.
     */
    public function submitForReview(User $user, DestructionList $destructionList): bool
    {
        return $user->role === Role::ArchiveCoordinator
            && $this->canAccessMunicipality($user, $destructionList)
            && $destructionList->canTransitionTo(DestructionListStatus::ReadyToReview);
    }

    /**
     * Determine whether the user can review (approve or request changes on) the list.
     */
    public function review(User $user, DestructionList $destructionList): bool
    {
        return $user->role === Role::ArchiveReviewer
            && $this->canAccessMunicipality($user, $destructionList)
            && $destructionList->status === DestructionListStatus::ReadyToReview;
    }

    /**
     * Determine whether the user can confirm the actual destruction.
     */
    public function confirm(User $user, DestructionList $destructionList): bool
    {
        return $user->role === Role::ArchiveCoordinator
            && $this->canAccessMunicipality($user, $destructionList)
            && $destructionList->status === DestructionListStatus::Approved;
    }

    /**
     * Determine whether the user can retry a failed destruction.
     */
    public function retry(User $user, DestructionList $destructionList): bool
    {
        return $user->role === Role::ArchiveCoordinator
            && $this->canAccessMunicipality($user, $destructionList)
            && $destructionList->status === DestructionListStatus::Failed;
    }

    /**
     * Determine whether the user can regenerate the destruction report of a
     * destroyed list. The report itself stays immutable: this only rebuilds
     * a report or PDF that is missing.
     */
    public function regenerateReport(User $user, DestructionList $destructionList): bool
    {
        return $user->role === Role::ArchiveCoordinator
            && $this->canAccessMunicipality($user, $destructionList)
            && $destructionList->status === DestructionListStatus::Deleted;
    }

    private function canAccessMunicipality(User $user, DestructionList $destructionList): bool
    {
        return $user instanceof MunicipalityUser
            && $user->canAccessMunicipality($destructionList->municipality_id);
    }
}
