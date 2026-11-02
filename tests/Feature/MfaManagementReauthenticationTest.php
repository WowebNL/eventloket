<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Shared\Pages\EditProfile;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function mfaManagementAction(string $name): TestAction
{
    return TestAction::make($name)->schemaComponent(true, 'content');
}

/**
 * @param  array<string>|null  $recoveryCodes
 */
function mfaManagementUser(?string $secret = null, ?array $recoveryCodes = null): User
{
    return User::factory()->create([
        'role' => Role::Admin,
        'password' => 'password',
        'app_authentication_secret' => $secret,
        'app_authentication_recovery_codes' => is_array($recoveryCodes)
            ? array_map(fn (string $code): string => Hash::make($code), $recoveryCodes)
            : null,
    ]);
}

function setUpAppAuthentication(?string $password): User
{
    $user = mfaManagementUser();
    test()->actingAs($user);

    $component = livewire(EditProfile::class)
        ->mountAction(mfaManagementAction('setUpAppAuthentication'));

    $secret = decrypt($component->instance()->mountedActions[0]['arguments']['encrypted'])['secret'];

    $component
        ->setActionData(array_filter([
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
            'password' => $password,
        ]))
        ->callMountedAction();

    $password === null
        ? $component->assertHasActionErrors(['password'])
        : $component->assertHasNoActionErrors();

    return $user->refresh();
}

test('setting up app authentication requires the current password', function () {
    $user = setUpAppAuthentication(password: null);

    expect($user->app_authentication_secret)->toBeNull();
});

test('setting up app authentication succeeds with the current password', function () {
    $user = setUpAppAuthentication(password: 'password');

    expect($user->app_authentication_secret)->not->toBeNull();
});

test('disabling app authentication requires the current password', function () {
    $google2FA = app(Google2FA::class);
    $secret = $google2FA->generateSecretKey(16);
    $user = mfaManagementUser($secret);
    $this->actingAs($user);

    livewire(EditProfile::class)
        ->callAction(mfaManagementAction('disableAppAuthentication'), data: [
            'code' => $google2FA->getCurrentOtp($secret),
        ])
        ->assertHasActionErrors(['password']);

    expect($user->refresh()->app_authentication_secret)->toBe($secret);
});

test('disabling app authentication succeeds with the current password', function () {
    $google2FA = app(Google2FA::class);
    $secret = $google2FA->generateSecretKey(16);
    $user = mfaManagementUser($secret);
    $this->actingAs($user);

    livewire(EditProfile::class)
        ->callAction(mfaManagementAction('disableAppAuthentication'), data: [
            'password' => 'password',
            'code' => $google2FA->getCurrentOtp($secret),
        ])
        ->assertHasNoActionErrors();

    expect($user->refresh()->app_authentication_secret)->toBeNull();
});

test('regenerating recovery codes requires the current password', function () {
    $google2FA = app(Google2FA::class);
    $secret = $google2FA->generateSecretKey(16);
    $user = mfaManagementUser($secret, ['recovery-code-one', 'recovery-code-two']);
    $before = $user->app_authentication_recovery_codes;
    $this->actingAs($user);

    livewire(EditProfile::class)
        ->callAction(mfaManagementAction('regenerateAppAuthenticationRecoveryCodes'), data: [
            'code' => $google2FA->getCurrentOtp($secret),
        ])
        ->assertHasActionErrors(['password']);

    expect($user->refresh()->app_authentication_recovery_codes)->toBe($before);
});

test('regenerating recovery codes succeeds with the current password', function () {
    $google2FA = app(Google2FA::class);
    $secret = $google2FA->generateSecretKey(16);
    $user = mfaManagementUser($secret, ['recovery-code-one', 'recovery-code-two']);
    $before = $user->app_authentication_recovery_codes;
    $this->actingAs($user);

    livewire(EditProfile::class)
        ->callAction(mfaManagementAction('regenerateAppAuthenticationRecoveryCodes'), data: [
            'password' => 'password',
            'code' => $google2FA->getCurrentOtp($secret),
        ])
        ->assertHasNoActionErrors();

    expect($user->refresh()->app_authentication_recovery_codes)->not->toBe($before);
});
