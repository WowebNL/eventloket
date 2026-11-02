<?php

declare(strict_types=1);

/**
 * A GPX file becomes the value of the route map field.
 *
 * That single move is the whole integration: the map renders the field's
 * state, so the route shows up and stays editable; the field's `required()`
 * rule is satisfied because the field is filled, so drawing by hand is no
 * longer necessary without the rule being touched; and the municipality check
 * already reads that same state, so the uploaded route counts towards the
 * municipalities the way a drawn one does.
 */

use App\Enums\Role;
use App\EventForm\Persistence\Draft;
use App\EventForm\Schema\Steps\LocatieVanHetEvenement2Step;
use App\EventForm\Services\ServiceFetcher;
use App\EventForm\State\FormState;
use App\EventForm\Support\LocationKinds;
use App\EventForm\Support\RouteGpxImport;
use App\EventForm\Validation\CompleteMapGeometry;
use App\Filament\Organiser\Pages\EventFormPage;
use App\Models\Municipality;
use App\Models\Organisation;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;

/**
 * The `afterValidation` gate of the location step, so it can be run with a
 * real page as `$livewire` without navigating the whole wizard.
 */
function routeGateCallback(): Closure
{
    $step = LocatieVanHetEvenement2Step::make();
    $property = new ReflectionProperty($step, 'afterValidation');
    $property->setAccessible(true);

    /** @var Closure $callback */
    $callback = $property->getValue($step);

    return $callback;
}

/**
 * A GPX document whose track runs between the given longitudes at latitude 0.
 */
function gpxCrossing(float $fromLongitude, float $toLongitude, int $points = 200): string
{
    $trackPoints = '';

    for ($index = 0; $index < $points; $index++) {
        $trackPoints .= sprintf(
            '<trkpt lat="0.0" lon="%.6f"/>',
            $fromLongitude + ($toLongitude - $fromLongitude) * $index / ($points - 1),
        );
    }

    return '<?xml version="1.0" encoding="UTF-8"?>'
        .'<gpx version="1.1" xmlns="http://www.topografix.com/GPX/1/1">'
        .'<trk><trkseg>'.$trackPoints.'</trkseg></trk>'
        .'</gpx>';
}

/**
 * A freshly uploaded file, as it exists while the wizard is still open: a
 * temporary upload rather than a stored document.
 */
function temporaryGpxUpload(string $contents, string $name = 'route.gpx'): TemporaryUploadedFile
{
    FileUploadConfiguration::storage()->put('livewire-tmp/'.$name, $contents);

    return TemporaryUploadedFile::createFromLivewire($name);
}

beforeEach(function () {
    Http::fake();

    $this->user = User::factory()->create(['role' => Role::Organiser]);
    $this->organisation = Organisation::factory()->create();
    $this->user->organisations()->attach($this->organisation->id, ['role' => 'admin']);

    $this->actingAs($this->user);
    Filament::setCurrentPanel(Filament::getPanel('organiser'));
    Filament::setTenant($this->organisation);

    $this->draft = Draft::create([
        'user_id' => $this->user->id,
        'organisation_id' => $this->organisation->id,
        'state' => FormState::empty()->toSnapshot(),
        'current_step_key' => null,
    ]);

    // Two neighbouring municipalities: [0,2] and [2,4] in longitude.
    Municipality::factory()->create([
        'brk_identification' => 'GM0001',
        'name' => 'StartGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[0,-1],[2,-1],[2,1],[0,1],[0,-1]]]]}',
    ]);
    Municipality::factory()->create([
        'brk_identification' => 'GM0002',
        'name' => 'BuurGemeente',
        'geometry' => '{"type":"MultiPolygon","coordinates":[[[[2,-1],[4,-1],[4,1],[2,1],[2,-1]]]]}',
    ]);
});

