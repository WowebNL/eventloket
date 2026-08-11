<?php

namespace App\Filament\Shared\Pages;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

class Login extends \Filament\Auth\Pages\Login
{
    public function authenticate(): ?LoginResponse
    {
        $isMfaStep = filled($this->userUndertakingMultiFactorAuthentication);

        try {
            $response = parent::authenticate();
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

        if ($response !== null) {
            $this->clearCredentialRateLimiters();
        }

        return $response;
    }

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void // @phpstan-ignore-line
    {
        $method ??= debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, limit: 2)[1]['function'];
        $component ??= static::class;

        // Check only. The counters are incremented in fireFailedEvent(), so a
        // successful login and the second (multi-factor) submit of a single
        // login never consume any budget.
        foreach ($this->getCredentialRateLimiters($method, $component) as $limiter) {
            if (! RateLimiter::tooManyAttempts($limiter['key'], $limiter['max_attempts'])) {
                continue;
            }

            $secondsUntilAvailable = RateLimiter::availableIn($limiter['key']);

            activity('auth')
                ->event('lockout')
                ->withProperties([
                    'type' => $limiter['type'],
                    'email' => $this->getRateLimitedEmail(),
                    'ip' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'panel' => Filament::getCurrentPanel()?->getId(),
                    'available_in_seconds' => $secondsUntilAvailable,
                ])
                ->log(__('activity/event.lockout'));

            throw new TooManyRequestsException($component, $method, request()->ip(), $secondsUntilAvailable);
        }
    }

    /**
     * Increment the credential rate limiters.
     *
     * This is the only place where the login counters go up. The parent calls
     * this method on exactly the two credential failure paths and nowhere else,
     * which keeps the counters tied to a failure the server established itself
     * instead of to an attempt the client can shape.
     *
     * @param  array<string, mixed>  $credentials
     */
    protected function fireFailedEvent(Guard $guard, ?Authenticatable $user, #[SensitiveParameter] array $credentials): void
    {
        parent::fireFailedEvent($guard, $user, $credentials);

        foreach ($this->getCredentialRateLimiters(email: $credentials['email'] ?? null) as $limiter) {
            RateLimiter::hit($limiter['key'], $limiter['decay_seconds']);
        }
    }

    /**
     * Clear the account bound limiters after a fully successful login.
     *
     * The IP backstop is deliberately left alone. Clearing it would let anyone
     * with a valid account of their own reset the spraying budget for their
     * whole IP address by logging in once.
     */
    protected function clearCredentialRateLimiters(): void
    {
        foreach ($this->getCredentialRateLimiters() as $limiter) {
            if ($limiter['type'] === 'credentials_ip') {
                continue;
            }

            RateLimiter::clear($limiter['key']);
        }

        $user = Filament::auth()->user();

        if ($user !== null) {
            // Safe to clear: this user just proved both their password and their
            // second factor. Without this, logging in normally a handful of times
            // within the decay window would lock the user out of their own account.
            RateLimiter::clear("filament-multi-factor-challenge:{$user->getAuthIdentifier()}");
        }
    }

    /**
     * The credential rate limiters, ordered from strict to wide so the strictest
     * reason is the one that ends up in the activity log.
     *
     * @return list<array{key: string, type: string, max_attempts: int, decay_seconds: int}>
     */
    protected function getCredentialRateLimiters(?string $method = null, ?string $component = null, mixed $email = null): array
    {
        $method ??= 'authenticate';
        $component ??= static::class;

        $limiters = [];
        $email = $this->getRateLimitedEmail($email);

        // A blank or unusable address falls through to the IP backstop only.
        // Counting it would put every empty submit in the application on one
        // shared sha1('') bucket.
        if ($email !== null) {
            $limiters[] = [
                'key' => 'login-credentials:'.sha1($email.'|'.request()->ip()),
                'type' => 'credentials',
                'max_attempts' => $this->throttleValue('auth.throttle.login.max_attempts', 5, 1),
                'decay_seconds' => $this->throttleValue('auth.throttle.login.decay_seconds', 900, 60),
            ];

            $limiters[] = [
                'key' => 'login-account:'.sha1($email),
                'type' => 'credentials_account',
                'max_attempts' => $this->throttleValue('auth.throttle.login_account.max_attempts', 20, 1),
                'decay_seconds' => $this->throttleValue('auth.throttle.login_account.decay_seconds', 3600, 60),
            ];
        }

        $limiters[] = [
            'key' => $this->getRateLimitKey($method, $component),
            'type' => 'credentials_ip',
            'max_attempts' => $this->throttleValue('auth.throttle.login_ip.max_attempts', 50, 1),
            'decay_seconds' => $this->throttleValue('auth.throttle.login_ip.decay_seconds', 900, 60),
        ];

        return $limiters;
    }

    /**
     * Normalise the address the counters are keyed on.
     *
     * CaseInsensitiveUserProvider lowercases the address before looking the user
     * up, so the counter has to do the same or an attacker escapes it by varying
     * the casing. The trim() on top makes the counter strictly stricter than the
     * provider, which is the safe direction to fail in.
     */
    protected function getRateLimitedEmail(mixed $email = null): ?string
    {
        $email ??= $this->data['email'] ?? null;

        // $this->data is raw, unvalidated Livewire state: a crafted request can
        // put an array in here.
        if (! is_string($email)) {
            return null;
        }

        // The address ends up in the activity log, so clamp its length. Users
        // regularly type their password into the email field as well.
        $email = Str::limit(strtolower(trim($email)), 255, end: '');

        return blank($email) ? null : $email;
    }

    /**
     * Read a throttle value, validated.
     *
     * config/auth.php already does this, but Config::set() and a cached config
     * artefact both bypass that computation. A string decay value blows up deep
     * inside Carbon, and a zero decay silently disables the limiter altogether.
     *
     * A value that is not a number falls back to the default instead of being
     * clamped to the floor. Clamping is the right move for a decay that is set
     * too low, but for a maximum it fails the wrong way: (int) '' is 0, and
     * clamping that to 1 would lock every user in the application out after a
     * single mistyped password. Kept in sync with the $throttle helper in
     * config/auth.php.
     */
    protected function throttleValue(string $key, int $default, int $floor): int
    {
        $value = config($key, $default);

        return is_numeric($value) ? max($floor, (int) $value) : $default;
    }

    protected function isMultiFactorChallengeRateLimited(Authenticatable $user): bool
    {
        $rateLimitingKey = "filament-multi-factor-challenge:{$user->getAuthIdentifier()}";
        $maxAttempts = $this->throttleValue('auth.throttle.mfa.max_attempts', 5, 1);
        $decaySeconds = $this->throttleValue('auth.throttle.mfa.decay_seconds', 900, 60);

        if (RateLimiter::tooManyAttempts($rateLimitingKey, $maxAttempts)) {
            activity('auth')
                ->event('lockout')
                ->causedBy($user instanceof Model ? $user : null)
                ->withProperties([
                    'type' => 'mfa',
                    'email' => $this->getRateLimitedEmail(),
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
