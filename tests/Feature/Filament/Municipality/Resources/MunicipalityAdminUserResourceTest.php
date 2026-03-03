<?php

use App\Enums\Role;
use App\Filament\Municipality\Clusters\Settings\Resources\MunicipalityAdminUserResource\Pages\ListMunicipalityAdminUsers;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

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
