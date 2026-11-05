<?php

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Filament\Admin\Resources\AdvisoryResource\Pages\EditAdvisory;
use App\Filament\Admin\Resources\AdvisoryResource\RelationManagers\UsersRelationManager;
use App\Filament\Advisor\Clusters\Settings\Resources\AdvisorUsers\Pages\ListAdvisorUsers;
use App\Filament\Shared\Resources\AdvisorUsers\Tables\AdvisorUserTable;
use App\Models\Advisory;
use App\Models\User;
use App\Policies\AdvisorUserPolicy;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

covers(AdvisorUserTable::class, AdvisorUserPolicy::class);

beforeEach(function () {
    $this->advisory = Advisory::factory()->create(['name' => 'Advisory A']);
    $this->otherAdvisory = Advisory::factory()->create(['name' => 'Advisory B']);

    $this->adminAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->adminAdvisor, ['role' => AdvisoryRole::Admin]);

    // Belongs to this advisory only: an admin of this advisory may delete the account.
    $this->soloAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->soloAdvisor, ['role' => AdvisoryRole::Member]);

    // Belongs to both advisories: the account is shared, so it may only be detached.
    $this->sharedAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->sharedAdvisor, ['role' => AdvisoryRole::Member]);
    $this->otherAdvisory->users()->attach($this->sharedAdvisor, ['role' => AdvisoryRole::Member]);

    // Belongs to the other advisory only.
    $this->foreignAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->otherAdvisory->users()->attach($this->foreignAdvisor, ['role' => AdvisoryRole::Member]);
});

function advisorAccountIsDeleted(User $user): bool
{
    return User::withTrashed()->findOrFail($user->id)->trashed();
}

function actAsAdvisoryAdminOnAdvisorPanel(User $user, Advisory $tenant): void
{
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    // Boot the panel like a real request does, so the tenant scope on the table query applies.
    Filament::bootCurrentPanel();
    test()->actingAs($user);
    Filament::setTenant($tenant);
}

// --- Advisor panel resource list page ---

test('advisor panel bulk delete removes an account that only belongs to the current advisory', function () {
    actAsAdvisoryAdminOnAdvisorPanel($this->adminAdvisor, $this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableBulkAction('delete', [$this->soloAdvisor]);

    expect(advisorAccountIsDeleted($this->soloAdvisor))->toBeTrue();
});

test('advisor panel bulk delete does not remove an account that also belongs to another advisory', function () {
    actAsAdvisoryAdminOnAdvisorPanel($this->adminAdvisor, $this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableBulkAction('delete', [$this->soloAdvisor, $this->sharedAdvisor]);

    expect(advisorAccountIsDeleted($this->soloAdvisor))->toBeTrue()
        ->and(advisorAccountIsDeleted($this->sharedAdvisor))->toBeFalse();
});

test('advisor panel bulk delete does not remove the account of the acting user', function () {
    actAsAdvisoryAdminOnAdvisorPanel($this->adminAdvisor, $this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableBulkAction('delete', [$this->adminAdvisor, $this->soloAdvisor]);

    expect(advisorAccountIsDeleted($this->adminAdvisor))->toBeFalse()
        ->and(advisorAccountIsDeleted($this->soloAdvisor))->toBeTrue();
});

test('advisor panel bulk delete cannot reach an account outside the current advisory', function () {
    actAsAdvisoryAdminOnAdvisorPanel($this->adminAdvisor, $this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableBulkAction('delete', [$this->foreignAdvisor]);

    expect(advisorAccountIsDeleted($this->foreignAdvisor))->toBeFalse();
});

test('advisor panel bulk delete reports the accounts it skipped', function () {
    actAsAdvisoryAdminOnAdvisorPanel($this->adminAdvisor, $this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableBulkAction('delete', [$this->soloAdvisor, $this->sharedAdvisor])
        ->assertNotified(trans_choice('filament-actions::delete.multiple.notifications.deleted_partial.title', 1, [
            'count' => 1,
            'total' => 2,
        ]));
});

// --- Admin relation manager ---

test('admin relation manager bulk delete still removes the selected accounts', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => Role::Admin]));

    livewire(UsersRelationManager::class, [
        'ownerRecord' => $this->advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->callTableBulkAction('delete', [$this->soloAdvisor, $this->sharedAdvisor]);

    expect(advisorAccountIsDeleted($this->soloAdvisor))->toBeTrue()
        ->and(advisorAccountIsDeleted($this->sharedAdvisor))->toBeTrue();
});
