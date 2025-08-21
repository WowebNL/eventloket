<?php

namespace App\Models\Users;

use App\Enums\Role;
use App\Models\Traits\ScopesByRole;
use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OrganiserUser extends User implements FilamentUser, HasTenants
{
    use ScopesByRole;

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'organiser';
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->organisations;
    }

    /**
     * @phpstan-param \App\Models\Organisation $tenant
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->canAccessOrganisation($tenant->id);
    }

    public static function getRole(): Role
    {
        return Role::Organiser;
    }
}
