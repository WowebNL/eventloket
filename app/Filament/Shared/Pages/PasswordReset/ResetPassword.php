<?php

namespace App\Filament\Shared\Pages\PasswordReset;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\RateLimiter;

class ResetPassword extends \Filament\Auth\Pages\PasswordReset\ResetPassword
{
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void // @phpstan-ignore-line
    {
        $method ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 2)[1]['function'];
        $component ??= static::class;

        try {
            parent::rateLimit(
                config('auth.throttle.password_reset.max_attempts', 5),
                config('auth.throttle.password_reset.decay_seconds', 900),
                $method,
                $component
            );
        } catch (TooManyRequestsException $e) {
            activity('auth')
                ->event('lockout')
                ->withProperties([
                    'type' => 'password_reset',
                    'email' => $this->email,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'panel' => Filament::getCurrentPanel()?->getId(),
                    'available_in_seconds' => $e->secondsUntilAvailable,
                ])
                ->log(__('activity/event.lockout'));

            throw $e;
        }
    }

    protected function isResetPasswordRateLimited(?string $email): bool
    {
        if (blank($email)) {
            return false;
        }

        $rateLimitingKey = 'filament-reset-password:'.sha1($email);
        $maxAttempts = config('auth.throttle.password_reset.max_attempts', 5);
        $decaySeconds = config('auth.throttle.password_reset.decay_seconds', 900);

        if (RateLimiter::tooManyAttempts($rateLimitingKey, $maxAttempts)) {
            activity('auth')
                ->event('lockout')
                ->withProperties([
                    'type' => 'password_reset',
                    'email' => $email,
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'panel' => Filament::getCurrentPanel()?->getId(),
                    'available_in_seconds' => RateLimiter::availableIn($rateLimitingKey),
                ])
                ->log(__('activity/event.lockout'));

            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'resetPassword',
                request()->ip(),
                RateLimiter::availableIn($rateLimitingKey),
            ))?->send();

            return true;
        }

        RateLimiter::hit($rateLimitingKey, $decaySeconds);

        return false;
    }
}
