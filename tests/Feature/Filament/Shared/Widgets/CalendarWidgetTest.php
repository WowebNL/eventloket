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
use App\Enums\ZaakRelatieType;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\ZaakRelatie;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

covers(AdminCalendarWidget::class, AdminCalendarPage::class, MunicipalityCalendarPage::class, AdvisorCalendarPage::class, OrganiserCalendarPage::class);

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
        'reference_data' => new ZaakReferenceData(
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
        'reference_data' => new ZaakReferenceData(
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
        'reference_data' => new ZaakReferenceData(
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

test('admin calendar page import action is visible to admin user', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(AdminCalendarPage::class)
        ->assertActionExists('import')
        ->assertActionVisible('import');
});

test('admin calendar widget applies municipality filter in table view', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $otherMunicipality = Municipality::factory()->create(['name' => 'Other Municipality']);
    $otherZaaktype = Zaaktype::factory()->create(['municipality_id' => $otherMunicipality->id]);
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $otherZaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(AdminCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->callAction('filter', data: [
            'municipalities' => [$this->municipality->id],
        ])
        ->assertCanSeeTableRecords([$this->zaak])
        ->assertCanNotSeeTableRecords([$otherZaak]);
});

test('admin calendar widget applies zaaktype filter in table view', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $otherZaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $otherZaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(AdminCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->callAction('filter', data: [
            'zaaktypes' => [$this->zaaktype->id],
        ])
        ->assertCanSeeTableRecords([$this->zaak])
        ->assertCanNotSeeTableRecords([$otherZaak]);
});

test('admin calendar widget applies organisations filter in table view', function () {
    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'role' => Role::Admin,
    ]);

    $otherOrganisation = Organisation::factory()->create();
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $otherOrganisation->id,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    livewire(AdminCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->callAction('filter', data: [
            'organisations' => [$this->organisation->id],
        ])
        ->assertCanSeeTableRecords([$this->zaak])
        ->assertCanNotSeeTableRecords([$otherZaak]);
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
        'reference_data' => new ZaakReferenceData(
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

test('calendar widget table view opens the view modal when a row is clicked', function () {
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);

    $this->municipality->users()->attach($user);

    // A local zaak keeps the modal from calling out to Open Zaak.
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $this->zaaktype->id,
        'organisation_id' => $this->organisation->id,
        'zgw_zaak_url' => null,
    ]);

    $this->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);

    $component = livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertCanSeeTableRecords([$zaak]);

    // A row click mounts the table record action, so it has to resolve to the view action.
    expect($component->instance()->getTable()->getRecordAction($zaak))->toBe('view');

    // And that action must open a modal rather than navigate away.
    $viewAction = $component->instance()->getTable()->getAction('view')->record($zaak);
    expect($viewAction->getUrl())->toBeNull();

    $component->mountTableAction('view', $zaak)->assertOk();

    expect($component->instance()->getMountedAction()?->getName())->toBe('view');
});

/**
 * Issue #10: a vooraankondiging that has been replaced by a definitive
 * aanvraag disappears from the calendar; the aanvraag takes its place.
 * The zaken list itself is untouched by this filter.
 */
function omgezetteVooraankondigingScenario(object $context): array
{
    $vooraankondigingZaaktype = Zaaktype::factory()->create([
        'municipality_id' => $context->municipality->id,
        'name' => 'Vooraankondiging gemeente Test',
        'is_active' => true,
    ]);

    // Answer 6: the vooraankondiging is typically already closed when the
    // definitive aanvraag arrives — the filter must ignore its status.
    $vooraankondiging = Zaak::factory()->create([
        'zaaktype_id' => $vooraankondigingZaaktype->id,
        'organisation_id' => $context->organisation->id,
        'reference_data' => new ZaakReferenceData(
            start_evenement: now()->toString(),
            eind_evenement: now()->addDay()->toString(),
            registratiedatum: now()->toString(),
            status_name: 'Afgehandeld',
            statustype_url: 'https://example.com/statustype/1',
            resultaat: 'Afgehandeld',
            naam_evenement: 'Omgezette vooraankondiging',
        ),
    ]);

    $aanvraag = Zaak::factory()->create([
        'zaaktype_id' => $context->zaaktype->id,
        'organisation_id' => $context->organisation->id,
    ]);

    ZaakRelatie::create([
        'zaak_id' => $aanvraag->id,
        'gerelateerde_zaak_id' => $vooraankondiging->id,
        'type' => ZaakRelatieType::VervangtVooraankondiging,
    ]);

    return [$vooraankondiging, $aanvraag];
}

function actAsMunicipalityAdminOnCalendar(object $context): void
{
    $user = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);
    $context->municipality->users()->attach($user);

    test()->actingAs($user);
    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($context->municipality);
}

test('calendar hides a vooraankondiging that was replaced by a definitive aanvraag', function () {
    [$vooraankondiging, $aanvraag] = omgezetteVooraankondigingScenario($this);

    actAsMunicipalityAdminOnCalendar($this);

    livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertCanSeeTableRecords([$aanvraag])
        ->assertCanNotSeeTableRecords([$vooraankondiging]);
});

test('calendar shows the vooraankondiging again when its successor is soft-deleted', function () {
    [$vooraankondiging, $aanvraag] = omgezetteVooraankondigingScenario($this);

    // Soft delete: the FK cascade does not fire, so the relation row stays
    // behind — the filter itself must check the successor's deleted_at.
    $aanvraag->delete();

    actAsMunicipalityAdminOnCalendar($this);

    livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertCanSeeTableRecords([$vooraankondiging]);
});

test('the zaken list still shows a vooraankondiging that was replaced', function () {
    [$vooraankondiging, $aanvraag] = omgezetteVooraankondigingScenario($this);

    actAsMunicipalityAdminOnCalendar($this);

    livewire(\App\Filament\Shared\Resources\Zaken\Pages\ListZaken::class)
        ->filterTable('workingstock', 'all')
        ->assertCanSeeTableRecords([$vooraankondiging, $aanvraag]);
});
