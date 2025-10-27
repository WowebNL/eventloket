<?php

namespace App\Models\Users;

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Models\Advisory;
use App\Models\Threads\AdviceThread;
use App\Models\Traits\ScopesByRole;
use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class AdvisorUser extends User implements FilamentUser, HasTenants
{
    use ScopesByRole;

    public function advisories(): BelongsToMany
    {
        return $this->belongsToMany(Advisory::class, 'advisory_user')->withPivot('role');
    }

    public function assignedAdviceThreads()
    {
        return $this->belongsToMany(AdviceThread::class, 'thread_user');
    }

    public function canAccessAdvisory(int $advisoryId, ?AdvisoryRole $as = null): bool
    {
        $query = $this->advisories()
            ->wherePivot('advisory_id', $advisoryId);

        if ($as !== null) {
            $query->wherePivot('role', $as->value);
        }

        return $query->exists();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'advisor';
    }

    public function getTenants(Panel $panel): Collection
    {
        return $this->advisories;
    }

    /**
     * @phpstan-param \App\Models\Advisory $tenant
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $this->canAccessAdvisory($tenant->id);
    }

    public static function getRole(): Role
    {
        return Role::Advisor;
    }

    public static function getRoleKey(): string
    {
        return 'users.role';
    }
}
