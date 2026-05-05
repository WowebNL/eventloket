<?php

declare(strict_types=1);

namespace App\EventForm\Reporting;

use App\EventForm\State\FormState;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Wizard\Step;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionObject;

/**
 * Bouwt het inzendingsbewijs als een lijst secties (één per stap)
 * met (label, waarde)-paren. Stappen die geen ingevulde velden
 * bevatten worden weggelaten zodat de PDF compact blijft.
 *
 * Werkwijze: per stap walken we via reflection door de child-
 * components van de Filament-Step, omdat Filament's eigen
 * `getChildComponents()` een container-context nodig heeft die we
 * in de queue-job (zonder mounted Livewire-component) niet hebben.
 *
 * Labels zijn vaak Closures die `$livewire->state()` gebruiken om
 * placeholders zoals `{{ watIsDeNaamVanHetEvenementVergunning }}`
 * te interpoleren. We voeden ze met een mini-stub die diezelfde
 * `state()`-methode aanbiedt — dat is exact wat Filament's eigen
 * runtime ook doet, alleen via z'n parameter-injectie.
 */
final class SubmissionReport
{
    /**
     * @param  list<Step>  $steps
     * @return list<array{title: string, entries: list<array{label: string, value: string}>}>
     */
    public function build(FormState $state, array $steps): array
    {
        $sections = [];

        foreach ($steps as $step) {
            $entries = $this->extractEntries($step, $state);
            if ($entries === []) {
                continue;
            }
            $title = (string) ($step->getLabel() ?? '');
            $sections[] = [
                'title' => $title !== '' ? $title : 'Sectie',
                'entries' => $entries,
            ];
        }

        return $sections;
    }

    /**
     * @return list<array{label: string, value: string, sub?: list<array{label: string, value: string}>}>
     */
    private function extractEntries(Step $step, FormState $state): array
    {
        $stubLivewire = $this->stubLivewire($state);
        $entries = [];

        $walk = function (object $component, ?string $keyPrefix = null) use (&$walk, &$entries, $state, $stubLivewire): void {
            // Repeater: per rij een sub-tabel, alle child-velden uitgeklapt.
            if ($component instanceof Repeater) {
                $key = $component->getName();
                if ($key === null || $key === '') {
                    return;
                }
                $fullKey = $keyPrefix === null ? $key : "{$keyPrefix}.{$key}";
                $rows = is_array($state->get($fullKey)) ? $state->get($fullKey) : [];
                if ($rows === []) {
                    return;
                }
                $repeaterLabel = $this->renderLabel($component, $stubLivewire);
                foreach (array_values($rows) as $rowIndex => $rowData) {
                    if (! is_array($rowData)) {
                        continue;
                    }
                    $subEntries = $this->extractRowEntries($component, $state, "{$fullKey}.{$rowIndex}", $stubLivewire);
                    if ($subEntries === []) {
                        continue;
                    }
                    $entries[] = [
                        'label' => sprintf('%s — rij %d', $repeaterLabel, $rowIndex + 1),
                        'value' => '',
                        'sub' => $subEntries,
                    ];
                }

                return;
            }

            if ($component instanceof Field) {
                $key = $component->getName();
                if ($key !== null && $key !== '') {
                    $fullKey = $keyPrefix === null ? $key : "{$keyPrefix}.{$key}";
                    $entry = $this->buildEntry($component, $state, $fullKey, $stubLivewire);
                    if ($entry !== null) {
                        $entries[] = $entry;
                    }
                }
            }

            $this->descendIntoChildren($component, $walk, $keyPrefix);
        };

        $walk($step, null);

        return $entries;
    }

