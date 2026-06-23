<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\User;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityUser;
use App\Models\Users\OrganiserUser;

class MessagePolicy
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
    public function view(User $user, Message $message): bool
    {
        return false;
    }

    /**
     * Role-level pre-filter: only thread-participant roles may create messages.
     * Thread and zaak ownership is enforced separately via postMessage() on the thread policy.
     */
    public function create(User $user): bool
    {
        return $user instanceof MunicipalityUser
            || $user instanceof AdvisorUser
            || $user instanceof OrganiserUser;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Message $message): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Message $message): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Message $message): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Message $message): bool
    {
        return false;
    }
}
