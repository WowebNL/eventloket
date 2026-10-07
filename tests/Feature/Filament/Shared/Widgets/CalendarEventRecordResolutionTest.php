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
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Config;
use Livewire\Features\SupportTesting\Testable;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

covers(MunicipalityCalendarWidget::class, OrganiserCalendarWidget::class, AdvisorCalendarWidget::class);

const STORED_FORM_MARKER = 'synthetic-stored-form-marker';

beforeEach(function (): void {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');
    $ownZaakUrl = ZgwHttpFake::fakeSingleZaak('own');
    $otherZaakUrl = ZgwHttpFake::fakeSingleZaak('other');
    ZgwHttpFake::wildcardFake();

    $this->ownMunicipality = Municipality::factory()->create();
    $this->otherMunicipality = Municipality::factory()->create();
    $this->otherOrganisation = Organisation::factory()->create();

    $zaakIn = fn (Municipality $municipality, string $zgwZaakUrl): Zaak => Zaak::factory()->create([
        'zaaktype_id' => Zaaktype::factory()->create(['municipality_id' => $municipality->id])->id,
        'organisation_id' => $this->otherOrganisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'form_state_snapshot' => ['values' => ['field' => STORED_FORM_MARKER]],
    ]);

    $this->ownZaak = $zaakIn($this->ownMunicipality, $ownZaakUrl);
    $this->otherZaak = $zaakIn($this->otherMunicipality, $otherZaakUrl);
});

/**
 * Click a calendar item with the given extendedProps. Returns the JSON of the
 * component snapshot after the click, or null when the record was not found.
 */
function clickCalendarItem(Testable $component, string $model, string $key): ?string
{
    try {
        $component->call('onEventClickJs', [
            'event' => [
                'title' => 'Synthetic event',
                'start' => '2026-10-10T08:00:00.000Z',
                'end' => '2026-10-10T16:00:00.000Z',
                'allDay' => false,
                'styles' => [],
                'classNames' => [],
                'extendedProps' => ['model' => $model, 'key' => $key],
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
    } catch (ModelNotFoundException) {
        return null;
    }

    return json_encode($component->snapshot);
}

function actingAsMunicipalityAdmin(Municipality $municipality): Testable
{
    $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $municipality->users()->attach($user);
    test()->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($municipality);

    return livewire(MunicipalityCalendarWidget::class);
}

function actingAsOrganiser(): Testable
{
    $organisation = Organisation::factory()->create();
    $user = User::factory()->create(['role' => Role::Organiser]);
    $organisation->users()->attach($user, ['role' => OrganisationRole::Member->value]);
    test()->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($organisation);

    return livewire(OrganiserCalendarWidget::class);
}

function actingAsAdvisor(): Testable
{
    $advisory = Advisory::factory()->create(['can_view_any_zaak' => false]);
    $user = User::factory()->create(['role' => Role::Advisor]);
    $advisory->users()->attach($user, ['role' => AdvisoryRole::Member]);
    test()->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('advisor'));
    Filament::setTenant($advisory);

    return livewire(AdvisorCalendarWidget::class);
}

function actingAsRole(string $role, Municipality $ownMunicipality): Testable
{
    return match ($role) {
        'municipality admin' => actingAsMunicipalityAdmin($ownMunicipality),
        'organiser' => actingAsOrganiser(),
        'advisor' => actingAsAdvisor(),
    };
}

dataset('roles', [
    'municipality admin' => ['municipality admin'],
    'organiser' => ['organiser'],
    'advisor' => ['advisor'],
]);

test('calendar records are only resolved as events', function (string $role) {
    $snapshot = clickCalendarItem(actingAsRole($role, $this->ownMunicipality), Zaak::class, $this->otherZaak->id);

    expect($snapshot)->toBeNull();
})->with('roles');

test('the calendar click response only contains event fields', function (string $role) {
    $snapshot = clickCalendarItem(actingAsRole($role, $this->ownMunicipality), Zaak::class, $this->otherZaak->id);

    expect((string) $snapshot)->not->toContain(STORED_FORM_MARKER);
})->with('roles');

test('a calendar click only resolves event records', function () {
    $component = actingAsMunicipalityAdmin($this->ownMunicipality);

    expect(clickCalendarItem($component, User::class, (string) auth()->id()))->toBeNull();
});

test('clicking an event in the calendar opens it', function () {
    $component = actingAsMunicipalityAdmin($this->ownMunicipality);

    $snapshot = clickCalendarItem($component, Event::class, $this->ownZaak->id);

    expect($snapshot)->not->toBeNull()
        ->and($snapshot)->not->toContain(STORED_FORM_MARKER);

    $component->assertActionMounted('view');
    expect($component->instance()->getEventRecord())
        ->toBeInstanceOf(Event::class)
        ->getKey()->toBe($this->ownZaak->id);
});
