<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Filament\Shared\Pages\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Config::set('app.require_2fa', false);
});

/**
 * A user with app authentication enabled, optionally with recovery codes.
 *
 * The codes are stored the way the provider stores them: hashed, so the
 * plaintext only exists in the test.
 *
 * @param  array<string>|null  $recoveryCodes
 */
function mfaChallengeUser(string $secret, ?array $recoveryCodes = null): User
{
    return User::factory()->create([
        'email' => 'admin@example.com',
        'password' => 'password',
        'role' => Role::Admin,
        'app_authentication_secret' => $secret,
        'app_authentication_recovery_codes' => is_array($recoveryCodes)
            ? array_map(fn (string $code): string => Hash::make($code), $recoveryCodes)
            : null,
    ]);
}

/**
 * Drive a full login: correct credentials first, then the second factor.
 *
 * The first call only puts the component into its challenge state; the second
 * one carries the answer to the challenge.
 *
 * @param  array<string, mixed>  $multiFactor
 */
function submitMfaChallenge(User $user, array $multiFactor): void
{
    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password', 'remember' => false])
        ->call('authenticate')
        ->set('data', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => false,
            'multiFactor' => ['app' => $multiFactor],
        ])
        ->call('authenticate');
}

test('a valid app authentication code completes the login', function () {
    $google2FA = app(Google2FA::class);
    $secret = $google2FA->generateSecretKey(16);

    $user = mfaChallengeUser($secret);

    submitMfaChallenge($user, [
        'code' => $google2FA->getCurrentOtp($secret),
        'useRecoveryCode' => false,
    ]);

    expect(Filament::auth()->check())->toBeTrue()
        ->and(Filament::auth()->id())->toEqual($user->id);
});

test('a valid recovery code completes the login and is consumed', function () {
    $google2FA = app(Google2FA::class);
    $secret = $google2FA->generateSecretKey(16);

    $recoveryCodes = ['first-recovery-code', 'second-recovery-code'];
    $user = mfaChallengeUser($secret, $recoveryCodes);

    submitMfaChallenge($user, [
        'code' => '',
        'recoveryCode' => $recoveryCodes[0],
        'useRecoveryCode' => true,
    ]);

    expect(Filament::auth()->check())->toBeTrue()
        ->and(Filament::auth()->id())->toEqual($user->id);

    // The used code must be gone from the stored set, and the untouched one
    // must still be there.
    $storedCodes = $user->refresh()->getAppAuthenticationRecoveryCodes() ?? [];

    expect($storedCodes)->toHaveCount(1)
        ->and(Hash::check($recoveryCodes[0], $storedCodes[0]))->toBeFalse()
        ->and(Hash::check($recoveryCodes[1], $storedCodes[0]))->toBeTrue();

    // A single-use code must not work a second time.
    Filament::auth()->logout();

    submitMfaChallenge($user, [
        'code' => '',
        'recoveryCode' => $recoveryCodes[0],
        'useRecoveryCode' => true,
    ]);

    expect(Filament::auth()->check())->toBeFalse();
});

test('an app authentication code older than the one already accepted is rejected', function () {
    $google2FA = app(Google2FA::class);
    $secret = $google2FA->generateSecretKey(16);

    $user = mfaChallengeUser($secret);

    // Both codes are valid for the same secret and both fall inside the
    // verification window; the only difference is their age.
    $timestamp = $google2FA->getTimestamp();
    $currentCode = $google2FA->oathTotp($secret, $timestamp);
    $olderCode = $google2FA->oathTotp($secret, $timestamp - 4);

    submitMfaChallenge($user, [
        'code' => $currentCode,
        'useRecoveryCode' => false,
    ]);

    expect(Filament::auth()->check())->toBeTrue();

    Filament::auth()->logout();

    // Accepting a code fixes a floor: anything issued before it is spent, so
    // an older code must no longer complete a challenge.
    submitMfaChallenge($user, [
        'code' => $olderCode,
        'useRecoveryCode' => false,
    ]);

    expect(Filament::auth()->check())->toBeFalse();
});
