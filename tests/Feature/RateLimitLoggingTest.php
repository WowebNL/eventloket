<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Organiser\Pages\Register;
use App\Filament\Shared\Pages\Login;
use App\Filament\Shared\Pages\PasswordReset\RequestPasswordReset;
use App\Filament\Shared\Pages\PasswordReset\ResetPassword;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
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

/*
|--------------------------------------------------------------------------
| Login rate limiting
|--------------------------------------------------------------------------
|
| Three counters, from strict to wide:
|   A  login-credentials:sha1("<email>|<ip>")                 per account and IP
|   B  login-account:sha1("<email>")                          per account, all IPs
|   C  livewire-rate-limiter:sha1("<component>|authenticate|<ip>")  the IP backstop
|
| They are only incremented on a failed credential check, never on a
| successful login and never on the multi-factor submit of a single login.
|
*/

test('login lockout per account and IP is logged after exceeding max attempts', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
    ]);

    $strictKey = 'login-credentials:'.sha1('admin@example.com|127.0.0.1');
    $maxAttempts = config('auth.throttle.login.max_attempts', 5);

    for ($i = 0; $i < $maxAttempts; $i++) {
        RateLimiter::hit($strictKey);
    }

    livewire(Login::class)
        ->fillForm(['email' => 'admin@example.com', 'password' => 'password', 'remember' => false])
        ->call('authenticate');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->where('properties->type', 'credentials')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Geblokkeerd')
        ->and($activity->properties->get('email'))->toBe('admin@example.com')
        ->and($activity->properties->get('panel'))->toBe('admin')
        ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0)
        ->and(Filament::auth()->check())->toBeFalse();
});

test('a colleague behind the same IP is not locked out by another account', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
    ]);

    // The factory always seeds an app authentication secret, which would send
    // this login through the multi-factor step. Null keeps it a plain login.
    $colleague = User::factory()->create([
        'email' => 'colleague@example.com',
        'password' => 'password',
        'role' => Role::Admin,
        'app_authentication_secret' => null,
    ]);

    $strictKey = 'login-credentials:'.sha1('admin@example.com|127.0.0.1');

    for ($i = 0; $i < config('auth.throttle.login.max_attempts', 5); $i++) {
        RateLimiter::hit($strictKey);
    }

    livewire(Login::class)
        ->fillForm(['email' => $colleague->email, 'password' => 'password', 'remember' => false])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(Filament::auth()->check())->toBeTrue()
        ->and(Activity::where('log_name', 'auth')->where('event', 'lockout')->exists())->toBeFalse();
});

test('uppercase and surrounding whitespace do not escape the login counter', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
    ]);

    $strictKey = 'login-credentials:'.sha1('admin@example.com|127.0.0.1');

    for ($i = 0; $i < config('auth.throttle.login.max_attempts', 5); $i++) {
        RateLimiter::hit($strictKey);
    }

    livewire(Login::class)
        ->fillForm(['email' => '  ADMIN@Example.COM ', 'password' => 'password', 'remember' => false])
        ->call('authenticate');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->where('properties->type', 'credentials')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('email'))->toBe('admin@example.com');
});

test('a successful login consumes no rate limit budget', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
        'app_authentication_secret' => null,
    ]);

    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password', 'remember' => false])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(Filament::auth()->check())->toBeTrue()
        ->and(RateLimiter::attempts('login-credentials:'.sha1('admin@example.com|127.0.0.1')))->toBe(0)
        ->and(RateLimiter::attempts('login-account:'.sha1('admin@example.com')))->toBe(0)
        ->and(RateLimiter::attempts('livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1')))->toBe(0);
});

test('the multi-factor step consumes no rate limit budget', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $secret = app(Google2FA::class)->generateSecretKey(16);

    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
        'app_authentication_secret' => $secret,
    ]);

    $component = livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password', 'remember' => false])
        ->call('authenticate');

    expect(Filament::auth()->check())->toBeFalse();

    $component
        ->set('data', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => false,
            'multiFactor' => [
                'app' => [
                    'code' => app(Google2FA::class)->getCurrentOtp($secret),
                    'useRecoveryCode' => false,
                ],
            ],
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(Filament::auth()->check())->toBeTrue()
        ->and(RateLimiter::attempts('login-credentials:'.sha1('admin@example.com|127.0.0.1')))->toBe(0)
        ->and(RateLimiter::attempts('login-account:'.sha1('admin@example.com')))->toBe(0)
        ->and(RateLimiter::attempts('livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1')))->toBe(0)
        ->and(RateLimiter::attempts("filament-multi-factor-challenge:{$user->getAuthIdentifier()}"))->toBe(0);
});

test('a successful login clears the account counters but not the IP backstop', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
        'app_authentication_secret' => null,
    ]);

    $strictKey = 'login-credentials:'.sha1('admin@example.com|127.0.0.1');
    $accountKey = 'login-account:'.sha1('admin@example.com');
    $ipKey = 'livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1');

    for ($i = 0; $i < 3; $i++) {
        livewire(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'wrong-password', 'remember' => false])
            ->call('authenticate');
    }

    expect(RateLimiter::attempts($strictKey))->toBe(3)
        ->and(RateLimiter::attempts($accountKey))->toBe(3)
        ->and(RateLimiter::attempts($ipKey))->toBe(3);

    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password', 'remember' => false])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    // The IP backstop must survive: clearing it would turn one valid account
    // into an unlimited reset primitive for the whole IP address.
    expect(Filament::auth()->check())->toBeTrue()
        ->and(RateLimiter::attempts($strictKey))->toBe(0)
        ->and(RateLimiter::attempts($accountKey))->toBe(0)
        ->and(RateLimiter::attempts($ipKey))->toBe(3);
});

