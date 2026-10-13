<?php

use App\Enums\Role;
use App\Filament\Municipality\Widgets\MunicipalityCalendarWidget;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Zaak;
use App\Models\Zaaktype;
use App\ValueObjects\ModelAttributes\ZaakReferenceData;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\Fakes\ZgwHttpFake;

use function Pest\Livewire\livewire;

covers(MunicipalityCalendarWidget::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-10-07 10:00:00');

    $this->municipality = Municipality::factory()->create();

    $user = User::factory()->create(['role' => Role::MunicipalityAdmin]);
    $this->municipality->users()->attach($user);
    $this->actingAs($user);

    Filament::setCurrentPanel(Filament::getPanel('municipality'));
    Filament::setTenant($this->municipality);
});

/**
 * The payload the calendar sends when it shows the month of October 2026,
 * with the browser two hours ahead of UTC.
 */
function octoberMonthDatesSet(): array
{
    return [
        'start' => '2026-09-27T22:00:00.000Z',
        'end' => '2026-11-07T23:00:00.000Z',
        'tzOffset' => 120,
        'view' => [
            'type' => 'dayGridMonth',
            'title' => 'October 2026',
            'currentStart' => '2026-09-30T22:00:00.000Z',
            'currentEnd' => '2026-10-31T23:00:00.000Z',
            'activeStart' => '2026-09-27T22:00:00.000Z',
            'activeEnd' => '2026-11-07T23:00:00.000Z',
        ],
    ];
}

test('geojson export with an empty end date reports a validation error instead of failing', function () {
    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->mountAction('export')
        ->fillForm([
            'start_date' => '2026-10-07',
            'end_date' => null,
        ])
        ->callAction('exportToGeojson')
        ->assertHasFormErrors(['end_date' => 'required'])
        ->assertNoFileDownloaded();
});

test('geojson export with an empty start date reports a validation error instead of failing', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->mountAction('export')
        ->fillForm([
            'start_date' => null,
            'end_date' => '2026-11-01',
        ])
        ->callAction('exportToGeojson')
        ->assertHasFormErrors(['start_date' => 'required'])
        ->assertNoFileDownloaded();
});

test('geojson export with an end date before the start date reports a validation error', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->mountAction('export')
        ->fillForm([
            'start_date' => '2026-11-01',
            'end_date' => '2026-10-01',
        ])
        ->callAction('exportToGeojson')
        ->assertHasFormErrors(['end_date' => 'after'])
        ->assertNoFileDownloaded();
});

test('geojson export with a valid period downloads a feature collection', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->mountAction('export')
        ->fillForm([
            'start_date' => '2026-10-01',
            'end_date' => '2026-11-01',
        ])
        ->callAction('exportToGeojson')
        ->assertHasNoFormErrors()
        ->assertFileDownloaded('export_evenementen_2026-10-07_10-00-00.geojson');
});

test('export modal in the month view uses the period shown by the calendar', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->call('onDatesSetJs', octoberMonthDatesSet())
        ->mountAction('export')
        ->assertActionDataSet([
            'start_date' => '2026-10-01',
            'end_date' => '2026-11-01',
        ]);
});

test('export modal in the list view defaults the end date to one month after the start', function () {
    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->assertSet('end', null)
        ->mountAction('export')
        ->assertActionDataSet([
            'start_date' => '2026-10-07',
            'end_date' => '2026-11-07',
        ]);
});

test('export modal defaults the end date after switching to the list view', function () {
    livewire(MunicipalityCalendarWidget::class)
        ->call('onDatesSetJs', octoberMonthDatesSet())
        ->callAction('toggleView')
        ->assertSet('viewMode', 'table')
        ->assertSet('end', null)
        ->mountAction('export')
        ->assertActionDataSet([
            'start_date' => '2026-10-07',
            'end_date' => '2026-11-07',
        ]);
});

test('export modal in the list view keeps the end date of the range filter', function () {
    livewire(MunicipalityCalendarWidget::class, ['viewtype' => 'table'])
        ->set('tableFilters.range.to', '20-10-2026')
        ->assertSet('end', fn ($end) => $end?->toDateString() === '2026-10-20')
        ->mountAction('export')
        ->assertActionDataSet([
            'start_date' => '2026-10-07',
            'end_date' => '2026-10-20',
        ]);
});

