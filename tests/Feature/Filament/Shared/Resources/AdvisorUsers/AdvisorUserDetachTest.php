<?php

use App\Enums\AdvisoryRole;
use App\Enums\Role;
use App\Filament\Admin\Resources\AdvisoryResource\Pages\EditAdvisory;
use App\Filament\Admin\Resources\AdvisoryResource\RelationManagers\UsersRelationManager;
use App\Filament\Advisor\Clusters\Settings\Resources\AdvisorUsers\Pages\ListAdvisorUsers;
use App\Filament\Shared\Resources\AdvisorUsers\Tables\AdvisorUserTable;
use App\Models\Advisory;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

covers(AdvisorUserTable::class);

beforeEach(function () {
    $this->advisory = Advisory::factory()->create(['name' => 'Advisory A']);
    $this->otherAdvisory = Advisory::factory()->create(['name' => 'Advisory B']);

    $this->adminAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->adminAdvisor, ['role' => AdvisoryRole::Admin]);

    // Member of both advisories: the detach action is visible for this user.
    $this->sharedAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->sharedAdvisor, ['role' => AdvisoryRole::Member]);
    $this->otherAdvisory->users()->attach($this->sharedAdvisor, ['role' => AdvisoryRole::Member]);

    // Member of a single advisory: the detach action stays hidden for this user.
    $this->soloAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->soloAdvisor, ['role' => AdvisoryRole::Member]);
});

function advisoryPivotExists(int $advisoryId, int $userId): bool
{
    return DB::table('advisory_user')
        ->where('advisory_id', $advisoryId)
        ->where('user_id', $userId)
        ->exists();
}

// --- Advisor panel resource list page (no relationship context) ---

test('advisor panel detach removes the user from the current advisory only', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableAction('detach', $this->sharedAdvisor)
        ->assertHasNoTableActionErrors()
        ->assertNotified(__('filament-actions::detach.single.notifications.detached.title'));

    expect(advisoryPivotExists($this->advisory->id, $this->sharedAdvisor->id))->toBeFalse()
        ->and(advisoryPivotExists($this->otherAdvisory->id, $this->sharedAdvisor->id))->toBeTrue()
        ->and(User::find($this->sharedAdvisor->id))->not->toBeNull();
});

test('advisor panel detach is hidden for a user that belongs to a single advisory', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->assertTableActionHidden('detach', $this->soloAdvisor)
        ->assertTableActionVisible('detach', $this->sharedAdvisor);
});

test('advisor panel bulk detach removes the selected users from the current advisory only', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    $secondShared = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($secondShared, ['role' => AdvisoryRole::Member]);
    $this->otherAdvisory->users()->attach($secondShared, ['role' => AdvisoryRole::Member]);

    livewire(ListAdvisorUsers::class)
        ->callTableBulkAction('detach', [$this->sharedAdvisor, $secondShared])
        ->assertNotified(__('filament-actions::detach.multiple.notifications.detached.title'));

    expect(advisoryPivotExists($this->advisory->id, $this->sharedAdvisor->id))->toBeFalse()
        ->and(advisoryPivotExists($this->advisory->id, $secondShared->id))->toBeFalse()
        ->and(advisoryPivotExists($this->otherAdvisory->id, $this->sharedAdvisor->id))->toBeTrue()
        ->and(advisoryPivotExists($this->otherAdvisory->id, $secondShared->id))->toBeTrue()
        ->and(User::find($this->sharedAdvisor->id))->not->toBeNull()
        ->and(User::find($secondShared->id))->not->toBeNull();
});

test('advisor panel bulk detach skips users that belong to a single advisory', function () {
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    $this->actingAs($this->adminAdvisor);
    Filament::setTenant($this->advisory);

    livewire(ListAdvisorUsers::class)
        ->callTableBulkAction('detach', [$this->sharedAdvisor, $this->soloAdvisor])
        ->assertNotified(
            Notification::make()
                ->warning()
                ->title(__('resources/advisor_user.actions.detach.notifications.partially_detached.title'))
                ->body('<p>'.trans_choice('resources/advisor_user.actions.detach.skipped_last_advisory', 1).'</p>')
                ->persistent(),
        );

    expect(advisoryPivotExists($this->advisory->id, $this->sharedAdvisor->id))->toBeFalse()
        ->and(advisoryPivotExists($this->advisory->id, $this->soloAdvisor->id))->toBeTrue();
});

// --- Admin relation manager (relationship context) — regression ---

test('admin relation manager detach still removes the user from the advisory', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    livewire(UsersRelationManager::class, [
        'ownerRecord' => $this->advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->callTableAction('detach', $this->sharedAdvisor)
        ->assertHasNoTableActionErrors();

    expect(advisoryPivotExists($this->advisory->id, $this->sharedAdvisor->id))->toBeFalse()
        ->and(advisoryPivotExists($this->otherAdvisory->id, $this->sharedAdvisor->id))->toBeTrue()
        ->and(User::find($this->sharedAdvisor->id))->not->toBeNull();
});

test('admin relation manager bulk detach still removes the users from the advisory', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin);

    livewire(UsersRelationManager::class, [
        'ownerRecord' => $this->advisory,
        'pageClass' => EditAdvisory::class,
    ])
        ->callTableBulkAction('detach', [$this->sharedAdvisor]);

    expect(advisoryPivotExists($this->advisory->id, $this->sharedAdvisor->id))->toBeFalse()
        ->and(advisoryPivotExists($this->otherAdvisory->id, $this->sharedAdvisor->id))->toBeTrue();
});
