<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource\Pages\EditArchiveUser;
use App\Filament\Municipality\Clusters\Settings\Resources\ArchiveUserResource\Pages\ListArchiveUsers;
use App\Filament\Municipality\Resources\ReviewerUserResource\Pages\ListReviewerUsers;
use App\Models\Municipality;
use App\Models\User;
use App\Models\Users\ArchiveCoordinatorUser;
use App\Models\Users\ReviewerUser;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create();

    $this->municipalityAdmin = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($this->municipalityAdmin);

    $this->actingAs($this->municipalityAdmin);

    Filament::setTenant($this->municipality);
    Filament::bootCurrentPanel();
});

test('a municipality admin can assign an archive role to an existing reviewer', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);

    $component = livewire(ListReviewerUsers::class)
        ->assertCanSeeTableRecords([$reviewer]);

    $component->call('updateTableColumnState', 'role', $reviewer->getKey(), Role::ArchiveCoordinator->value);

    $reviewer->refresh();

    expect($reviewer->role)->toBe(Role::ArchiveCoordinator)
        ->and(User::find($reviewer->id))->toBeInstanceOf(ArchiveCoordinatorUser::class);

    // The user now shows up in the archive users list instead of the reviewer list
    livewire(ListArchiveUsers::class)
        ->assertCanSeeTableRecords([$reviewer]);

    livewire(ListReviewerUsers::class)
        ->assertCanNotSeeTableRecords([$reviewer]);
});

test('a municipality admin can switch an archive user back to a regular role', function () {
    $archiveUser = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($archiveUser);

    $component = livewire(ListArchiveUsers::class)
        ->assertCanSeeTableRecords([$archiveUser]);

    $component->call('updateTableColumnState', 'role', $archiveUser->getKey(), Role::Reviewer->value);

    $archiveUser->refresh();

    expect($archiveUser->role)->toBe(Role::Reviewer)
        ->and(User::find($archiveUser->id))->toBeInstanceOf(ReviewerUser::class);

    livewire(ListReviewerUsers::class)
        ->assertCanSeeTableRecords([$archiveUser]);
});

// --- Authorization on the archive user resource ---

test('a municipality admin can open the edit page of an archive user', function () {
    $archiveUser = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($archiveUser);

    livewire(EditArchiveUser::class, ['record' => $archiveUser->getRouteKey()])->assertOk();
});

test('a municipality admin of another municipality cannot edit an archive user', function () {
    $archiveUser = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($archiveUser);

    // Records created while a tenant is booted are pinned to that tenant, so
    // the second municipality is made outside the tenant context.
    Filament::setTenant(null);
    $otherMunicipality = Municipality::factory()->create();
    Filament::setTenant($this->municipality);

    $outsider = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $otherMunicipality->users()->attach($outsider);

    $this->actingAs($outsider);

    livewire(EditArchiveUser::class, ['record' => $archiveUser->getRouteKey()])->assertForbidden();
});

test('the edit form cannot hand out a municipality admin role either', function () {
    $archiveUser = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($archiveUser);

    livewire(EditArchiveUser::class, ['record' => $archiveUser->getRouteKey()])
        ->fillForm(['role' => Role::MunicipalityAdmin->value])
        ->call('save')
        ->assertHasFormErrors(['role']);

    expect($archiveUser->refresh()->role)->toBe(Role::ArchiveReviewer);
});

test('a reviewer of the same municipality is forbidden from the archive user list page', function () {
    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);

    $this->actingAs($reviewer);

    // The cluster navigation hides this screen, but navigation does not gate
    // the route: opening the url directly has to be refused as well.
    livewire(ListArchiveUsers::class)->assertForbidden();
});

test('an archive coordinator is forbidden from managing archive users', function () {
    $coordinator = User::factory()->create(['role' => Role::ArchiveCoordinator]);
    $this->municipality->users()->attach($coordinator);

    $this->actingAs($coordinator);

    livewire(ListArchiveUsers::class)->assertForbidden();
});

test('a reviewer cannot change a role through the inline role column', function () {
    $archiveUser = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($archiveUser);

    $reviewer = User::factory()->create(['role' => Role::Reviewer]);
    $this->municipality->users()->attach($reviewer);

    $this->actingAs($reviewer);

    // The page is already out of reach for this role, so the call may never
    // arrive at the column; assert on the stored role either way.
    rescue(function () use ($archiveUser) {
        livewire(ListArchiveUsers::class)
            ->call('updateTableColumnState', 'role', $archiveUser->getKey(), Role::ArchiveCoordinator->value);
    }, report: false);

    expect($archiveUser->refresh()->role)->toBe(Role::ArchiveReviewer);
});

test('the archive user screen cannot hand out the municipality admin roles', function (Role $role) {
    $archiveUser = User::factory()->create(['role' => Role::ArchiveReviewer]);
    $this->municipality->users()->attach($archiveUser);

    rescue(function () use ($archiveUser, $role) {
        livewire(ListArchiveUsers::class)
            ->call('updateTableColumnState', 'role', $archiveUser->getKey(), $role->value);
    }, report: false);

    expect($archiveUser->refresh()->role)->toBe(Role::ArchiveReviewer);
})->with([
    'municipality admin' => [Role::MunicipalityAdmin],
    'reviewer municipality admin' => [Role::ReviewerMunicipalityAdmin],
]);