test('geojson export skips zaken without zgw data or without a geometry', function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $geometry = ['type' => 'Point', 'coordinates' => [5.85, 51.84]];
    $withGeometryUrl = ZgwHttpFake::fakeSingleZaak('with-geometry', ['zaakgeometrie' => $geometry]);
    $withoutGeometryUrl = ZgwHttpFake::fakeSingleZaak('without-geometry');
    Http::preventStrayRequests();

    $zaaktype = Zaaktype::factory()->create(['municipality_id' => $this->municipality->id]);
    $organisation = Organisation::factory()->create();
    $zaakStartingAt = fn (string $name, ?string $zgwZaakUrl, ?array $importedData = null): Zaak => Zaak::factory()->create([
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => $organisation->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'imported_data' => $importedData,
        'reference_data' => new ZaakReferenceData(
            registratiedatum: '2026-09-01T09:00:00+02:00',
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            start_evenement: '2026-10-10T10:00:00+02:00',
            eind_evenement: '2026-10-10T18:00:00+02:00',
            naam_evenement: $name,
        ),
    ]);

    // An imported zaak has no ZGW zaak, so it has no zgw data to read.
    $zaakStartingAt('Imported event', null, ['source' => 'import']);
    $zaakStartingAt('Event without geometry', $withoutGeometryUrl);
    $withGeometry = $zaakStartingAt('Event with geometry', $withGeometryUrl);

    $component = livewire(MunicipalityCalendarWidget::class)
        ->mountAction('export')
        ->fillForm([
            'start_date' => '2026-10-08',
            'end_date' => '2026-10-12',
        ])
        ->callAction('exportToGeojson')
        ->assertHasNoFormErrors()
        ->assertFileDownloaded('export_evenementen_2026-10-07_10-00-00.geojson');

    $geojson = json_decode(base64_decode(data_get($component->effects, 'download.content')), true);

    expect($geojson['type'])->toBe('FeatureCollection')
        ->and($geojson['features'])->toHaveCount(1)
        ->and($geojson['features'][0]['geometry'])->toBe($geometry)
        ->and($geojson['features'][0]['properties']['public_id'])->toBe($withGeometry->public_id);
});

test('geojson export limits the feature properties to the exported event fields', function () {
    Config::set('openzaak.url', ZgwHttpFake::$baseUrl.'/');

    $geometry = ['type' => 'Point', 'coordinates' => [5.85, 51.84]];
    $zgwZaakUrl = ZgwHttpFake::fakeSingleZaak('with-geometry', ['zaakgeometrie' => $geometry]);
    Http::preventStrayRequests();

    $zaaktype = Zaaktype::factory()->create([
        'municipality_id' => $this->municipality->id,
        'name' => 'Evenementenvergunning',
    ]);
    $organiser = User::factory()->create(['role' => Role::Organiser]);
    $zaak = Zaak::factory()->create([
        'public_id' => 'EV-2026-0001',
        'zaaktype_id' => $zaaktype->id,
        'organisation_id' => Organisation::factory()->create()->id,
        'organiser_user_id' => $organiser->id,
        'zgw_zaak_url' => $zgwZaakUrl,
        'data_object_url' => 'https://objects.example.com/api/v2/objects/1',
        'imported_data' => ['source' => 'import'],
        'form_state_snapshot' => ['values' => ['field' => 'value']],
        'reference_data' => new ZaakReferenceData(
            registratiedatum: '2026-09-01T09:00:00+02:00',
            status_name: 'Ontvangen',
            statustype_url: ZgwHttpFake::$baseUrl.'/catalogi/api/v1/statustypen/1',
            start_evenement: '2026-10-10T10:00:00+02:00',
            eind_evenement: '2026-10-10T18:00:00+02:00',
            risico_classificatie: 'B',
            naam_locatie_eveneme: 'Stadspark',
            naam_evenement: 'Synthetisch festival',
            organisator: 'Synthetische organisator',
        ),
    ]);

    $component = livewire(MunicipalityCalendarWidget::class)
        ->mountAction('export')
        ->fillForm([
            'start_date' => '2026-10-08',
            'end_date' => '2026-10-12',
        ])
        ->callAction('exportToGeojson')
        ->assertHasNoFormErrors();

    $geojson = json_decode(base64_decode(data_get($component->effects, 'download.content')), true);

    expect($geojson['features'])->toHaveCount(1)
        ->and($geojson['features'][0]['properties'])->not->toHaveKeys([
            'form_state_snapshot',
            'imported_data',
            'reference_data',
            'organiser_user_id',
            'organisation_id',
            'zgw_zaak_url',
            'data_object_url',
            'zaaktype_id',
        ])
        ->and(json_encode($geojson))->not->toContain('Synthetische organisator')
        ->and($geojson['features'][0]['properties'])->toBe([
            'public_id' => 'EV-2026-0001',
            'naam_evenement' => 'Synthetisch festival',
            'zaaktype' => 'Evenementenvergunning',
            'gemeente' => $this->municipality->name,
            'naam_locatie_evenement' => 'Stadspark',
            'start_evenement' => '2026-10-10T10:00:00+02:00',
            'eind_evenement' => '2026-10-10T18:00:00+02:00',
            'risico_classificatie' => 'B',
            'status_name' => 'Ontvangen',
            'status_color' => $zaak->status_color,
        ]);
});