test('an uploaded GPX becomes the value of the route map field', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->set('data.waarVindtHetEvenementPlaats', ['route'])
        ->set('data.gpxBestandVanDeRoute', ['upload-1' => temporaryGpxUpload(gpxCrossing(0.5, 1.5))]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    $routes = $page->state()->get(LocationKinds::FIELD_BY_KIND[LocationKinds::ROUTE]);

    expect($routes)->toBeArray()->not->toBeEmpty();

    $geojson = $routes['geojson'];

    expect($geojson['type'])->toBe('FeatureCollection')
        ->and($geojson['features'])->toHaveCount(1)
        ->and($geojson['features'][0]['geometry']['type'])->toBe('LineString')
        ->and($geojson['features'][0]['geometry']['coordinates'])->toHaveCount(200);
});

test('the map field is filled while the file is still a temporary upload', function () {
    // The wizard saves uploads on submit, so during the wizard the file only
    // exists as a temporary upload. Not reading it there is the way this
    // feature would silently do nothing.
    $upload = temporaryGpxUpload(gpxCrossing(0.5, 1.5));

    expect($upload->getRealPath())->toContain('livewire-tmp');

    $mapState = RouteGpxImport::mapStateFrom($upload);

    expect($mapState)->not->toBeNull()
        ->and($mapState['geojson']['features'][0]['geometry']['coordinates'])->toHaveCount(200)
        ->and($mapState['lat'])->toBe(0.0)
        ->and($mapState['lng'])->toBe(1.0);
});

test('the uploaded route satisfies the rules that enforce drawing', function () {
    // The drawing obligation lapses because the field is filled, not because a
    // rule was relaxed. The rules on the route field are run here against the
    // state the upload produced: `required()` sees a filled field, and
    // `CompleteMapGeometry` sees a finished line.
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->set('data.waarVindtHetEvenementPlaats', ['route'])
        ->set('data.gpxBestandVanDeRoute', ['upload-1' => temporaryGpxUpload(gpxCrossing(0.5, 1.5))]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    $value = $page->state()->get(RouteGpxImport::ROUTE_FIELD);

    $failures = [];
    $fail = function (string $message) use (&$failures): void {
        $failures[] = $message;
    };

    (new CompleteMapGeometry)->validate(RouteGpxImport::ROUTE_FIELD, $value, $fail);

    expect($failures)->toBe([])
        ->and($value)->toBeArray()->not->toBeEmpty()
        ->and($value['geojson']['features'][0]['geometry']['type'])->toBe('LineString');
});

test('the location step can be passed without anything being drawn', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->set('data.waarVindtHetEvenementPlaats', ['route'])
        ->set('data.gpxBestandVanDeRoute', ['upload-1' => temporaryGpxUpload(gpxCrossing(0.5, 1.5))]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    // Nothing was drawn: the only geometry in the route field came from the
    // uploaded file.
    $gate = routeGateCallback();

    $gate($page);

    expect($page->state()->get('evenementInGemeente.brk_identification'))->toBe('GM0001');
});

test('a GPX crossing into a second municipality puts it in the list', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->set('data.waarVindtHetEvenementPlaats', ['route'])
        ->set('data.gpxBestandVanDeRoute', ['upload-1' => temporaryGpxUpload(gpxCrossing(1.5, 2.5))]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    $found = collect($page->state()->get('inGemeentenResponse.all.items'))
        ->pluck('brk_identification')
        ->sort()
        ->values()
        ->all();

    expect($found)->toBe(['GM0001', 'GM0002']);
});

test('the gate halts on a GPX that reaches two municipalities until one is chosen', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->set('data.waarVindtHetEvenementPlaats', ['route'])
        ->set('data.gpxBestandVanDeRoute', ['upload-1' => temporaryGpxUpload(gpxCrossing(1.5, 2.5))]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    // The halt has to be the one about choosing between municipalities, so
    // first establish that the uploaded route actually produced two.
    expect($page->state()->get('inGemeentenResponse.all.items'))->toHaveCount(2);

    $gate = routeGateCallback();

    expect(fn () => $gate($page))->toThrow(Halt::class);

    expect($page->state()->get('evenementInGemeente'))->toBeEmpty();
});

test('unticking route takes the uploaded route out of the determination', function () {
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['gebouw'],
        'routesOpKaart' => RouteGpxImport::mapStateFrom(temporaryGpxUpload(gpxCrossing(1.5, 2.5))),
    ]);

    app(ServiceFetcher::class)->fetch('inGemeentenResponse', $state);

    expect($state->get('inGemeentenResponse'))->toBeNull()
        ->and(LocationKinds::valueFor($state, LocationKinds::ROUTE))->toBeNull();
});

