<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages\ListMunicipalityAdminUsers;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

covers(MunicipalityAdminUserResource::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create([
        'name' => 'Test Municipality',
    ]);

    $this->municipalityAdmin = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($this->municipalityAdmin);
});

/**
 * Create a user with the given role and attach it to the test municipality.
 */
function memberOfTestMunicipality(Municipality $municipality, Role $role): User
{
    $user = User::factory()->create(['role' => $role]);

    $municipality->users()->attach($user);

    return $user;
}

test('municipality admin can only see admins from their own municipality', function () {
    // Arrange - Create another municipality with its own admin
    $otherMunicipality = Municipality::factory()->create([
        'name' => 'Other Municipality',
    ]);

    $otherMunicipalityAdmin = User::factory()->create([
        'email' => 'other-municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $otherMunicipality->users()->attach($otherMunicipalityAdmin);

    // Create an admin for the current municipality
    $currentMunicipalityAdmin = User::factory()->create([
        'email' => 'current-municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($currentMunicipalityAdmin);

    $this->actingAs($this->municipalityAdmin);

    Filament::setTenant($this->municipality);
    Filament::bootCurrentPanel();

    // Act & Assert
    livewire(ListMunicipalityAdminUsers::class)
        ->assertCanSeeTableRecords([$this->municipalityAdmin, $currentMunicipalityAdmin])
        ->assertCanNotSeeTableRecords([$otherMunicipalityAdmin]);
});

test('municipality admin can change municipality admin role using select column', function () {
    // Arrange
    $targetUser = User::factory()->create([
        'role' => Role::MunicipalityAdmin,
    ]);
    $this->municipality->users()->attach($targetUser);

    $this->actingAs($this->municipalityAdmin);

    Filament::setTenant($this->municipality);

    // Act - Test changing role using Livewire's set method directly
    $component = livewire(ListMunicipalityAdminUsers::class)
        ->assertCanSeeTableRecords([$targetUser]);

    // Simulate the SelectColumn update by calling the column's wire:change event
    $component->call('updateTableColumnState', 'role', $targetUser->getKey(), Role::ReviewerMunicipalityAdmin->value);

    // Verify the role was actually changed in the database
    $targetUser->refresh();
    expect($targetUser->role)->toBe(Role::ReviewerMunicipalityAdmin);
});

// --- Authorization on the admin user resource ---

test('a municipality admin can open the admin user list page', function () {
    $this->actingAs($this->municipalityAdmin);

    Filament::setTenant($this->municipality);

    livewire(ListMunicipalityAdminUsers::class)->assertOk();
});

test('a reviewer municipality admin can open the admin user list page', function () {
    $this->actingAs(memberOfTestMunicipality($this->municipality, Role::ReviewerMunicipalityAdmin));

    Filament::setTenant($this->municipality);

    livewire(ListMunicipalityAdminUsers::class)->assertOk();
});

test('a reviewer of the same municipality is forbidden from the admin user list page', function () {
    $this->actingAs(memberOfTestMunicipality($this->municipality, Role::Reviewer));

    Filament::setTenant($this->municipality);

    livewire(ListMunicipalityAdminUsers::class)->assertForbidden();
});

test('a coordinator of the same municipality is forbidden from the admin user list page', function () {
    $this->actingAs(memberOfTestMunicipality($this->municipality, Role::Coordinator));

    Filament::setTenant($this->municipality);

    livewire(ListMunicipalityAdminUsers::class)->assertForbidden();
});

test('a reviewer cannot change a role through the inline role column', function () {
    $targetUser = memberOfTestMunicipality($this->municipality, Role::MunicipalityAdmin);

    $this->actingAs(memberOfTestMunicipality($this->municipality, Role::Reviewer));

    Filament::setTenant($this->municipality);

    // The page itself is no longer reachable for this role, so the call may
    // never get to the column; assert on the stored role either way.
    rescue(function () use ($targetUser) {
        livewire(ListMunicipalityAdminUsers::class)
            ->call('updateTableColumnState', 'role', $targetUser->getKey(), Role::ReviewerMunicipalityAdmin->value);
    }, report: false);

    expect($targetUser->refresh()->role)->toBe(Role::MunicipalityAdmin);
});

test('the inline role column refuses a role it does not offer', function () {
    $targetUser = memberOfTestMunicipality($this->municipality, Role::MunicipalityAdmin);

    $this->actingAs($this->municipalityAdmin);

    Filament::setTenant($this->municipality);

    livewire(ListMunicipalityAdminUsers::class)
        ->call('updateTableColumnState', 'role', $targetUser->getKey(), Role::Admin->value);

    expect($targetUser->refresh()->role)->toBe(Role::MunicipalityAdmin);
});
