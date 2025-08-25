<?php

namespace App\Models\Users;

use App\Enums\Role;
use App\Models\Municipality;
use App\Models\Traits\ScopesByRole;
use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class ReviewerUser extends User implements FilamentUser, HasTenants
{
    use ScopesByRole;

    public function municipalities(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class, 'municipality_user');
    }

    public function canAccessMunicipality(int $municipalityId): bool
    {
        return $this->municipalities->contains($municipalityId);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'municipality';
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->municipalities;
    }

    /**
     * @phpstan-param \App\Models\Municipality $tenant
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->canAccessMunicipality($tenant->id);
    }

    public static function getRole(): Role
    {
        return Role::Reviewer;
    }
}
