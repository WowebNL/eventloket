<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;
use App\Models\Users\AdminUser;

class AdminUserPolicy
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
    public function view(User $user, AdminUser $adminUser): bool
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
    public function update(User $user, AdminUser $adminUser): bool
    {
        return $user->role === Role::Admin;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AdminUser $adminUser): bool
    {
        return $user->role === Role::Admin;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AdminUser $adminUser): bool
    {
        return $user->role === Role::Admin;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AdminUser $adminUser): bool
    {
        return $user->role === Role::Admin;
    }
}
