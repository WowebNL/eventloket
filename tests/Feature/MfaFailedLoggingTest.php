<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Shared\Pages\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Config::set('app.require_2fa', false);
});

test('failed MFA challenge is logged', function () {
    $secret = app(Google2FA::class)->generateSecretKey(16);

    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
        'app_authentication_secret' => $secret,
    ]);

    $component = livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password', 'remember' => false])
        ->call('authenticate'); // First call: correct credentials → sets MFA state internally

    // Second call: correct credentials + wrong TOTP → triggers our logged ValidationException
    $component
        ->set('data', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => false,
            'multiFactor' => ['app' => ['code' => '000000', 'useRecoveryCode' => false]],
        ])
        ->call('authenticate');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'mfa_failed')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Twee-factor verificatie mislukt')
        ->and($activity->properties->get('ip'))->not->toBeNull()
        ->and($activity->properties->get('panel'))->toBe('admin');
});

test('credential lockout is logged after exceeding max attempts', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
    ]);

    $rateLimitKey = 'livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1');
    $maxAttempts = config('auth.throttle.login.max_attempts', 5);

    for ($i = 0; $i < $maxAttempts; $i++) {
        RateLimiter::hit($rateLimitKey);
    }

    livewire(Login::class)
        ->fillForm(['email' => 'admin@example.com', 'password' => 'password', 'remember' => false])
        ->call('authenticate');

    $activity = Activity::where('log_name', 'auth')->where('event', 'lockout')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Geblokkeerd')
        ->and($activity->properties->get('type'))->toBe('credentials')
        ->and($activity->properties->get('email'))->toBe('admin@example.com')
        ->and($activity->properties->get('panel'))->toBe('admin')
        ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0);
});

test('MFA lockout is logged after exceeding max MFA attempts', function () {
    $secret = app(Google2FA::class)->generateSecretKey(16);

    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
        'app_authentication_secret' => $secret,
    ]);

    $rateLimitKey = "filament-multi-factor-challenge:{$user->getAuthIdentifier()}";
    $maxAttempts = config('auth.throttle.mfa.max_attempts', 5);

    for ($i = 0; $i < $maxAttempts; $i++) {
        RateLimiter::hit($rateLimitKey);
    }

    $component = livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password', 'remember' => false])
        ->call('authenticate');

    $component
        ->set('data', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => false,
            'multiFactor' => ['app' => ['code' => '123456', 'useRecoveryCode' => false]],
        ])
        ->call('authenticate');

    $activity = Activity::where('log_name', 'auth')->where('event', 'lockout')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Geblokkeerd')
        ->and($activity->properties->get('type'))->toBe('mfa')
        ->and($activity->properties->get('email'))->toBe($user->email)
        ->and($activity->causer_id)->toEqual($user->id)
        ->and($activity->properties->get('panel'))->toBe('admin');
});

test('regular credential failure is not logged as MFA failure', function () {
    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
    ]);

    livewire(Login::class)
        ->set('data', [
            'email' => 'admin@example.com',
            'password' => 'wrongpassword',
            'remember' => false,
        ])
        ->call('authenticate');

    expect(
        Activity::where('log_name', 'auth')->where('event', 'mfa_failed')->first()
    )->toBeNull();
});
