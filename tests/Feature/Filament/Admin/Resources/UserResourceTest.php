<?php

use App\Enums\Role;
use App\Filament\Admin\Resources\UserResource\Actions\Reset2faAction;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

covers(Reset2faAction::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
        'app_authentication_secret' => null,
        'app_authentication_recovery_codes' => null,
    ]);

    actingAs($this->admin);
});

test('reset 2fa action is visible when user has 2fa secret set', function () {
    $user = User::factory()->create([
        'app_authentication_secret' => 'some-secret',
        'app_authentication_recovery_codes' => null,
    ]);

    livewire(ListUsers::class)
        ->assertTableActionVisible('reset_2fa', $user);
});

test('reset 2fa action is visible when user has recovery codes set', function () {
    $user = User::factory()->create([
        'app_authentication_secret' => null,
        'app_authentication_recovery_codes' => 'some-codes',
    ]);

    livewire(ListUsers::class)
        ->assertTableActionVisible('reset_2fa', $user);
});

test('reset 2fa action is hidden when user has no 2fa configured', function () {
    $user = User::factory()->create([
        'app_authentication_secret' => null,
        'app_authentication_recovery_codes' => null,
    ]);

    livewire(ListUsers::class)
        ->assertTableActionHidden('reset_2fa', $user);
});

test('reset 2fa action clears secret and recovery codes', function () {
    $user = User::factory()->create([
        'app_authentication_secret' => 'some-secret',
        'app_authentication_recovery_codes' => 'some-codes',
    ]);

    livewire(ListUsers::class)
        ->callTableAction('reset_2fa', $user);

    $user->refresh();

    expect($user->app_authentication_secret)->toBeNull();
    expect($user->app_authentication_recovery_codes)->toBeNull();
});

test('reset 2fa action logs activity', function () {
    $user = User::factory()->create([
        'app_authentication_secret' => 'some-secret',
        'app_authentication_recovery_codes' => 'some-codes',
    ]);

    livewire(ListUsers::class)
        ->callTableAction('reset_2fa', $user);

    expect(
        Activity::where('subject_id', $user->id)
            ->where('event', 'updated')
            ->where('description', 'User 2FA reset by admin')
            ->exists()
    )->toBeTrue();
});
