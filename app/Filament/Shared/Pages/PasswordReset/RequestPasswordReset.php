<?php

namespace App\Filament\Shared\Pages\PasswordReset;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;

class RequestPasswordReset extends \Filament\Auth\Pages\PasswordReset\RequestPasswordReset
{
    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void // @phpstan-ignore-line
    {
        $method ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 2)[1]['function'];
        $component ??= static::class;

        try {
            parent::rateLimit(
                config('auth.throttle.password_reset_request.max_attempts', 5),
                config('auth.throttle.password_reset_request.decay_seconds', 900),
                $method,
                $component
            );
        } catch (TooManyRequestsException $e) {
            activity('auth')
                ->event('lockout')
                ->withProperties([
                    'type' => 'password_reset_request',
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
}
