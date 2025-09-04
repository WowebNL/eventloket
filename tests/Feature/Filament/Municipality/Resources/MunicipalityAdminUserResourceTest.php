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
