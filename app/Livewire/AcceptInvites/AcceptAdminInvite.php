<?php

namespace App\Livewire\AcceptInvites;

use App\Enums\Role;
use App\Models\AdminInvite;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;

class AcceptAdminInvite extends AbstractAcceptInvite
{
    protected function getInviteModel(): AdminInvite
    {
        return new AdminInvite;
    }

    protected function getRole(): Role
    {
        return Role::Admin;
    }

    protected function getPanelName(): string
    {
        return 'admin';
    }

    protected function getTenantId(): null
    {
        return null;
    }

    protected function attachTenantRelation(User $user): void
    {
        // No tenant relation to attach for admin invites
    }

    public function getHeading(): string|Htmlable
    {
        return __('admin/pages/auth/accept-admin-invite.heading');
    }

    public function getSubheading(): Htmlable|string|null
    {
        return __('admin/pages/auth/accept-admin-invite.subheading');
    }
}
