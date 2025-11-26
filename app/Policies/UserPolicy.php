<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Define which roles can be managed by which user roles
     */
    private const MANAGEABLE_ROLES = [
        Role::Admin->value => [
            Role::MunicipalityAdmin,
            Role::ReviewerMunicipalityAdmin,
            Role::Advisor,
            Role::Reviewer,
        ],
        Role::MunicipalityAdmin->value => [
            Role::Reviewer,
        ],
    ];

    /**
     * Define which roles can be restored by which user roles
     */
    private const RESTORABLE_ROLES = [
        Role::Admin->value => [
            Role::MunicipalityAdmin,
            Role::ReviewerMunicipalityAdmin,
            Role::Advisor,
            Role::Reviewer,
        ],
        Role::MunicipalityAdmin->value => [
            Role::Reviewer,
        ],
    ];

    /**
     * Define which roles can be force deleted by which user roles
     */
    private const FORCE_DELETABLE_ROLES = [
        Role::Admin->value => [
            Role::MunicipalityAdmin,
            Role::ReviewerMunicipalityAdmin,
            Role::Advisor,
            Role::Reviewer,
        ],
    ];

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
    public function view(User $user, User $model): bool
    {
        return false;
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
    public function update(User $user, User $model): bool
    {
        return $user->role === Role::Admin && $model->role === Role::MunicipalityAdmin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return $this->canManageUser($user, $model, self::MANAGEABLE_ROLES);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return $this->canManageUser($user, $model, self::RESTORABLE_ROLES);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return $this->canManageUser($user, $model, self::FORCE_DELETABLE_ROLES);
    }

    /**
     * Check if a user can manage another user based on role permissions
     *
     * @param  array<string, Role[]>  $rolePermissions
     */
    private function canManageUser(User $user, User $model, array $rolePermissions): bool
    {
        // Soft-deleted users cannot perform actions
        if ($user->trashed()) {
            return false;
        }

        // Check if the user's role has permission to manage the model's role
        /** @var Role $userRole */
        $userRole = $user->role;
        $allowedRoles = $rolePermissions[$userRole->value] ?? [];

        if (! in_array($model->role, $allowedRoles)) {
            return false;
        }

        // Special case: Municipality admin can only manage reviewer users in their municipality
        if ($user->role === Role::MunicipalityAdmin && $model->role === Role::Reviewer) {
            return $this->isReviewerInAdminMunicipality($user, $model);
        }

        return true;
    }

    /**
     * Check if a reviewer user belongs to any of the admin user's municipalities
     */
    private function isReviewerInAdminMunicipality(User $adminUser, User $reviewerUser): bool
    {
        $adminMunicipalityIds = $adminUser->municipalities()->pluck('municipalities.id');

        return $reviewerUser->municipalities()->whereIn('municipalities.id', $adminMunicipalityIds)->exists();
    }
}
