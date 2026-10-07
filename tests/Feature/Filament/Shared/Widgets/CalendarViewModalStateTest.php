<?php

use App\Enums\AdvisoryRole;
use App\Enums\OrganisationRole;
use App\Enums\Role;
use App\Filament\Advisor\Widgets\AdvisorCalendarWidget;
use App\Filament\Municipality\Widgets\MunicipalityCalendarWidget;
use App\Filament\Organiser\Widgets\OrganiserCalendarWidget;
use App\Models\Advisory;
use App\Models\Event;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Livewire\Features\SupportTesting\Testable;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

covers(MunicipalityCalendarWidget::class, OrganiserCalendarWidget::class, AdvisorCalendarWidget::class);

const ORGANISER_MARKER = 'synthetic-organiser-marker';
const IMPORTED_MARKER = 'synthetic-imported-marker';

beforeEach(function (): void {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('view-modal');
    ZgwHttpFake::wildcardFake();

    $this->municipality = Municipality::factory()->create();

    $this->zaak = Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create(['municipality_id' => $this->municipality->id])->id,
        'organisation_id' => Organisation::factory()->create()->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'imported_data' => ['source' => IMPORTED_MARKER],
        'reference_data' => new ZaakReferenceData(
            registratiedatum: '2026-09-01T09:00:00+02:00',
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            start_evenement: '2026-10-10T10:00:00+02:00',
            eind_evenement: '2026-10-10T18:00:00+02:00',
            naam_evenement: 'Synthetic event',
            organisator: ORGANISER_MARKER,
        ),
    ]);
});

function viewModalAs(string $role, Municipality $municipality): Testable
{
    $user = User::factory()->create(['role' => match ($role) {
        'municipality admin' => Role::MunicipalityAdmin,
        'organiser' => Role::Organiser,
        'advisor' => Role::Advisor,
    }]);
    test()->actingAs($user);

    if ($role === 'municipality admin') {
        $municipality->users()->attach($user);
        Filament::setCurrentPanel(Filament::getPanel('municipality'));
        Filament::setTenant($municipality);

        return livewire(MunicipalityCalendarWidget::class);
    }

    if ($role === 'organiser') {
        $organisation = Organisation::factory()->create();
        $organisation->users()->attach($user, ['role' => OrganisationRole::Member->value]);
        Filament::setCurrentPanel(Filament::getPanel('organiser'));
        Filament::setTenant($organisation);

        return livewire(OrganiserCalendarWidget::class);
    }

    $advisory = Advisory::factory()->create(['can_view_any_zaak' => false]);
    $advisory->users()->attach($user, ['role' => AdvisoryRole::Member]);
    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    Filament::setTenant($advisory);

    return livewire(AdvisorCalendarWidget::class);
}

function openEventInCalendar(Testable $component, string $key): Testable
{
    return $component->call('onEventClickJs', [
        'event' => [
            'title' => 'Synthetic event',
            'start' => '2026-10-10T08:00:00.000Z',
            'end' => '2026-10-10T16:00:00.000Z',
            'allDay' => false,
            'styles' => [],
            'classNames' => [],
            'extendedProps' => ['model' => Event::class, 'key' => $key],
            'display' => 'auto',
            'resourceIds' => [],
        ],
        'view' => [
            'type' => 'dayGridMonth',
            'title' => 'October 2026',
            'currentStart' => '2026-09-30T22:00:00.000Z',
            'currentEnd' => '2026-10-31T23:00:00.000Z',
            'activeStart' => '2026-09-27T22:00:00.000Z',
            'activeEnd' => '2026-11-07T23:00:00.000Z',
        ],
        'tzOffset' => 120,
    ]);
}

dataset('roles', [
    'municipality admin' => ['municipality admin'],
    'organiser' => ['organiser'],
    'advisor' => ['advisor'],
]);

test('the calendar view modal only receives the fields it displays', function (string $role) {
    $component = openEventInCalendar(viewModalAs($role, $this->municipality), $this->zaak->id)
        ->assertActionMounted('view');

    expect(json_encode($component->snapshot))
        ->not->toContain(ORGANISER_MARKER)
        ->not->toContain(IMPORTED_MARKER)
        ->and($component->instance()->mountedActions[0]['data'] ?? [])->toBe([]);
})->with('roles');

test('the list view modal only receives the fields it displays', function (string $role) {
    $component = viewModalAs($role, $this->municipality)
        ->set('viewMode', 'table')
        ->mountAction(TestAction::make('view')->table($this->zaak))
        ->assertActionMounted(TestAction::make('view')->table($this->zaak));

    expect(json_encode($component->snapshot))
        ->not->toContain(ORGANISER_MARKER)
        ->not->toContain(IMPORTED_MARKER)
        ->and($component->instance()->mountedActions[0]['data'] ?? [])->toBe([]);
})->with('roles');

test('the calendar view modal still shows the organiser to a municipality admin', function () {
    openEventInCalendar(viewModalAs('municipality admin', $this->municipality), $this->zaak->id)
        ->assertActionMounted('view')
        ->assertMountedActionModalSee(ORGANISER_MARKER);
});

test('the calendar view modal still hides the organiser from an organiser', function () {
    openEventInCalendar(viewModalAs('organiser', $this->municipality), $this->zaak->id)
        ->assertActionMounted('view')
        ->assertMountedActionModalDontSee(ORGANISER_MARKER);
});
