<?php

declare(strict_types=1);

namespace App\EventForm\Services;

use App\Actions\Geospatial\CheckIntersects;
use App\Actions\Geospatial\CheckWithin;
use App\Models\Municipality;
use App\Services\LocatieserverService;
use Brick\Geo\Engine\GeometryEngine;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\LineString;
use Brick\Geo\Polygon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bepaalt welke gemeenten geraakt of doorkruist worden door opgegeven
 * polygonen, lijnen en adressen, en vult de response-structuur zoals OF
 * die verwacht (oorspronkelijk endpoint POST /api/locationserver/check).
 *
 * Logica is 1-op-1 overgenomen uit `LocationServerController::check()`
 * zodat gedrag identiek blijft. De controller is nu een thin wrapper
 * (validate + json_decode + json response).
 */
class LocationServerCheckService
{
    public function __construct(
        private readonly LocatieserverService $locatieserver = new LocatieserverService,
    ) {}

    /**
     * @return array<string, mixed> Response zoals OF die verwacht:
     *                              `{ all, polygons, line, lines, addresses }`.
     */
    public function execute(LocationServerCheckInput $input): array
    {
        $response = $this->emptyResponse();
        $engine = null;
        $intersects = null;
        $within = null;

        if ($input->polygons !== null) {
            [$engine, $intersects, $within] = $this->ensureEngine($engine, $intersects, $within);
            foreach ($input->polygons as $polygonObject) {
                /** @var Polygon $polygon */
                $polygon = (new GeoJsonReader)->read((string) json_encode($polygonObject));
                $items = $intersects->checkIntersectsWithModels($polygon);
                $response = $this->mergeItems($response, $items, ['all.items', 'polygons.items']);
                $response = $this->mergeWithin(
                    $response,
                    fn () => $within->checkWithinAllGeometriesFromModels($polygon),
                    ['polygons.within', 'all.within'],
                );
            }
        }

        if ($input->line !== null) {
            [$engine, $intersects, $within] = $this->ensureEngine($engine, $intersects, $within);
            /** @var LineString $line */
            $line = (new GeoJsonReader)->read((string) json_encode($input->line));
            $response = $this->absorbLineIntoSingleLine($response, $line, $intersects, $within);
        }

        if ($input->lines !== null) {
            [$engine, $intersects, $within] = $this->ensureEngine($engine, $intersects, $within);
            foreach ($input->lines as $lineObject) {
                /** @var LineString $line */
                $line = (new GeoJsonReader)->read((string) json_encode($lineObject));
                $response = $this->appendLineToLinesArray($response, $line, $intersects, $within);
            }
            $response = $this->promoteSingleLine($response);
        }

        if ($input->addresses !== null) {
            foreach ($input->addresses as $address) {
                $response = $this->absorbAddress($response, $address);
            }
        }
        if ($input->address !== null) {
            $response = $this->absorbAddress($response, $input->address);
        }

        return $this->finalize($response);
    }

    /** @return array<string, mixed> */
    private function emptyResponse(): array
    {
        return [
            'all' => [
                'items' => collect(),
                'within' => null,
                'object' => null,
            ],
            'polygons' => [
                'items' => collect(),
                'within' => null,
            ],
            'line' => [
                'items' => collect(),
                'within' => null,
                'start' => null,
                'end' => null,
                'start_end_equal' => null,
                'passing' => collect(),
            ],
            'lines' => [],
            'addresses' => [
                'items' => collect(),
                'within' => null,
            ],
        ];
    }

