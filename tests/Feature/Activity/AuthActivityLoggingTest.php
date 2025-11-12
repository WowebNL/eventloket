<?php

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Config::set('app.require_2fa', false);

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
    ]);
});

test('successful login is logged with user information', function () {
    event(new Login('web', $this->admin, false));

    expect(Activity::count())->toBe(1);

    $activity = Activity::first();

    expect($activity->log_name)->toBe('auth')
        ->and($activity->event)->toBe('login')
        ->and($activity->description)->toBe('User logged in')
        ->and($activity->properties->get('ip'))->not->toBeNull()
        ->and($activity->properties->get('user_agent'))->not->toBeNull()
        ->and($activity->properties->get('login_path'))->not->toBeNull()
        ->and($activity->properties->get('guard'))->toBe('web');
});

test('failed login is logged with email and IP', function () {
    $credentials = [
        'email' => 'wrong@example.com',
        'password' => 'wrongpassword',
    ];

    event(new Failed('web', null, $credentials));

    expect(Activity::count())->toBe(1);

    $activity = Activity::first();

    expect($activity->log_name)->toBe('auth')
        ->and($activity->event)->toBe('login_failed')
        ->and($activity->description)->toBe('Failed login attempt')
        ->and($activity->properties->get('credentials')['email'])->toBe('wrong@example.com')
        ->and($activity->properties->get('ip'))->not->toBeNull()
        ->and($activity->properties->get('user_agent'))->not->toBeNull()
        ->and($activity->properties->get('login_path'))->not->toBeNull()
        ->and($activity->properties->get('guard'))->toBe('web');
});

// TODO: Filament 2FA failed triggered geen events.
// test('failed login detects 2FA failure', function () {
//    //
// });

// TODO: Lockout event is not triggered by Filament.
// test('lockout event is logged', function () {
//    $request = Request::create('/login', 'POST', [
//        'email' => 'lockout@example.com',
//        'password' => 'password',
//    ]);
//
//    event(new Lockout($request));
//
//    expect(Activity::count())->toBe(1);
//
//    $activity = Activity::first();
//
//    expect($activity->log_name)->toBe('auth')
//        ->and($activity->event)->toBe('lockout')
//        ->and($activity->description)->toBe('Account locked out due to too many failed attempts')
//        ->and($activity->properties->get('email'))->toBe('lockout@example.com')
//        ->and($activity->properties->get('ip'))->not->toBeNull()
//        ->and($activity->properties->get('user_agent'))->not->toBeNull()
//        ->and($activity->properties->get('login_path'))->not->toBeNull();
// });
