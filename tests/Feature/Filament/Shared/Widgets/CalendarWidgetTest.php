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

test('calendar widget hides cases with hidden resultaat types', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($user);

    // Create zaaktype with hidden resultaat type
    $hiddenResultaatTypeUrl = 'https://example.com/resultaattype/ingetrokken';
    $zaaktypeWithHiddenResults = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'hidden_resultaat_types' => [$hiddenResultaatTypeUrl],
    ]);

    // Create a zaak with the hidden resultaat type
    $hiddenZaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktypeWithHiddenResults->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new \App\ValueObjects\ModelAttributes\ZaakReferenceData(
            start_evenement: now()->toString(),
            eind_evenement: now()->addDay()->toString(),
            registratiedatum: now()->toString(),
            status_name: 'Afgehandeld',
            statustype_url: 'https://example.com/statustype/1',
            resultaat: 'Ingetrokken',
            resultaattype_url: $hiddenResultaatTypeUrl,
            naam_evenement: 'Hidden Event',
        ),
    ]);

    // Create a zaak with a different resultaat type (not hidden)
    $visibleZaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktypeWithHiddenResults->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new \App\ValueObjects\ModelAttributes\ZaakReferenceData(
            start_evenement: now()->toString(),
            eind_evenement: now()->addDay()->toString(),
            registratiedatum: now()->toString(),
            status_name: 'Afgehandeld',
            statustype_url: 'https://example.com/statustype/1',
            resultaat: 'Toegekend',
            resultaattype_url: 'https://example.com/resultaattype/toegekend',
            naam_evenement: 'Visible Event',
        ),
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    // Check calendar view - should not see hidden zaak
    $component = livewire(MunicipalityCalendarWidget::class);

    // Switch to table view to check data
    $component->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertCanSeeTableRecords([$visibleZaak])
        ->assertCanNotSeeTableRecords([$hiddenZaak]);
});

test('calendar widget shows cases when zaaktype has no hidden resultaat types configured', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($user);

    // Create zaaktype without hidden resultaat types
    $zaaktypeWithoutHiddenResults = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'hidden_resultaat_types' => null,
    ]);

    // Create a zaak with any resultaat type
    $zaakWithResultaat = Zaak::factory()->create([
        'zaaktype_id' => $zaaktypeWithoutHiddenResults->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new \App\ValueObjects\ModelAttributes\ZaakReferenceData(
            start_evenement: now()->toString(),
            eind_evenement: now()->addDay()->toString(),
            registratiedatum: now()->toString(),
            status_name: 'Afgehandeld',
            statustype_url: 'https://example.com/statustype/1',
            resultaat: 'Ingetrokken',
            resultaattype_url: 'https://example.com/resultaattype/ingetrokken',
            naam_evenement: 'Visible Event',
        ),
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    // Switch to table view to check data
    $component = livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertCanSeeTableRecords([$zaakWithResultaat]);
});

test('calendar widget shows cases without resultaat even when zaaktype has hidden resultaat types', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($user);

    // Create zaaktype with hidden resultaat type
    $zaaktypeWithHiddenResults = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'hidden_resultaat_types' => ['https://example.com/resultaattype/ingetrokken'],
    ]);

    // Create a zaak without a resultaat (still in progress)
    $zaakWithoutResultaat = Zaak::factory()->create([
        'zaaktype_id' => $zaaktypeWithHiddenResults->id,
        'organisation_id' => $this->organisation->id,
        'reference_data' => new \App\ValueObjects\ModelAttributes\ZaakReferenceData(
            start_evenement: now()->toString(),
            eind_evenement: now()->addDay()->toString(),
            registratiedatum: now()->toString(),
            status_name: 'In behandeling',
            statustype_url: 'https://example.com/statustype/1',
            resultaat: null,
            resultaattype_url: null,
            naam_evenement: 'In Progress Event',
        ),
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    // Switch to table view to check data
    $component = livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertCanSeeTableRecords([$zaakWithoutResultaat]);
});
