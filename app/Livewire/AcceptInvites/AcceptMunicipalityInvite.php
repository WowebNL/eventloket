<?php

namespace App\Livewire\AcceptInvites;

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\MunicipalityInvite;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;

class AcceptMunicipalityInvite extends AbstractAcceptInvite
{
    protected function getInviteModel(): MunicipalityInvite
    {
        return new MunicipalityInvite;
    }

    protected function getRole(): Role
    {
        /** @phpstan-ignore-next-line */
        return $this->invite->role;
    }

    protected function getPanelName(): string
    {
        return 'municipality';
    }

    protected function getTenantId(): ?int
    {
        /** @var Municipality|null $municipality */
        $municipality = $this->invite->municipalities->first();

        return $municipality?->id;
    }

    protected function attachTenantRelation(User $user): void
    {
        foreach ($this->invite->municipalities as $key => $municipality) {
            /** @var Municipality $municipality */
            $municipality->users()->attach($user);
        }
    }

    public function getHeading(): string|Htmlable
    {
        return __('municipality/pages/auth/accept-municipality-invite.heading', ['role' => strtolower($this->getRole()->getLabel())]);
    }

    public function getSubheading(): Htmlable|string|null
    {
        return __('municipality/pages/auth/accept-municipality-invite.subheading');
    }
}
