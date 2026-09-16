<?php

declare(strict_types=1);

/**
 * Every map surface in this application draws the same basemap, configured in
 * exactly one place, and every one of them shows its attribution.
 *
 * Three things are proven here, because each of them has its own way of going
 * wrong and a broken one is not obvious from looking at a map:
 *
 *   1. The tile URL and the attribution come from config/maps.php, and the
 *      form fields, the read-only infolist entry and the PDF renderer all read
 *      that one value. A hard-coded URL anywhere else would drift silently.
 *   2. No map field bypasses the component that applies them. The upstream
 *      field defaults to a different tile service and ships no attribution at
 *      all, so a field constructed straight from it renders unattributed.
 *   3. The Content-Security-Policy allows the host that the tile URL points
 *      at. Without that the browser blocks every tile while the application
 *      code is perfectly correct, which is the failure mode that looks like
 *      the change simply did not work.
 */

use App\EventForm\Schema\EventFormSchema;
use App\EventForm\Schema\Steps\LocatieVanHetEvenement2Step;
use App\Filament\Forms\Components\BasemapMap;
use App\Filament\Infolists\GeoJsonMapEntry;
use App\Support\Maps\Basemap;
use Dotswan\MapPicker\Fields\Map;
use Illuminate\Support\Facades\Config;

/**
 * Read the tile layer configuration a map field will hand to Leaflet.
 *
 * The upstream field keeps it in a private property and only exposes it
 * through getMapConfig(), which needs a mounted schema container this test
 * does not have. Reading the property is the same array that method encodes.
 *
 * @return array<string, mixed>
 */
function mapFieldTileConfig(Map $field): array
{
    $property = (new ReflectionClass(Map::class))->getProperty('mapConfig');
    $property->setAccessible(true);

    $config = $property->getValue($field);

    return is_array($config) ? $config : [];
}

/**
 * Collect every map field in a schema tree.
 *
 * Walks the child components by reflection, because Filament's own traversal
 * wants a mounted container. Repeater rows are included, which matters: those
 * are the reason one report can contain an unbounded number of maps.
 *
 * @param  list<Map>  $found
 */
function collectMapFields(object $component, array &$found, int $depth = 0): void
{
    if ($depth > 12) {
        return;
    }

    if ($component instanceof Map) {
        $found[] = $component;
    }

    $reflection = new ReflectionObject($component);

    foreach (['childComponents', 'schema'] as $name) {
        if (! $reflection->hasProperty($name)) {
            continue;
        }

        $property = $reflection->getProperty($name);
        $property->setAccessible(true);

        if (! $property->isInitialized($component)) {
            continue;
        }

        $value = $property->getValue($component);
        $children = is_array($value) ? $value : [$value];

        array_walk_recursive($children, function ($item) use (&$found, $depth) {
            if (is_object($item) && ! $item instanceof Closure) {
                collectMapFields($item, $found, $depth + 1);
            }
        });
    }
}

test('the basemap reads its tile URL and attribution from config', function () {
    Config::set('maps.tiles.url', 'https://tiles.example.test/{z}/{x}/{y}.png');
    Config::set('maps.tiles.attribution', 'Example attribution');

    expect(Basemap::tilesUrl())->toBe('https://tiles.example.test/{z}/{x}/{y}.png')
        ->and(Basemap::attribution())->toBe('Example attribution');
});

test('the configured attribution is not empty', function () {
    // The tile licence requires the source to be credited, so an empty value
    // is a policy breach and not merely a cosmetic omission.
    expect(trim(Basemap::attribution()))->not->toBe('');
});

test('a tile URL is resolved into a concrete tile request', function () {
    Config::set('maps.tiles.url', 'https://tiles.example.test/{z}/{x}/{y}.png');

    expect(Basemap::tileUrlFor(12, 2103, 1345))
        ->toBe('https://tiles.example.test/12/2103/1345.png');
});

test('the CSP origin is derived from the configured tile URL', function () {
    Config::set('maps.tiles.url', 'https://tiles.example.test/wmts/{z}/{x}/{y}.png');
    expect(Basemap::origin())->toBe('https://tiles.example.test');

    Config::set('maps.tiles.url', 'https://tiles.example.test:8443/wmts/{z}/{x}/{y}.png');
    expect(Basemap::origin())->toBe('https://tiles.example.test:8443');
});

test('an unusable tile URL widens the CSP with nothing', function () {
    Config::set('maps.tiles.url', 'not-a-url');

    expect(Basemap::origin())->toBe('');
});

