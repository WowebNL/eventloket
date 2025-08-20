<?php

use App\Enums\OrganisationRole;
use App\Enums\OrganisationType;
use App\Enums\Role;
use App\Filament\Organiser\Clusters\Settings;
use App\Filament\Organiser\Clusters\Settings\Resources\UserResource\Pages\ListUsers;
use App\Filament\Organiser\Pages\Dashboard;
use App\Filament\Organiser\Widgets\Intro;
use App\Models\Organisation;
use App\Models\User;
use App\Settings\OrganiserPanelSettings;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('organiser'));

    $this->businessOrganisation = Organisation::factory(['type' => OrganisationType::Business])->create();
    $this->personalOrganisation = Organisation::factory(['type' => OrganisationType::Personal])->create();

    $this->businessAdminUser = User::factory(['role' => Role::Organiser])->create();
    $this->businessAdminUser->organisations()->attach($this->businessOrganisation, ['role' => OrganisationRole::Admin->value]);

    $this->businessMemberUser = User::factory(['role' => Role::Organiser])->create();
    $this->businessMemberUser->organisations()->attach($this->businessOrganisation, ['role' => OrganisationRole::Member->value]);

    $this->personalAdminUser = User::factory(['role' => Role::Organiser])->create();
    $this->personalAdminUser->organisations()->attach($this->personalOrganisation, ['role' => OrganisationRole::Admin->value]);

    Config::set('app.require_2fa', false);
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
        ->assertTableColumnExists('organisations.role');

});

test('Organiser panel custom dashboard is shown', function () {
    $this->actingAs($this->businessAdminUser);

    livewire(Dashboard::class)->assertSee($this->businessAdminUser->name); // name in custom title
});

test('Intro widget has intro text', function () {
    OrganiserPanelSettings::fake([
        'intro' => 'Test Intro',
    ]);

    livewire(Intro::class)->assertSee('Test Intro');
});

test('Widgets are rendered in organisation dashboard', function () {
    $this->actingAs($this->businessAdminUser);

    $this->get(route('filament.organiser.pages.dashboard', ['tenant' => $this->businessOrganisation->id]))
        ->assertSee('app.filament.organiser.widgets.intro') // intro widget
        ->assertSee('app.filament.organiser.widgets.shortlink'); // shortlink widget

});
