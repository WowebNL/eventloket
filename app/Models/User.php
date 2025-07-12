<?php

namespace App\Models;

use App\Enums\OrganisationRole;
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

class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
        ];
    }

    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class)->withPivot('role');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => true,
            'advisor' => true,
            'organiser' => true,
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        return match ($panel->getId()) {
            'admin' => null,
            'advisor' => null,
            'organiser' => $this->organisations,
            default => null,
        };
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return match (get_class($tenant)) {
            Organisation::class => $this->canAccessOrganisation($tenant->id),
            default => false,
            // TODO: Add other tenant types
        };
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
