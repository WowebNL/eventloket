<?php

namespace App\Models\Users;

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\Organisation;
use App\Models\Traits\ScopesByRole;
use App\Models\User;
use App\Models\Zaak;
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

    /**
     * The zaken this organiser submitted, soft deleted ones included: their
     * data is still there, so they still count as a reason to keep the
     * account. The foreign key is explicit because User::getForeignKey()
     * resolves to user_id.
     *
     * @return HasMany<Zaak, $this>
     */
    public function zaken(): HasMany
    {
        return $this->hasMany(Zaak::class, 'organiser_user_id')->withTrashed();
    }

    public function canAccessOrganisation(?int $organisationId, ?OrganisationRole $role = null): bool
    {
        if ($organisationId === null) {
            return false;
        }

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
     * @phpstan-param Organisation $tenant
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
}
