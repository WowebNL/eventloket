<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Organiser\Pages\Register;
use App\Filament\Shared\Pages\PasswordReset\RequestPasswordReset;
use App\Filament\Shared\Pages\PasswordReset\ResetPassword;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Config::set('app.require_2fa', false);
});

test('password reset request lockout is logged after exceeding max attempts', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $rateLimitKey = 'livewire-rate-limiter:'.sha1(RequestPasswordReset::class.'|request|127.0.0.1');
    $maxAttempts = config('auth.throttle.password_reset_request.max_attempts', 5);

    for ($i = 0; $i < $maxAttempts; $i++) {
        RateLimiter::hit($rateLimitKey);
    }

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => 'test@example.com'])
        ->call('request');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->where('properties->type', 'password_reset_request')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('type'))->toBe('password_reset_request')
        ->and($activity->properties->get('email'))->toBe('test@example.com')
        ->and($activity->properties->get('panel'))->toBe('admin')
        ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0);
});

test('password reset submit lockout is logged after exceeding max attempts per IP', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $rateLimitKey = 'livewire-rate-limiter:'.sha1(ResetPassword::class.'|resetPassword|127.0.0.1');
    $maxAttempts = config('auth.throttle.password_reset.max_attempts', 5);

    for ($i = 0; $i < $maxAttempts; $i++) {
        RateLimiter::hit($rateLimitKey);
    }

    livewire(ResetPassword::class, ['email' => 'test@example.com', 'token' => 'dummy-token'])
        ->fillForm(['password' => 'Password123!', 'passwordConfirmation' => 'Password123!'])
        ->call('resetPassword');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->where('properties->type', 'password_reset')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('type'))->toBe('password_reset')
        ->and($activity->properties->get('email'))->toBe('test@example.com')
        ->and($activity->properties->get('panel'))->toBe('admin')
        ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0);
});

test('password reset submit lockout is logged after exceeding max attempts per email', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $email = 'test@example.com';
    $rateLimitKey = 'filament-reset-password:'.sha1($email);
    $maxAttempts = config('auth.throttle.password_reset.max_attempts', 5);

    for ($i = 0; $i < $maxAttempts; $i++) {
        RateLimiter::hit($rateLimitKey);
    }

    livewire(ResetPassword::class, ['email' => $email, 'token' => 'dummy-token'])
        ->fillForm(['password' => 'Password123!', 'passwordConfirmation' => 'Password123!'])
        ->call('resetPassword');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->where('properties->type', 'password_reset')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('type'))->toBe('password_reset')
        ->and($activity->properties->get('email'))->toBe($email)
        ->and($activity->properties->get('panel'))->toBe('admin')
        ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0);
});

test('registration lockout is logged after exceeding max attempts per IP', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    $rateLimitKey = 'livewire-rate-limiter:'.sha1(Register::class.'|register|127.0.0.1');
    $maxAttempts = config('auth.throttle.registration.max_attempts', 5);

    for ($i = 0; $i < $maxAttempts; $i++) {
        RateLimiter::hit($rateLimitKey);
    }

    livewire(Register::class)
        ->fillForm([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'newuser@example.com',
            'phone' => '0612345678',
            'password' => 'Password123!',
            'passwordConfirmation' => 'Password123!',
        ])
        ->call('register');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->where('properties->type', 'registration')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('type'))->toBe('registration')
        ->and($activity->properties->get('email'))->toBe('newuser@example.com')
        ->and($activity->properties->get('panel'))->toBe('organiser')
        ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0);
});

test('registration lockout is logged after exceeding max attempts per email', function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    $email = 'newuser@example.com';
    $rateLimitKey = 'filament-register:'.sha1($email);
    $maxAttempts = config('auth.throttle.registration.max_attempts', 5);

    for ($i = 0; $i < $maxAttempts; $i++) {
        RateLimiter::hit($rateLimitKey);
    }

    livewire(Register::class)
        ->fillForm([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'phone' => '0612345678',
            'password' => 'Password123!',
            'passwordConfirmation' => 'Password123!',
        ])
        ->call('register');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->where('properties->type', 'registration')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('type'))->toBe('registration')
        ->and($activity->properties->get('email'))->toBe($email)
        ->and($activity->properties->get('panel'))->toBe('organiser')
        ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0);
});

test('normal password reset request does not create a lockout log', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    User::factory()->create([
        'email' => 'existing@example.com',
        'role' => Role::Admin,
    ]);

    livewire(RequestPasswordReset::class)
        ->fillForm(['email' => 'existing@example.com'])
        ->call('request');

    expect(
        Activity::where('log_name', 'auth')->where('event', 'lockout')->first()
    )->toBeNull();
});