test('a reused multi-factor step still counts towards the limiters', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $secret = app(Google2FA::class)->generateSecretKey(16);

    $attacker = User::factory()->create([
        'email' => 'attacker@example.com',
        'password' => 'password',
        'role' => Role::Admin,
        'app_authentication_secret' => $secret,
    ]);

    // Step one with their own valid credentials leaves the multi-factor flag set
    // on the component. A Livewire snapshot is a plain HMAC over the state with
    // no nonce or expiry, so that snapshot can be replayed indefinitely.
    $component = livewire(Login::class)
        ->fillForm(['email' => $attacker->email, 'password' => 'password', 'remember' => false])
        ->call('authenticate');

    expect(Filament::auth()->check())->toBeFalse()
        ->and($component->instance()->userUndertakingMultiFactorAuthentication)->not->toBeNull();

    // Same component state, now guessing somebody else's password. Holding on to
    // the component instance is what a replayed snapshot amounts to.
    $component
        ->set('data', [
            'email' => 'victim@example.com',
            'password' => 'wrong-password',
            'remember' => false,
        ])
        ->call('authenticate');

    // The credential check fails before the multi-factor branch is ever reached,
    // so this must cost budget on all three counters. Skipping them here would
    // hand out an unlimited password oracle.
    expect(Filament::auth()->check())->toBeFalse()
        ->and(RateLimiter::attempts('login-credentials:'.sha1('victim@example.com|127.0.0.1')))->toBe(1)
        ->and(RateLimiter::attempts('login-account:'.sha1('victim@example.com')))->toBe(1)
        ->and(RateLimiter::attempts('livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1')))->toBe(1);
});

test('the IP backstop fires across many different accounts', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Config::set('auth.throttle.login_ip.max_attempts', 3);

    foreach (['one@example.com', 'two@example.com', 'three@example.com'] as $email) {
        livewire(Login::class)
            ->fillForm(['email' => $email, 'password' => 'wrong-password', 'remember' => false])
            ->call('authenticate');
    }

    livewire(Login::class)
        ->fillForm(['email' => 'four@example.com', 'password' => 'wrong-password', 'remember' => false])
        ->call('authenticate');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->where('properties->type', 'credentials_ip')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('email'))->toBe('four@example.com')
        ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0);
});

test('the account counter fires across multiple IP addresses', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Config::set('auth.throttle.login_account.max_attempts', 3);

    $email = 'admin@example.com';
    $strictKey = 'login-credentials:'.sha1($email.'|127.0.0.1');
    $accountKey = 'login-account:'.sha1($email);

    // The account key deliberately carries no IP component, so failures from
    // other addresses land in this same bucket. The Livewire test harness builds
    // a fresh request per call and pins REMOTE_ADDR to 127.0.0.1, so those other
    // addresses are seeded here instead of driven through the component.
    for ($i = 0; $i < 3; $i++) {
        RateLimiter::hit($accountKey);
    }

    livewire(Login::class)
        ->fillForm(['email' => $email, 'password' => 'wrong-password', 'remember' => false])
        ->call('authenticate');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->first();

    // This IP has not failed once, so only the account counter can be the reason.
    expect(RateLimiter::attempts($strictKey))->toBe(0)
        ->and($activity)->not->toBeNull()
        ->and($activity->properties->get('type'))->toBe('credentials_account')
        ->and($activity->properties->get('email'))->toBe($email)
        ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0);
});

test('nonsensical throttle configuration does not disable the login limiter', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // A string decay value used to blow up inside Carbon, and a value casting to
    // zero would silently switch the limiter off. Both are handled on the call
    // site, because Config::set() bypasses the validation in config/auth.php
    // exactly like a cached config artefact would.
    foreach (['900', '', 'abc'] as $index => $decaySeconds) {
        Config::set('auth.throttle.login.decay_seconds', $decaySeconds);

        $email = "user{$index}@example.com";

        for ($i = 0; $i < 6; $i++) {
            livewire(Login::class)
                ->fillForm(['email' => $email, 'password' => 'wrong-password', 'remember' => false])
                ->call('authenticate');
        }

        $activity = Activity::where('log_name', 'auth')
            ->where('event', 'lockout')
            ->where('properties->type', 'credentials')
            ->where('properties->email', $email)
            ->first();

        expect($activity)->not->toBeNull()
            ->and($activity->properties->get('available_in_seconds'))->toBeGreaterThan(0);
    }
});

test('a non numeric throttle maximum falls back to the default instead of the floor', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    // (int) '' is 0. Clamping that to the floor of 1 would lock every user in
    // the application out after a single mistyped password, so an unusable
    // value has to fall back to the documented default of 5 instead.
    Config::set('auth.throttle.login.max_attempts', '');

    $email = 'admin@example.com';

    for ($i = 0; $i < 5; $i++) {
        livewire(Login::class)
            ->fillForm(['email' => $email, 'password' => 'wrong-password', 'remember' => false])
            ->call('authenticate');
    }

    expect(Activity::where('log_name', 'auth')->where('event', 'lockout')->exists())->toBeFalse();

    // The default still has to be a working limit, not an absent one.
    livewire(Login::class)
        ->fillForm(['email' => $email, 'password' => 'wrong-password', 'remember' => false])
        ->call('authenticate');

    $activity = Activity::where('log_name', 'auth')
        ->where('event', 'lockout')
        ->where('properties->type', 'credentials')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->properties->get('email'))->toBe($email)
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
