<?php

use App\Enums\Role;
use App\Filament\Municipality\Pages\Calendar as MunicipalityCalendarPage;
use App\Filament\Municipality\Widgets\MunicipalityCalendarWidget;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

covers(MunicipalityCalendarWidget::class, MunicipalityCalendarPage::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('municipality'));

    $this->municipality = Municipality::factory()->create(['name' => 'Test Municipality']);
    $this->otherMunicipality = Municipality::factory()->create(['name' => 'Other Municipality']);
    $this->organisation = Organisation::factory()->create();

    $this->municipalityAdmin = User::factory()->create([
        'email' => 'municipality-admin@example.com',
        'role' => Role::MunicipalityAdmin,
    ]);
    $this->municipality->users()->attach($this->municipalityAdmin);

    $this->actingAs($this->municipalityAdmin);
    Filament::setTenant($this->municipality);
});

test('municipality calendar widget renders successfully', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->assertOk();
});

test('municipality calendar widget mounts with municipality filter set to current tenant', function () {
    $component = livewire(MunicipalityCalendarWidget::class);

    expect($component->get('filters'))->toHaveKey('municipalities')
        ->and($component->get('filters')['municipalities'])->toContain($this->municipality->id);
});

test('municipality calendar widget does not pre-filter to other municipality', function () {
    $component = livewire(MunicipalityCalendarWidget::class);

    $filters = $component->get('filters');
    expect($filters['municipalities'] ?? [])->not->toContain($this->otherMunicipality->id);
});

test('municipality calendar widget starts in calendar view', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->assertSet('viewMode', 'calendar');
});

test('municipality calendar widget can toggle to table view', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table');
});

test('municipality calendar widget only shows zaken from current municipality in table view', function () {
    $ownZaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherZaaktype = Zaaktype::factory()->create(['municipality_id' => $this->otherMunicipality->id]);

    $ownZaak = Zaak::factory()->create([
        'zaaktype_id' => $ownZaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $otherZaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertCanSeeTableRecords([$ownZaak])
        ->assertCanNotSeeTableRecords([$otherZaak]);
});

test('municipality calendar widget filters are preserved when toggling between views', function () {
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    $initialFilters = [
        'municipalities' => [$this->municipality->id],
        'zaaktypes' => [$zaaktype->id],
    ];

    livewire(MunicipalityCalendarWidget::class)
        ->set('filters', $initialFilters)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertSet('filters', $initialFilters)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'calendar')
        ->assertSet('filters', $initialFilters);
});

test('municipality calendar widget can apply risico classificaties filter', function () {
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $zaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    // Applying the filter action should not cause errors — exercises getFilterSchema()
    livewire(MunicipalityCalendarWidget::class)
        ->callAction('filter', data: [
            'risico_classificaties' => ['A'],
        ])
        ->assertOk();
});

test('municipality calendar page renders and contains the widget', function () {
    livewire(MunicipalityCalendarPage::class)
        ->assertOk()
        ->assertSeeLivewire(MunicipalityCalendarWidget::class);
});

test('municipality calendar widget applies zaaktype filter in table view', function () {
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherZaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);

    $filteredZaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $otherZaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);

    livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->callAction('filter', data: [
            'zaaktypes' => [$zaaktype->id],
        ])
        ->assertCanSeeTableRecords([$filteredZaak])
        ->assertCanNotSeeTableRecords([$otherZaak]);
});

test('municipality calendar widget applies organisation filter in table view', function () {
    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $otherOrganisation = Organisation::factory()->create();

    $filteredZaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $this->organisation->id,
    ]);
    $otherZaak = Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $otherOrganisation->id,
    ]);

    livewire(MunicipalityCalendarWidget::class)
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->callAction('filter', data: [
            'organisations' => [$this->organisation->id],
        ])
        ->assertCanSeeTableRecords([$filteredZaak])
        ->assertCanNotSeeTableRecords([$otherZaak]);
});
