<?php

namespace App\Filament\Organiser\Pages;

use App\Enums\Role;
use App\Models\Users\OrganiserUser;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\RateLimiter;

class Register extends \Filament\Auth\Pages\Register
{
    protected string $userModel = OrganiserUser::class;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                EditProfile::getFirstNameFormComponent(),
                EditProfile::getLastNameFormComponent(),
                $this->getEmailFormComponent(),
                TextInput::make('phone')
                    ->label(__('organiser/pages/auth/register.form.phone.label'))
                    ->maxLength(20)
                    ->required(),
                /** @phpstan-ignore-next-line */
                $this->getPasswordFormComponent()->helperText(app()->isProduction() ? __('organiser/pages/auth/register.form.password.helper_text') : null),
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function mutateFormDataBeforeRegister(array $data): array
    {
        $data['role'] = Role::Organiser;
        $data['name'] = $data['first_name'].' '.$data['last_name'];

        return $data;
    }

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void // @phpstan-ignore-line
    {
        $method ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 2)[1]['function'];
        $component ??= static::class;

        try {
            parent::rateLimit(
                config('auth.throttle.registration.max_attempts', 5),
                config('auth.throttle.registration.decay_seconds', 900),
                $method,
                $component
            );
        } catch (TooManyRequestsException $e) {
            activity('auth')
                ->event('lockout')
                ->withProperties([
                    'type' => 'registration',
                    'email' => $this->data['email'] ?? null,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'panel' => Filament::getCurrentPanel()?->getId(),
                    'available_in_seconds' => $e->secondsUntilAvailable,
                ])
                ->log(__('activity/event.lockout'));

            throw $e;
        }
    }

    protected function isRegisterRateLimited(string $email): bool
    {
        if (blank($email)) {
            return false;
        }

        $rateLimitingKey = 'filament-register:'.sha1($email);
        $maxAttempts = config('auth.throttle.registration.max_attempts', 5);
        $decaySeconds = config('auth.throttle.registration.decay_seconds', 900);

        if (RateLimiter::tooManyAttempts($rateLimitingKey, $maxAttempts)) {
            activity('auth')
                ->event('lockout')
                ->withProperties([
                    'type' => 'registration',
                    'email' => $email,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'panel' => Filament::getCurrentPanel()?->getId(),
                    'available_in_seconds' => RateLimiter::availableIn($rateLimitingKey),
                ])
                ->log(__('activity/event.lockout'));

            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'register',
                request()->ip(),
                RateLimiter::availableIn($rateLimitingKey),
            ))?->send();

            return true;
        }

        RateLimiter::hit($rateLimitingKey, $decaySeconds);

        return false;
    }
}
