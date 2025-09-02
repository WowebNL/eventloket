<?php

namespace App\Livewire\AcceptInvites;

use App\Enums\Role;
use App\Models\OrganisationInvite;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;

class AcceptOrganisationInvite extends AbstractAcceptInvite
{
    protected function getInviteModel(): OrganisationInvite
    {
        return new OrganisationInvite;
    }

    protected function getRole(): Role
    {
        return Role::Organiser;
    }

    protected function getPanelName(): string
    {
        return 'organiser';
    }

    protected function getTenantId(): ?int
    {
        return $this->invite->organisation_id;
    }

    protected function attachTenantRelation(User $user): void
    {
        /** @phpstan-ignore-next-line */
        $this->invite->organisation->users()->attach($user, [
            'role' => $this->invite->role,
        ]);
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
