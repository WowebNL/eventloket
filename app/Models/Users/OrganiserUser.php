<?php

namespace App\Models\Users;

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\FormsubmissionSession;
use App\Models\Organisation;
use App\Models\Traits\ScopesByRole;
use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class OrganiserUser extends User implements FilamentUser, HasTenants
{
    use ScopesByRole;

    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'organisation_user')->withPivot('role');
    }

    public function canAccessOrganisation(int $organisationId, ?OrganisationRole $role = null): bool
    {
        $query = $this->organisations()
            ->wherePivot('organisation_id', $organisationId);

        if ($role !== null) {
            $query->wherePivot('role', $role->value);
        }

        return $query->exists();
    }

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

    public static function getRoleKey(): string
    {
        return 'users.role';
    }

    public function formsubmissionSessions(): HasMany
    {
        return $this->hasMany(FormsubmissionSession::class);
    }
}
