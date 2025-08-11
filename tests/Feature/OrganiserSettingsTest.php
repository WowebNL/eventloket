<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Filament\Organiser\Clusters\Settings;
use App\Filament\Organiser\Clusters\Settings\Resources\UserResource\Pages\ListUsers;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    $this->businessOrganisation = Organisation::factory(['type' => OrganisationType::Business])->create();
    $this->personalOrganisation = Organisation::factory(['type' => OrganisationType::Personal])->create();

    $this->businessAdminUser = User::factory()->create();
    $this->businessAdminUser->organisations()->attach($this->businessOrganisation, ['role' => OrganisationRole::Admin->value]);

    $this->businessMemberUser = User::factory()->create();
    $this->businessMemberUser->organisations()->attach($this->businessOrganisation, ['role' => OrganisationRole::Member->value]);

    $this->personalAdminUser = User::factory()->create();
    $this->personalAdminUser->organisations()->attach($this->personalOrganisation, ['role' => OrganisationRole::Admin->value]);
});

test('Organisation admin can access organisation settings', function () {
    $this->actingAs($this->businessAdminUser);
    Filament::setTenant($this->businessOrganisation);

    expect(Settings::canAccess())->toBeTrue();
});

test('Organisation member cannot access organisation settings', function () {
    $this->actingAs($this->businessMemberUser);
    Filament::setTenant($this->businessOrganisation);

    expect(Settings::canAccess())->toBeFalse();
});

test('Personal organisation cannot access settings', function () {
    $this->actingAs($this->personalAdminUser);
    Filament::setTenant($this->personalOrganisation);

    expect(Settings::canAccess())->toBeFalse();
});

test('Organisation admin can change other organisation members role', function () {
    $this->actingAs($this->businessAdminUser);
    $memberUser = User::factory()->create();
    $this->businessOrganisation->users()->attach($memberUser, ['role' => OrganisationRole::Member->value]);
    Filament::setTenant($this->businessOrganisation);

    livewire(ListUsers::class)
        ->assertTableColumnExists('organisations.role')
        ->assertTableSelectColumnHasOptions('organisations.role', OrganisationRole::getOptions(), $memberUser);

});