test('a route reaching outside the served municipalities uses the existing warning', function () {
    // Beyond longitude 4 there is no municipality, so the check reports the
    // route as not within. That is the same signal a drawn route produces, and
    // it drives the `NotWithin` message that already exists on the step. No
    // second, nearly identical warning is introduced next to it.
    $state = new FormState(values: [
        'waarVindtHetEvenementPlaats' => ['route'],
        'routesOpKaart' => RouteGpxImport::mapStateFrom(temporaryGpxUpload(gpxCrossing(3.5, 6.0))),
    ]);

    app(ServiceFetcher::class)->fetch('inGemeentenResponse', $state);

    expect($state->get('inGemeentenResponse.all.within'))->toBeFalse()
        ->and($state->isFieldHidden('NotWithin'))->toBeFalse();
});

test('an upload replaces a route that was already on the map', function () {
    // The GPX takes the place of drawing by hand, so a route that was drawn
    // before the upload is what gets replaced, rather than the uploaded route
    // arriving as a second route beside it.
    $drawn = [
        'lat' => 0.0,
        'lng' => 0.3,
        'geojson' => [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => new stdClass,
                'geometry' => ['type' => 'LineString', 'coordinates' => [[0.2, 0.0], [0.4, 0.0]]],
            ]],
        ],
    ];

    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->set('data.waarVindtHetEvenementPlaats', ['route'])
        ->set('data.routesOpKaart', $drawn)
        ->set('data.gpxBestandVanDeRoute', ['upload-1' => temporaryGpxUpload(gpxCrossing(0.5, 1.5))]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    $routes = $page->state()->get(RouteGpxImport::ROUTE_FIELD);

    expect($routes['geojson']['features'])->toHaveCount(1)
        ->and($routes['geojson']['features'][0]['geometry']['coordinates'])->toHaveCount(200)
        ->and($routes['geojson']['features'][0]['geometry']['coordinates'][0])->toBe([0.5, 0.0]);
});

test('removing the file leaves the route on the map', function () {
    // Once the route is on the map it is the organiser's route, and it may
    // have been edited there since. Detaching the attachment does not take it
    // away again.
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->set('data.waarVindtHetEvenementPlaats', ['route'])
        ->set('data.gpxBestandVanDeRoute', ['upload-1' => temporaryGpxUpload(gpxCrossing(0.5, 1.5))]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    $afterUpload = $page->state()->get(RouteGpxImport::ROUTE_FIELD);

    expect($afterUpload['geojson']['features'][0]['geometry']['coordinates'])->toHaveCount(200);

    $component->set('data.gpxBestandVanDeRoute', []);

    /** @var EventFormPage $page */
    $page = $component->instance();

    // Compared by value: a whole-number coordinate comes back from the state
    // snapshot as an integer rather than a float, which says nothing about
    // whether the route survived.
    expect($page->state()->get(RouteGpxImport::ROUTE_FIELD))->toEqual($afterUpload);
});

test('a file that holds no route leaves the map field alone', function () {
    $component = Livewire::test(EventFormPage::class, ['draft' => $this->draft->id])
        ->set('data.waarVindtHetEvenementPlaats', ['route'])
        ->set('data.gpxBestandVanDeRoute', ['upload-1' => temporaryGpxUpload(
            '<?xml version="1.0" encoding="UTF-8"?><note><body>Not a route</body></note>',
            'not-a-route.gpx',
        )]);

    /** @var EventFormPage $page */
    $page = $component->instance();

    $routes = $page->state()->get(LocationKinds::FIELD_BY_KIND[LocationKinds::ROUTE]);

    expect(is_array($routes) ? ($routes['geojson'] ?? null) : null)->toBeNull();
});