    /**
     * @return array{label: string, value: string, svg?: string}|null
     */
    private function buildEntry(Field $component, FormState $state, string $key, object $stubLivewire): ?array
    {
        $rawValue = $state->get($key);
        $value = $this->renderValue($component, $state, $key);

        $svg = null;
        if (is_array($rawValue) && isset($rawValue['geojson'])) {
            $svg = $this->renderGeoJsonSvg($rawValue['geojson']);
        }

        if ($value === '' && $svg === null) {
            return null;
        }

        $label = $this->renderLabel($component, $stubLivewire);

        // Geen label = veld is voor deze context niet relevant (bv.
        // dynamische `reportQuestion_X` waarvoor `gemeenteVariabelen.
        // report_questions` nog niet gevuld is). Niet in PDF tonen.
        if (trim(strip_tags($label)) === '') {
            return null;
        }

        $entry = [
            'label' => $label,
            'value' => $value,
        ];
        if ($svg !== null) {
            $entry['svg'] = $svg;
        }

        return $entry;
    }

    /**
     * Walk de child-fields van één Repeater-rij, met de rij-state als
     * scope. Levert dezelfde shape als top-level entries (inclusief
     * eventuele SVG-render van een Map-veld).
     *
     * @return list<array{label: string, value: string, svg?: string}>
     */
    private function extractRowEntries(Repeater $repeater, FormState $state, string $rowKeyPrefix, object $stubLivewire): array
    {
        $entries = [];

        $walk = function (object $component) use (&$walk, &$entries, $state, $stubLivewire, $rowKeyPrefix): void {
            if ($component instanceof Repeater) {
                return; // geneste Repeaters laten we plat
            }
            if ($component instanceof Field) {
                $key = $component->getName();
                if ($key !== null && $key !== '') {
                    $fullKey = "{$rowKeyPrefix}.{$key}";
                    $entry = $this->buildEntry($component, $state, $fullKey, $stubLivewire);
                    if ($entry !== null) {
                        $entries[] = $entry;
                    }
                }
            }
            $this->descendIntoChildren($component, $walk, $rowKeyPrefix);
        };

        $this->descendIntoChildren($repeater, $walk, $rowKeyPrefix);

        return $entries;
    }

    /**
     * Render een GeoJSON FeatureCollection als compact SVG voor in de PDF.
     * Tegels van OpenStreetMap als achtergrond + polygon/lijn/point in
     * Mercator-projectie erbovenop. SVG wordt als data-URI in een `<img>`
     * gewrapt zodat dompdf 'm rendert (inline `<svg>` werkt niet in dompdf).
     *
     * @param  array<string, mixed>  $geojson
     */
    private function renderGeoJsonSvg(array $geojson): ?string
    {
        $features = $geojson['features'] ?? null;
        if (! is_array($features) || $features === []) {
            return null;
        }

        $allCoords = [];
        $shapes = [];

        foreach ($features as $feature) {
            $geometry = $feature['geometry'] ?? null;
            if (! is_array($geometry)) {
                continue;
            }
            $type = $geometry['type'] ?? null;
            $coords = $geometry['coordinates'] ?? null;
            if (! is_string($type) || ! is_array($coords)) {
                continue;
            }
            $shapes[] = ['type' => $type, 'coords' => $coords];
            $this->collectCoords($type, $coords, $allCoords);
        }

        if ($allCoords === []) {
            return null;
        }

        $minLng = min(array_column($allCoords, 0));
        $maxLng = max(array_column($allCoords, 0));
        $minLat = min(array_column($allCoords, 1));
        $maxLat = max(array_column($allCoords, 1));

        $centerLat = ($minLat + $maxLat) / 2;
        $centerLng = ($minLng + $maxLng) / 2;
        $zoom = $this->estimateZoom($maxLng - $minLng, $maxLat - $minLat);

        // 2×2 tile-grid → 512×512px canvas
        $canvas = 512;
        [$tilesPng, $originPx] = $this->fetchTileMosaic($centerLat, $centerLng, $zoom, $canvas);

        $project = function (array $coord) use ($zoom, $originPx): array {
            $px = $this->lngToPixel($coord[0], $zoom) - $originPx[0];
            $py = $this->latToPixel($coord[1], $zoom) - $originPx[1];

            return [round($px, 2), round($py, 2)];
        };

        $svgPaths = '';
        foreach ($shapes as $shape) {
            $svgPaths .= $this->renderShape($shape['type'], $shape['coords'], $project);
        }

        $background = $tilesPng !== null
            ? sprintf(
                '<image href="data:image/png;base64,%s" width="%d" height="%d" preserveAspectRatio="none"/>',
                base64_encode($tilesPng),
                $canvas,
                $canvas,
            )
            : sprintf('<rect width="%d" height="%d" fill="#fafafa"/>', $canvas, $canvas);

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">%s%s<rect width="%d" height="%d" fill="none" stroke="#666" stroke-width="1"/></svg>',
            $canvas, $canvas, $canvas, $canvas, $background, $svgPaths, $canvas, $canvas,
        );

