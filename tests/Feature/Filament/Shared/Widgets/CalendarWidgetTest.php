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
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
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
        'reference_data' => new ZaakReferenceData(
            'A',
            now(),
            now()->addDay(),
            now(),
            'Ontvangen',
            'Test event'
        ),
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
