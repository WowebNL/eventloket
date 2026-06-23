<?php

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
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

    $activity = Activity::where('log_name', 'auth')->where('event', 'login')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Ingelogd')
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

    $activity = Activity::where('log_name', 'auth')->where('event', 'login_failed')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Inlogpoging mislukt')
        ->and($activity->properties->get('credentials')['email'])->toBe('wrong@example.com')
        ->and($activity->properties->get('ip'))->not->toBeNull()
        ->and($activity->properties->get('user_agent'))->not->toBeNull()
        ->and($activity->properties->get('login_path'))->not->toBeNull()
        ->and($activity->properties->get('guard'))->toBe('web');
});

test('logout is logged with user and guard information', function () {
    event(new Logout('web', $this->admin));

    $activity = Activity::where('log_name', 'auth')->where('event', 'logout')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Uitgelogd')
        ->and($activity->causer_id)->toEqual($this->admin->id)
        ->and($activity->properties->get('guard'))->toBe('web')
        ->and($activity->properties->get('ip'))->not->toBeNull()
        ->and($activity->properties->get('user_agent'))->not->toBeNull();
});

test('password reset is logged with user information', function () {
    event(new PasswordReset($this->admin));

    $activity = Activity::where('log_name', 'auth')->where('event', 'password_reset')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->description)->toBe('Wachtwoord opnieuw ingesteld')
        ->and($activity->causer_id)->toEqual($this->admin->id)
        ->and($activity->properties->get('ip'))->not->toBeNull()
        ->and($activity->properties->get('user_agent'))->not->toBeNull();
});

test('user model changes are logged via LogsActivity', function () {
    $this->admin->update(['first_name' => 'UpdatedName']);

    $activity = Activity::where('subject_type', User::class)
        ->where('event', 'updated')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toEqual($this->admin->id)
        ->and($activity->properties->get('attributes'))->toHaveKey('first_name');
});

test('user model password changes are not logged', function () {
    $this->admin->update(['password' => 'newpassword123']);

    $activity = Activity::where('subject_type', User::class)
        ->where('event', 'updated')
        ->first();

    if ($activity !== null) {
        expect($activity->properties->get('attributes'))->not->toHaveKey('password');
    } else {
        expect(true)->toBeTrue();
    }
});

test('changes to only excluded fields produce no activity log entry', function () {
    $this->admin->saveAppAuthenticationSecret('FJGXOAQ4DBS6PRCP');

    expect(
        Activity::where('subject_type', User::class)
            ->where('event', 'updated')
            ->whereJsonLength('properties->attributes', 0)
            ->exists()
    )->toBeFalse();
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
//    $activity = Activity::where('log_name', 'auth')->where('event', 'lockout')->first();
//
//    expect($activity)->not->toBeNull()
//        ->and($activity->description)->toBe('Account locked out due to too many failed attempts')
//        ->and($activity->properties->get('email'))->toBe('lockout@example.com')
//        ->and($activity->properties->get('ip'))->not->toBeNull()
//        ->and($activity->properties->get('user_agent'))->not->toBeNull()
//        ->and($activity->properties->get('login_path'))->not->toBeNull();
// });