        return sprintf(
            '<img class="map-img" alt="kaart" src="data:image/svg+xml;base64,%s" />',
            base64_encode($svg),
        );
    }

    /**
     * Schat een passend zoom-niveau bij gegeven bbox-grootte (graden).
     * Per zoom-niveau halveert de zichtbare extent. Bij 256px-tile en
     * een 512px-canvas willen we de bbox ongeveer in 70% van het
     * canvas hebben.
     */
    private function estimateZoom(float $rangeLng, float $rangeLat): int
    {
        $extent = max($rangeLng, $rangeLat / cos(deg2rad(0))) ?: 0.001;
        // Empirisch: zoom 16 ≈ 0.005°, zoom 15 ≈ 0.011°, zoom 14 ≈ 0.022° etc.
        $zoom = (int) floor(log(360 / max($extent * 1.4, 0.0005), 2));

        return max(8, min(18, $zoom));
    }

    /**
     * Haal 2×2 tegels op (256×256 elk) gecentreerd op (lat, lng) bij
     * gegeven zoom. Stitch ze samen tot één PNG. Returnt het PNG-blob
     * + de pixel-origin van de linkerbovenhoek (in OSM Mercator-pixels)
     * zodat polygon/line-coords correct geprojecteerd kunnen worden.
     *
     * Falen we (offline / rate-limit), returnt null voor de PNG —
     * caller valt dan terug op een effen achtergrond.
     *
     * @return array{0: ?string, 1: array{0: float, 1: float}}
     */
    private function fetchTileMosaic(float $lat, float $lng, int $zoom, int $canvas): array
    {
        $centerPx = [$this->lngToPixel($lng, $zoom), $this->latToPixel($lat, $zoom)];
        // Linkerbovenhoek van 512×512 canvas in wereld-pixel-coords.
        $originPx = [$centerPx[0] - $canvas / 2, $centerPx[1] - $canvas / 2];

        // Welke tiles dekken deze 2×2 grid? OSM tegels zijn 256×256.
        $tile00X = (int) floor($originPx[0] / 256);
        $tile00Y = (int) floor($originPx[1] / 256);
        $offsetX = (int) ($tile00X * 256 - $originPx[0]);
        $offsetY = (int) ($tile00Y * 256 - $originPx[1]);

        // Hoeveel tegels horizontal/vertical om 't canvas te dekken?
        $tilesX = (int) ceil(($canvas - $offsetX) / 256);
        $tilesY = (int) ceil(($canvas - $offsetY) / 256);

        // Als de extension de gd-extensie ondersteunt, kunnen we een
        // composite maken. Anders skip de stitch en val terug op effen.
        if (! function_exists('imagecreatetruecolor')) {
            return [null, $originPx];
        }

        $mosaic = imagecreatetruecolor($canvas, $canvas);
        imagefill($mosaic, 0, 0, imagecolorallocate($mosaic, 250, 250, 250));

        $maxTile = (1 << $zoom) - 1;
        for ($dy = 0; $dy < $tilesY; $dy++) {
            for ($dx = 0; $dx < $tilesX; $dx++) {
                $tx = ($tile00X + $dx + $maxTile + 1) % ($maxTile + 1);
                $ty = $tile00Y + $dy;
                if ($ty < 0 || $ty > $maxTile) {
                    continue;
                }
                $url = sprintf('https://tile.openstreetmap.org/%d/%d/%d.png', $zoom, $tx, $ty);
                $tilePng = $this->fetchTile($url);
                if ($tilePng === null) {
                    continue;
                }
                $tile = @imagecreatefromstring($tilePng);
                if ($tile === false) {
                    continue;
                }
                imagecopy($mosaic, $tile, $offsetX + $dx * 256, $offsetY + $dy * 256, 0, 0, 256, 256);
                imagedestroy($tile);
            }
        }

        ob_start();
        imagepng($mosaic);
        $stitched = (string) ob_get_clean();
        imagedestroy($mosaic);

        return [$stitched, $originPx];
    }

    private function fetchTile(string $url): ?string
    {
        // Cache per URL-hash zodat een PDF-render met meerdere geometrieën
        // op dezelfde tegel niet 2× downloadt.
        $cacheKey = 'osm-tile:'.sha1($url);

        return Cache::remember($cacheKey, 3600, function () use ($url): ?string {
            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Eventloket/1.0 (PDF-render; admin@veiligheidsregiozl.nl)',
                ])->timeout(8)->get($url);

                if ($response->successful()) {
                    return $response->body();
                }
            } catch (\Throwable) {
                // Stille fallback — caller gaat door zonder achtergrond.
            }

            return null;
        });
    }

    private function lngToPixel(float $lng, int $zoom): float
    {
        return 256 * (1 << $zoom) * (($lng + 180) / 360);
    }

    private function latToPixel(float $lat, int $zoom): float
    {
        $latRad = deg2rad($lat);
        $sinLat = sin($latRad);

        return 256 * (1 << $zoom) * (0.5 - log((1 + $sinLat) / (1 - $sinLat)) / (4 * M_PI));
    }

    /**
     * @param  list<array{0: float, 1: float}>  $bucket
     */
    private function collectCoords(string $type, mixed $coordinates, array &$bucket): void
    {
        if (! is_array($coordinates)) {
            return;
        }

        switch ($type) {
            case 'Point':
                $bucket[] = [(float) $coordinates[0], (float) $coordinates[1]];
                break;
            case 'LineString':
                foreach ($coordinates as $pt) {
                    if (is_array($pt)) {
                        $bucket[] = [(float) $pt[0], (float) $pt[1]];
                    }
                }
                break;
            case 'Polygon':
                foreach ($coordinates as $ring) {
                    foreach ((array) $ring as $pt) {
                        if (is_array($pt)) {
                            $bucket[] = [(float) $pt[0], (float) $pt[1]];
                        }
                    }
                }
                break;
            case 'MultiPolygon':
                foreach ($coordinates as $polygon) {
                    foreach ((array) $polygon as $ring) {
                        foreach ((array) $ring as $pt) {
                            if (is_array($pt)) {
                                $bucket[] = [(float) $pt[0], (float) $pt[1]];
                            }
                        }
                    }
                }
                break;
        }
    }

    private function renderShape(string $type, mixed $coords, Closure $project): string
    {
        return match ($type) {
            'Point' => is_array($coords) ? $this->svgPoint($project([(float) $coords[0], (float) $coords[1]])) : '',
            'LineString' => is_array($coords)
                ? $this->svgLine(array_map(fn ($pt) => $project([(float) $pt[0], (float) $pt[1]]), $coords))
                : '',
            'Polygon' => is_array($coords) && is_array($coords[0] ?? null)
                ? $this->svgPolygon(array_map(fn ($pt) => $project([(float) $pt[0], (float) $pt[1]]), $coords[0]))
                : '',
            'MultiPolygon' => $this->svgMultiPolygon($coords, $project),
            default => '',
        };
    }

    private function svgPoint(array $pt): string
    {
        return sprintf('<circle cx="%s" cy="%s" r="4" fill="#3478f6" stroke="#fff" stroke-width="2"/>', $pt[0], $pt[1]);
    }

    private function svgLine(array $points): string
    {
        if ($points === []) {
            return '';
        }
        $d = implode(' ', array_map(fn ($i, $pt) => ($i === 0 ? 'M' : 'L').$pt[0].' '.$pt[1], array_keys($points), $points));

        return sprintf('<path d="%s" fill="none" stroke="#e74c3c" stroke-width="2.5" stroke-linejoin="round"/>', $d);
    }

    private function svgPolygon(array $points): string
    {
        if ($points === []) {
            return '';
        }
        $d = implode(' ', array_map(fn ($i, $pt) => ($i === 0 ? 'M' : 'L').$pt[0].' '.$pt[1], array_keys($points), $points)).' Z';

        return sprintf('<path d="%s" fill="rgba(52,120,246,0.25)" stroke="#3478f6" stroke-width="2"/>', $d);
    }

    private function svgMultiPolygon(mixed $coords, Closure $project): string
    {
        if (! is_array($coords)) {
            return '';
        }
        $parts = '';
        foreach ($coords as $polygon) {
            if (is_array($polygon) && is_array($polygon[0] ?? null)) {
                $parts .= $this->svgPolygon(array_map(fn ($pt) => $project([(float) $pt[0], (float) $pt[1]]), $polygon[0]));
            }
        }

        return $parts;
    }

    /**
     * Walk via reflection door `childComponents` (de standaard Filament-
     * children-pool van Schemas/Components/Concerns/HasChildComponents).
     */
    private function descendIntoChildren(object $component, Closure $walk, ?string $keyPrefix): void
    {
        if (! property_exists($component, 'childComponents')) {
            return;
        }
        $reflection = new ReflectionObject($component);
        if (! $reflection->hasProperty('childComponents')) {
            return;
        }
        $prop = $reflection->getProperty('childComponents');
        $prop->setAccessible(true);
        $children = $prop->getValue($component);
        if (! is_array($children)) {
            return;
        }
        foreach ($children as $list) {
            if (! is_array($list)) {
                continue;
            }
            foreach ($list as $child) {
                if (is_object($child)) {
                    $walk($child, $keyPrefix);
                }
            }
        }
    }

    private function renderLabel(Field $component, object $stubLivewire): string
    {
        $reflection = new ReflectionObject($component);
        if (! $reflection->hasProperty('label')) {
            return $component->getName();
        }
        $prop = $reflection->getProperty('label');
        $prop->setAccessible(true);
        $raw = $prop->getValue($component);

        if ($raw instanceof Closure) {
            try {
                return (string) $raw($stubLivewire);
            } catch (\Throwable) {
                return $component->getName();
            }
        }

        return (string) ($raw ?? $component->getName());
    }

    private function renderValue(Field $component, FormState $state, string $key): string
    {
        $value = $state->get($key);

        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match (true) {
            $component instanceof DateTimePicker => $this->humanDateTime($value),
            $component instanceof DatePicker => $this->humanDate($value),
            $component instanceof CheckboxList => $this->renderCheckboxListValue($component, $value),
            $component instanceof Radio, $component instanceof Select => $this->renderSelectValue($component, $value),
            $component instanceof FileUpload => $this->renderFiles($value),
            $component instanceof Textarea, $component instanceof TextInput => (string) $value,
            default => is_scalar($value) ? (string) $value : $this->renderMixed($value),
        };
    }

    /**
     * Voor `[geselecteerde, opties]` in een CheckboxList → comma-separated
     * lijst van menselijke labels (uit `->options(...)`-mapping). Filament
     * slaat alleen de keys op; voor PDF-leesbaarheid willen we de tekst.
     */
    private function renderCheckboxListValue(CheckboxList $component, mixed $value): string
    {
        $options = $this->extractOptions($component);
        $selected = is_array($value) ? $value : [$value];

        return collect($selected)
            ->map(fn ($v) => (string) ($options[$v] ?? $v))
            ->filter()
            ->implode(', ');
    }

    /**
     * Map-state met geojson → meta-info-string ("Polygon, X punten,
     * centroïde lat/lng"). De SVG-render zit op het entry zelf via een
     * aparte `svg`-key — zie `enrichGeometryEntries()`.
     */
    private function renderMixed(mixed $value): string
    {
        if (is_array($value) && isset($value['geojson'])) {
            return $this->describeMapState($value);
        }

        return $this->renderList($value);
    }

    /**
     * @return array<int|string, string>
     */
    private function extractOptions(Field $component): array
    {
        $reflection = new ReflectionObject($component);
        if (! $reflection->hasProperty('options')) {
            return [];
        }
        $prop = $reflection->getProperty('options');
        $prop->setAccessible(true);
        $raw = $prop->getValue($component);
        if ($raw instanceof Closure) {
            return [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Korte omschrijving van een Map-state-object.
     *
     * @param  array<string, mixed>  $value
     */
    private function describeMapState(array $value): string
    {
        $features = $value['geojson']['features'] ?? [];
        if (! is_array($features) || $features === []) {
            return 'Geen geometrie ingetekend';
        }
        $parts = [];
        foreach ($features as $feature) {
            $geometry = $feature['geometry'] ?? null;
            if (! is_array($geometry) || ! isset($geometry['type'])) {
                continue;
            }
            $type = (string) $geometry['type'];
            $coords = $geometry['coordinates'] ?? null;
            $count = $this->countCoordinates($type, $coords);
            $parts[] = sprintf('%s (%d punten)', $type, $count);
        }

        return $parts === [] ? 'Geometrie ingetekend' : implode('; ', $parts);
    }

    private function countCoordinates(string $type, mixed $coordinates): int
    {
        if (! is_array($coordinates)) {
            return 0;
        }

        return match ($type) {
            'Polygon' => is_array($coordinates[0] ?? null) ? count($coordinates[0]) : 0,
            'MultiPolygon' => array_sum(array_map(
                fn ($p) => is_array($p) && is_array($p[0] ?? null) ? count($p[0]) : 0,
                $coordinates,
            )),
            'LineString' => count($coordinates),
            'Point' => 1,
            default => 0,
        };
    }

    private function renderSelectValue(Field $component, mixed $value): string
    {
        // Probeer de option-label te tonen i.p.v. de raw key. We doen dat
        // via reflection op `$options` omdat `getOptions()` ook een
        // container nodig kan hebben.
        $reflection = new ReflectionObject($component);
        if ($reflection->hasProperty('options')) {
            $prop = $reflection->getProperty('options');
            $prop->setAccessible(true);
            $rawOptions = $prop->getValue($component);
            $options = $rawOptions instanceof Closure ? null : $rawOptions;
            if (is_array($options)) {
                if (is_array($value)) {
                    return collect($value)->map(fn ($v) => (string) ($options[$v] ?? $v))->implode(', ');
                }

                return (string) ($options[$value] ?? $value);
            }
        }

        return is_array($value) ? $this->renderList($value) : (string) $value;
    }

    /** @param  array<int|string, mixed>|string|int|float|bool  $value */
    private function renderList(mixed $value): string
    {
        if (! is_array($value)) {
            return (string) $value;
        }

        return collect($value)
            ->map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v))
            ->filter()
            ->implode(', ');
    }

    private function renderFiles(mixed $value): string
    {
        if (! is_array($value)) {
            return is_string($value) ? basename($value) : '';
        }

        return collect($value)
            ->map(fn ($v) => is_string($v) ? basename($v) : (is_array($v) ? ($v['name'] ?? '') : ''))
            ->filter()
            ->implode(', ');
    }

    private function humanDateTime(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }
        try {
            return Carbon::parse((string) $value, 'Europe/Amsterdam')->translatedFormat('j F Y · H:i');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function humanDate(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }
        try {
            return Carbon::parse((string) $value, 'Europe/Amsterdam')->translatedFormat('j F Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function stubLivewire(FormState $state): object
    {
        return new class($state)
        {
            public function __construct(private readonly FormState $state) {}

            public function state(): FormState
            {
                return $this->state;
            }
        };
    }
}
