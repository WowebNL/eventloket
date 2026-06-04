<?php

namespace App\Filament\Shared\Pages;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class Login extends \Filament\Auth\Pages\Login
{
    public function authenticate(): ?LoginResponse
    {
        $isMfaStep = filled($this->userUndertakingMultiFactorAuthentication);

        try {
            return parent::authenticate();
        } catch (ValidationException $e) {
            if ($isMfaStep) {
                activity('auth')
                    ->event('mfa_failed')
                    ->withProperties([
                        'ip' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'panel' => Filament::getCurrentPanel()?->getId(),
                    ])
                    ->log(__('activity/event.mfa_failed'));
            }

            throw $e;
        }
    }

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void // @phpstan-ignore-line
    {
        $method ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 2)[1]['function'];
        $component ??= static::class;

        try {
            parent::rateLimit(
                config('auth.throttle.login.max_attempts', 5),
                config('auth.throttle.login.decay_seconds', 900),
                $method,
                $component
            );
        } catch (TooManyRequestsException $e) {
            activity('auth')
                ->event('lockout')
                ->withProperties([
                    'type' => 'credentials',
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

    protected function isMultiFactorChallengeRateLimited(Authenticatable $user): bool
    {
        $rateLimitingKey = "filament-multi-factor-challenge:{$user->getAuthIdentifier()}";
        $maxAttempts = config('auth.throttle.mfa.max_attempts', 5);
        $decaySeconds = config('auth.throttle.mfa.decay_seconds', 900);

        if (RateLimiter::tooManyAttempts($rateLimitingKey, $maxAttempts)) {
            activity('auth')
                ->event('lockout')
                ->causedBy($user instanceof Model ? $user : null)
                ->withProperties([
                    'type' => 'mfa',
                    'email' => $this->data['email'] ?? null,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'panel' => Filament::getCurrentPanel()?->getId(),
                    'available_in_seconds' => RateLimiter::availableIn($rateLimitingKey),
                ])
                ->log(__('activity/event.lockout'));

            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'authenticate',
                request()->ip(),
                RateLimiter::availableIn($rateLimitingKey),
            ))?->send();

            return true;
        }

        RateLimiter::hit($rateLimitingKey, $decaySeconds);

        return false;
    }

    protected function getRememberFormComponent(): Hidden
    {
        return Hidden::make('remember')->default(false);
    }

    public function getHeading(): string|Htmlable
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return __('filament-panels::auth/pages/login.multi_factor.heading');
        }

        $panelId = Filament::getCurrentPanel()->getId();
        $label = __("shared/pages/login.type.{$panelId}");

        return __('shared/pages/login.heading', ['type' => $label]);
    }
}
