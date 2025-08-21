<?php

namespace App\Models;

use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Models\Users\AdminUser;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\OrganiserUser;
use App\Models\Users\ReviewerUser;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property array<string>|null $app_authentication_recovery_codes
 */
class User extends Authenticatable implements HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'users';

    public function getForeignKey()
    {
        return 'user_id';
    }

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
        'app_authentication_secret',
        'app_authentication_recovery_codes',
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
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /**
     * Returns the model for a specific role
     */
    public static function resolveClassForRole(Role $role): string
    {
        return match ($role) {
            Role::Admin => AdminUser::class,
            Role::MunicipalityAdmin => MunicipalityAdminUser::class,
            Role::Reviewer => ReviewerUser::class,
            Role::Advisor => AdvisorUser::class,
            Role::Organiser => OrganiserUser::class,
        };
    }

    public function newFromBuilder($attributes = [], $connection = null)
    {
        $attributes = (array) $attributes;

        $class = self::resolveClassForRole(Role::from($attributes['role']));

        $model = (new $class)->newInstance([], true);

        $model->setRawAttributes($attributes, true);

        $model->setConnection($connection ?: $this->getConnectionName());

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    public function municipalities(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class, 'municipality_user');
    }

    public function advisories(): BelongsToMany
    {
        return $this->belongsToMany(Advisory::class, 'advisory_user');
    }

    public function organisations(): BelongsToMany
    {
        return $this->belongsToMany(Organisation::class, 'organisation_user')->withPivot('role');
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

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /**
     * @return ?array<string>
     */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /**
     * @param  array<string> | null  $codes
     */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