test('a map field carries the configured tile URL and attribution', function () {
    Config::set('maps.tiles.url', 'https://tiles.example.test/{z}/{x}/{y}.png');
    Config::set('maps.tiles.attribution', 'Example attribution');

    $config = mapFieldTileConfig(BasemapMap::make('geometry'));

    expect($config['tilesUrl'])->toBe('https://tiles.example.test/{z}/{x}/{y}.png')
        ->and($config['attribution'])->toBe('Example attribution');
});

test('a map field matches the tile geometry of the configured service', function () {
    $config = mapFieldTileConfig(BasemapMap::make('geometry'));

    // The tile service publishes 256px tiles on the standard Web Mercator
    // quad. A mismatched tile size or zoom offset still renders, but shows a
    // map from the wrong zoom level scaled up, which reads as a blurred map
    // rather than as a configuration error.
    expect($config['tileSize'])->toBe(config('maps.tiles.tile_size'))
        ->and($config['zoomOffset'])->toBe(config('maps.tiles.zoom_offset'))
        ->and($config['detectRetina'])->toBe(config('maps.tiles.detect_retina'))
        ->and($config['maxZoom'])->toBe(config('maps.tiles.max_zoom'));
});

test('every map field in the event form goes through the basemap component', function () {
    $found = [];

    foreach (EventFormSchema::steps() as $step) {
        collectMapFields($step, $found);
    }
    collectMapFields(LocatieVanHetEvenement2Step::make(), $found);

    expect($found)->not->toBeEmpty('no map fields were found, so this test proves nothing');

    foreach ($found as $field) {
        expect($field)->toBeInstanceOf(BasemapMap::class, "map field {$field->getName()} bypasses the basemap component");

        $config = mapFieldTileConfig($field);

        expect($config['tilesUrl'])->toBe(Basemap::tilesUrl(), "map field {$field->getName()} uses another tile source")
            ->and(trim((string) $config['attribution']))->not->toBe('', "map field {$field->getName()} renders without attribution");
    }
});

test('no map field is built straight from the upstream component', function () {
    // The upstream field defaults to another tile service and has no setter
    // for the attribution at all, so anything constructed from it directly
    // would render unattributed however the configuration is set.
    $offenders = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path()));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();

        if ($path === (new ReflectionClass(BasemapMap::class))->getFileName()) {
            continue;
        }

        if (str_contains((string) file_get_contents($path), 'Dotswan\\MapPicker\\Fields\\Map')) {
            $offenders[] = str_replace(app_path().'/', '', $path);
        }
    }

    expect($offenders)->toBe([]);
});

test('the read-only infolist map uses the same basemap as the form fields', function () {
    Config::set('maps.tiles.url', 'https://tiles.example.test/{z}/{x}/{y}.png');
    Config::set('maps.tiles.attribution', 'Example attribution');

    $entry = GeoJsonMapEntry::make('zaakgeometrie');

    expect($entry->getTilesUrl())->toBe('https://tiles.example.test/{z}/{x}/{y}.png')
        ->and($entry->getTileLayerOptions()['attribution'])->toBe('Example attribution');
});

test('the infolist map view hard-codes no tile service of its own', function () {
    $view = (string) file_get_contents(
        resource_path('views/filament/infolists/components/geojson-map-entry.blade.php')
    );

    expect($view)->not->toContain('tile.openstreetmap.org')
        ->and($view)->toContain('$entry->getTilesUrl()')
        ->and($view)->toContain('$entry->getTileLayerOptions()');
});

test('the CSP allows the configured basemap host and nothing wider', function () {
    Config::set('app.env', 'local');
    Config::set('app.debug', false);
    Config::set('maps.tiles.url', 'https://tiles.example.test/{z}/{x}/{y}.png');

    $csp = $this->get('/up')->headers->get('Content-Security-Policy');

    expect($csp)->toContain("img-src 'self' data: blob: https://tiles.example.test")
        ->and($csp)->not->toContain('tile.openstreetmap.org');
});

test('the CSP follows the tile URL when it is repointed', function () {
    // This is the trap the change is guarding against: a basemap that is
    // configured correctly but blocked by the browser, which looks exactly
    // like a basemap that was never configured at all.
    Config::set('app.env', 'local');
    Config::set('app.debug', false);
    Config::set('maps.tiles.url', 'https://other-tiles.example.test/{z}/{x}/{y}.png');

    $csp = $this->get('/up')->headers->get('Content-Security-Policy');

    expect($csp)->toContain('https://other-tiles.example.test')
        ->and($csp)->not->toContain('tiles.example.test/');
});
