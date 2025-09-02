<?php

namespace App\Livewire\AcceptInvites;

use App\Enums\Role;
use App\Models\AdvisoryInvite;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;

class AcceptAdvisoryInvite extends AbstractAcceptInvite
{
    protected function getInviteModel(): AdvisoryInvite
    {
        return new AdvisoryInvite;
    }

    protected function getRole(): Role
    {
        return Role::Advisor;
    }

    protected function getPanelName(): string
    {
        return 'advisor';
    }

    protected function getTenantId(): ?int
    {
        return $this->invite->advisory_id;
    }

    protected function attachTenantRelation(User $user): void
    {
        /** @phpstan-ignore-next-line */
        $this->invite->advisory->users()->attach($user);
    }

    public function getHeading(): string|Htmlable
    {
        return __('advisor/pages/auth/accept-advisory-invite.heading');
    }

    public function getSubheading(): Htmlable|string|null
    {
        return __('advisor/pages/auth/accept-advisory-invite.subheading');
    }
}
