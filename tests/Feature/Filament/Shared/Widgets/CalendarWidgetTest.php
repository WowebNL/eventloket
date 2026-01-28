<?php

use App\Enums\Role;
use App\Filament\Admin\Pages\Calendar as AdminCalendarPage;
use App\Filament\Admin\Widgets\AdminCalendarWidget;
use App\Filament\Advisor\Pages\Calendar as AdvisorCalendarPage;
use App\Filament\Advisor\Widgets\AdvisorCalendarWidget;
use App\Filament\Municipality\Pages\Calendar as MunicipalityCalendarPage;
use App\Filament\Municipality\Widgets\MunicipalityCalendarWidget;
use App\Filament\Organiser\Pages\Calendar as OrganiserCalendarPage;
use App\Filament\Organiser\Widgets\OrganiserCalendarWidget;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

covers(AdminCalendarWidget::class, MunicipalityCalendarPage::class, AdvisorCalendarPage::class, OrganiserCalendarPage::class);

beforeEach(function (): void {
    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);
    $this->organisation = Organisation::factory()->create();

    $this->zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
    ]);

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);
});

test('renders and shows municipality calendar page', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    // Give this user access to the municipality tenant (as in app logic)
    $this->municipality->users()->attach($user);

    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    livewire(MunicipalityCalendarPage::class)
        ->assertOk()
        ->assertSeeLivewire(MunicipalityCalendarWidget::class);
});

test('renders and shows admin calendar page', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(AdminCalendarPage::class)
        ->assertOk()
        ->assertSeeLivewire(AdminCalendarWidget::class);
});

test('renders and shows advisor calendar page', function () {
    $user = User::factory()->create([
        'email' => 'advisor@example.com',
        'role' => Role::Advisor,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('advisor'));

    livewire(AdvisorCalendarPage::class)
        ->assertOk()
        ->assertSeeLivewire(AdvisorCalendarWidget::class);
});

test('renders and shows organiser calendar page', function () {
    $user = User::factory()->create([
        'email' => 'organiser@example.com',
        'role' => Role::Organiser,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    livewire(OrganiserCalendarPage::class)
        ->assertOk()
        ->assertSeeLivewire(OrganiserCalendarWidget::class);
});

test('calendar widget can switch between calendar and table view', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($user);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    livewire(MunicipalityCalendarWidget::class)
        ->assertSet('viewMode', 'calendar')
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->callAction('toggleView')
        ->assertSet('viewMode', 'calendar');
});

test('calendar widget preserves filters when switching views', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($user);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    $filters = [
        'municipalities' => [$this->municipality->id],
        'search' => 'test event',
    ];

    livewire(MunicipalityCalendarWidget::class)
        ->assertSet('viewMode', 'calendar')
        ->set('filters', $filters)
        ->assertSet('filters', $filters)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertSet('filters', $filters)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'calendar')
        ->assertSet('filters', $filters);
});

test('calendar widget calls refreshRecords when filters change in calendar view', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($user);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    $component = livewire(MunicipalityCalendarWidget::class)
        ->assertSet('viewMode', 'calendar');

    // Apply filters via the filter action
    $component->callAction('filter', data: [
        'municipalities' => [$this->municipality->id],
    ]);

    // Verify filters were applied
    expect($component->get('filters'))->toHaveKey('municipalities');
});

test('calendar widget resets table when filters change in table view', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($user);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    $component = livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table');

    // Apply filters via the filter action
    $component->callAction('filter', data: [
        'municipalities' => [$this->municipality->id],
    ]);

    // Verify filters were applied
    expect($component->get('filters'))->toHaveKey('municipalities');
});

test('calendar view remains mounted when switching to table view', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($user);
    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    $component = livewire(MunicipalityCalendarWidget::class)
        ->assertSet('viewMode', 'calendar')
        ->assertSee('data-calendar') // Calendar should be rendered
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertSee('data-calendar'); // Calendar should still be in DOM (just hidden)
});
