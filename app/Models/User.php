<?php

namespace App\Models;

use App\Enums\OrganisationRole;
use App\Enums\Role;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Stephenjude\FilamentTwoFactorAuthentication\TwoFactorAuthenticatable;

class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'phone',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
        ];
    }

    public function municipalities(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class);
    }

    public function advisories(): BelongsToMany
    {
        return $this->belongsToMany(Advisory::class);
    }

    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class)->withPivot('role');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => in_array($this->role, [Role::Admin, Role::MunicipalityAdmin, Role::Reviewer]),
            'advisor' => $this->role === Role::Advisor,
            'organiser' => $this->role === Role::Organiser,
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        return match ($panel->getId()) {
            'admin' => $this->role === Role::Admin ? Municipality::orderBy('name')->get() : $this->municipalities,
            'advisor' => $this->advisories,
            'organiser' => $this->organisations,
            default => null,
        };
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return match (get_class($tenant)) {
            Municipality::class => $this->canAccessMunicipality($tenant->id),
            Advisory::class => $this->canAccessAdvisory($tenant->id),
            Organisation::class => $this->canAccessOrganisation($tenant->id),
            default => false,
        };
    }

    public function canAccessMunicipality(int $municipalityId): bool
    {
        if ($this->role === Role::Admin) {
            return true;
        }

        return $this->municipalities->contains($municipalityId);
    }

    public function canAccessAdvisory(int $advisoryId): bool
    {
        return $this->advisories->contains($advisoryId);
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
}