    /**
     * @return array{0: GeometryEngine, 1: CheckIntersects, 2: CheckWithin}
     */
    private function ensureEngine(?GeometryEngine $engine, ?CheckIntersects $intersects, ?CheckWithin $within): array
    {
        $engine = $engine ?? new PdoEngine(DB::connection()->getPdo());

        return [
            $engine,
            $intersects ?? new CheckIntersects($engine),
            $within ?? new CheckWithin($engine),
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function absorbLineIntoSingleLine(
        array $response,
        LineString $line,
        CheckIntersects $intersects,
        CheckWithin $within,
    ): array {
        /** @var Collection<int, Municipality> $items */
        $items = $intersects->checkIntersectsWithModels($line);
        $startModel = $intersects->checkIntersectsWithModels($line->startPoint());
        $endModel = $intersects->checkIntersectsWithModels($line->endPoint());

        $excluded = $startModel->pluck('brk_identification')
            ->merge($endModel->pluck('brk_identification'))
            ->unique()
            ->toArray();

        $response['line']['passing'] = $this->minimize(
            $items->reject(fn ($item) => in_array($item->brk_identification, $excluded, true))
        )->values();

        $response = $this->mergeItems($response, $items, ['all.items', 'line.items']);

        if ($start = $startModel->first()) {
            $response['line']['start'] = [
                'brk_identification' => $start->brk_identification,
                'name' => $start->name,
            ];
        }
        if ($end = $endModel->first()) {
            $response['line']['end'] = [
                'brk_identification' => $end->brk_identification,
                'name' => $end->name,
            ];
        }
        $response = $this->mergeWithin(
            $response,
            fn () => $within->checkWithinAllGeometriesFromModels($line),
            ['line.within', 'all.within'],
        );
        if (! $startModel->isEmpty() && ! $endModel->isEmpty()) {
            $response['line']['start_end_equal'] = $startModel->first()->id === $endModel->first()->id;
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function appendLineToLinesArray(
        array $response,
        LineString $line,
        CheckIntersects $intersects,
        CheckWithin $within,
    ): array {
        /** @var Collection<int, Municipality> $items */
        $items = $intersects->checkIntersectsWithModels($line);
        $startModel = $intersects->checkIntersectsWithModels($line->startPoint());
        $endModel = $intersects->checkIntersectsWithModels($line->endPoint());

        $excluded = $startModel->pluck('brk_identification')
            ->merge($endModel->pluck('brk_identification'))
            ->unique()
            ->toArray();

        $lineData = [
            'items' => array_values($this->minimize($items)->toArray()),
            'within' => null,
            'start' => null,
            'end' => null,
            'start_end_equal' => null,
            'passing' => array_values(
                $this->minimize(
                    $items->reject(fn ($item) => in_array($item->brk_identification, $excluded, true))
                )->toArray()
            ),
        ];

        if ($start = $startModel->first()) {
            $lineData['start'] = [
                'brk_identification' => $start->brk_identification,
                'name' => $start->name,
            ];
        }
        if ($end = $endModel->first()) {
            $lineData['end'] = [
                'brk_identification' => $end->brk_identification,
                'name' => $end->name,
            ];
        }
        if (! $startModel->isEmpty() && ! $endModel->isEmpty()) {
            $lineData['start_end_equal'] = $startModel->first()->id === $endModel->first()->id;
        }

        $response['lines'][] = $lineData;

        $response = $this->mergeItems($response, $items, ['all.items']);
        $response = $this->mergeWithin(
            $response,
            fn () => $within->checkWithinAllGeometriesFromModels($line),
            ['all.within'],
        );

        $lastIndex = count($response['lines']) - 1;
        $response['lines'][$lastIndex]['within'] = $within->checkWithinAllGeometriesFromModels($line);

        return $response;
    }

    /**
     * Als er precies 1 line binnen `lines[]` staat, promoveer die naar het
     * single-`line`-object. Gedrag overgenomen uit de originele controller.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function promoteSingleLine(array $response): array
    {
        if (count($response['lines']) !== 1) {
            return $response;
        }

        $first = $response['lines'][0];
        $response['line'] = [
            'items' => collect($first['items']),
            'within' => $first['within'],
            'start' => $first['start'],
            'end' => $first['end'],
            'start_end_equal' => $first['start_end_equal'],
            'passing' => collect($first['passing']),
        ];

        return $response;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array{postcode: string, houseNumber: string}  $address
     * @return array<string, mixed>
     */
    private function absorbAddress(array $response, array $address): array
    {
        $brkId = $this->locatieserver->getBrkIdentificationByPostcodeHuisnummer(
            $address['postcode'],
            $address['houseNumber'],
        );
        $municipality = $brkId
            ? Municipality::where('brk_identification', $brkId)
                ->select(['brk_identification', 'name'])
                ->first()
            : null;

        if ($municipality !== null) {
            $response = $this->mergeItems(
                $response,
                collect([$municipality]),
                ['all.items', 'addresses.items'],
            );
            $response = $this->mergeWithin(
                $response,
                fn () => true,
                ['addresses.within', 'all.within'],
            );
        } else {
            $response = $this->mergeWithin(
                $response,
                fn () => false,
                ['addresses.within', 'all.within'],
            );
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  Collection<int, Municipality>  $items
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    private function mergeItems(
        array $response,
        Collection $items,
        array $paths,
        string $unique = 'brk_identification',
    ): array {
        $minimized = $this->minimize($items);
        foreach ($paths as $path) {
            $current = Arr::get($response, $path);
            if ($current instanceof Collection) {
                Arr::set(
                    $response,
                    $path,
                    $current->merge($minimized)->unique($unique),
                );
            }
        }

        return $response;
    }

    /**
     * Reduceer een collectie Municipality-modellen tot een plain collectie met
     * alleen `brk_identification` + `name`. Vervangt `->select([...])` dat
     * PHPStan niet type-veilig vindt op Model-collecties.
     *
     * @param  Collection<int, Municipality>  $items
     * @return Collection<int, array{brk_identification: string, name: string}>
     */
    private function minimize(Collection $items): Collection
    {
        return $items->map(fn (Municipality $m): array => [
            'brk_identification' => (string) $m->brk_identification,
            'name' => (string) $m->name,
        ]);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  callable(): mixed  $check
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    private function mergeWithin(array $response, callable $check, array $paths): array
    {
        foreach ($paths as $path) {
            $current = Arr::get($response, $path);
            if ($current === null || $current === true) {
                Arr::set($response, $path, $check());
            }
        }

        return $response;
    }

    /**
     * Normaliseert de response naar het JSON-vriendelijke formaat:
     * `all.items` wordt een plain array (was Collection), en `all.object`
     * wordt opgebouwd uit `all.items`.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function finalize(array $response): array
    {
        $response['all']['object'] = $this->objectFromItems(Arr::get($response, 'all.items'));
        $allItems = Arr::get($response, 'all.items');
        if ($allItems instanceof Collection) {
            $response['all']['items'] = array_values($allItems->toArray());
        }

        return $response;
    }

    /**
     * @return array<string, array{brk_identification: string, name: string}>|null
     */
    private function objectFromItems(mixed $items): ?array
    {
        if (! $items instanceof Collection || $items->isEmpty()) {
            return null;
        }

        $object = [];
        foreach ($items as $item) {
            $object[$item['brk_identification']] = [
                'brk_identification' => $item['brk_identification'],
                'name' => $item['name'],
            ];
        }

        return $object;
    }
}
