<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Traits\HasUuid;
use App\Models\Users\AdminUser;
use App\Models\Users\AdvisorUser;
use App\Models\Users\MunicipalityAdminUser;
use App\Models\Users\OrganiserUser;
use App\Models\Users\ReviewerMunicipalityAdminUser;
use App\Models\Users\ReviewerUser;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property array<string>|null $app_authentication_recovery_codes
 * @property Role $role
 */
class User extends Authenticatable implements HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected $table = 'users';

    public function getForeignKey()
    {
        return 'user_id';
    }

    public function getMorphClass()
    {
        return User::class;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
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
        'role',
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
        'openzaak_jwt',
        'openzaak_jwt_valid_till',
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

    public function unreadMessages()
    {
        return $this->belongsToMany(Message::class, 'unread_messages');
    }

    public function notificationPreferences()
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function municipalities()
    {
        return $this->belongsToMany(Municipality::class, 'municipality_user');
    }

    /**
     * Always store email in lowercase for consistency
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => strtolower($value),
        );
    }

    /**
     * setup name based on first and last name
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (string $value, array $attributes) {
                if (! empty($attributes['first_name']) && ! empty($attributes['last_name'])) {
                    return $attributes['first_name'].' '.$attributes['last_name'];
                }

                return $value;
            },
            set: function ($value, $attributes) {
                // set name when first_name and last_name have value
                if (! empty($attributes['first_name']) && ! empty($attributes['last_name'])) {
                    return $attributes['first_name'].' '.$attributes['last_name'];
                }

                return $value;
            }
        );
    }

    /**
     * Returns the model for a specific role
     */
    public static function resolveClassForRole(Role $role): string
    {
        return match ($role) {
            Role::Admin => AdminUser::class,
            Role::MunicipalityAdmin => MunicipalityAdminUser::class,
            Role::ReviewerMunicipalityAdmin => ReviewerMunicipalityAdminUser::class,
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

    /**
     * Get notification channels for a specific notification class
     */
    public function getNotificationChannels(string $notificationClass): array
    {
        $preference = $this->notificationPreferences()
            ->where('notification_class', $notificationClass)
            ->first();

        // Default to both mail and database if no preference is set
        return $preference ? $preference->channels : ['mail', 'database'];
    }
}
