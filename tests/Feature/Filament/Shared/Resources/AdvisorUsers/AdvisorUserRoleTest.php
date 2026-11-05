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
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

covers(AdvisorUserTable::class);

beforeEach(function () {
    $this->advisory = Advisory::factory()->create(['name' => 'Advisory A']);

    // The only admin of the advisory.
    $this->adminAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->adminAdvisor, ['role' => AdvisoryRole::Admin]);

    $this->memberAdvisor = User::factory()->create(['role' => Role::Advisor]);
    $this->advisory->users()->attach($this->memberAdvisor, ['role' => AdvisoryRole::Member]);
});

function advisoryRoleOf(Advisory $advisory, User $user): ?string
{
    return DB::table('advisory_user')
        ->where('advisory_id', $advisory->id)
        ->where('user_id', $user->id)
        ->value('role');
}

function actAsAdvisoryAdminOnAdvisorPanelForRoles(User $user, Advisory $tenant): void
{
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    test()->actingAs($user);
    Filament::setTenant($tenant);
}

function adminRelationManagerForRoles(Advisory $advisory): mixed
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    test()->actingAs(User::factory()->create(['role' => Role::Admin]));

    return livewire(UsersRelationManager::class, [
        'ownerRecord' => $advisory,
        'pageClass' => EditAdvisory::class,
    ]);
}

// --- Advisor panel resource list page ---

test('advisor panel role select cannot demote the last admin of the advisory', function () {
    actAsAdvisoryAdminOnAdvisorPanelForRoles($this->adminAdvisor, $this->advisory);

    livewire(ListAdvisorUsers::class)
        ->call('updateTableColumnState', 'pivot.role', (string) $this->adminAdvisor->getKey(), AdvisoryRole::Member->value);

    expect(advisoryRoleOf($this->advisory, $this->adminAdvisor))->toBe(AdvisoryRole::Admin->value)
        ->and($this->advisory->adminUsers()->count())->toBe(1);
});

test('advisor panel role select lets an admin step down when another admin remains', function () {
    $this->advisory->users()->updateExistingPivot($this->memberAdvisor->id, ['role' => AdvisoryRole::Admin]);

    actAsAdvisoryAdminOnAdvisorPanelForRoles($this->adminAdvisor, $this->advisory);

    livewire(ListAdvisorUsers::class)
        ->call('updateTableColumnState', 'pivot.role', (string) $this->adminAdvisor->getKey(), AdvisoryRole::Member->value);

    expect(advisoryRoleOf($this->advisory, $this->adminAdvisor))->toBe(AdvisoryRole::Member->value)
        ->and($this->advisory->adminUsers()->count())->toBe(1);
});

test('advisor panel role select still promotes a member while there is a single admin', function () {
    actAsAdvisoryAdminOnAdvisorPanelForRoles($this->adminAdvisor, $this->advisory);

    livewire(ListAdvisorUsers::class)
        ->call('updateTableColumnState', 'pivot.role', (string) $this->memberAdvisor->getKey(), AdvisoryRole::Admin->value);

    expect(advisoryRoleOf($this->advisory, $this->memberAdvisor))->toBe(AdvisoryRole::Admin->value);
});

// --- Admin relation manager ---

test('admin relation manager role select cannot demote the last admin of the advisory', function () {
    adminRelationManagerForRoles($this->advisory)
        ->call('updateTableColumnState', 'pivot.role', (string) $this->adminAdvisor->getKey(), AdvisoryRole::Member->value);

    expect(advisoryRoleOf($this->advisory, $this->adminAdvisor))->toBe(AdvisoryRole::Admin->value)
        ->and($this->advisory->adminUsers()->count())->toBe(1);
});

test('admin relation manager role select demotes an admin when another admin remains', function () {
    $this->advisory->users()->updateExistingPivot($this->memberAdvisor->id, ['role' => AdvisoryRole::Admin]);

    adminRelationManagerForRoles($this->advisory)
        ->call('updateTableColumnState', 'pivot.role', (string) $this->adminAdvisor->getKey(), AdvisoryRole::Member->value);

    expect(advisoryRoleOf($this->advisory, $this->adminAdvisor))->toBe(AdvisoryRole::Member->value)
        ->and($this->advisory->adminUsers()->count())->toBe(1);
});
