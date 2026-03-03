<?php

namespace App\Http\Controllers\Api;

use App\Actions\Geospatial\CheckIntersects;
use App\Actions\Geospatial\CheckWithin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocationServerCheckRequest;
use App\Models\Municipality;
use App\Services\LocatieserverService;
use Brick\Geo\Engine\PdoEngine;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\Polygon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LocationServerController extends Controller
{
    public function check(LocationServerCheckRequest $request)
    {
        $data = $request->validated();

        $responseData = [
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
            ],
            'lines' => [],
            'addresses' => [
                'items' => collect(),
                'within' => null,
            ],
        ];

        $geometryEngine = null;
        $checkIntersects = null;
        $checkWithin = null;

        if (isset($data['polygons'])) {
            $polygons = json_decode($data['polygons']);
            $geometryEngine = new PdoEngine(DB::connection()->getPdo());
            $checkIntersects = new CheckIntersects($geometryEngine);
            $checkWithin = new CheckWithin($geometryEngine);

            foreach ($polygons as $object) {
                // dd($object);
                /** @var Polygon $polygon */
                $polygon = (new GeoJsonReader)->read(json_encode($object));

                /** @var Collection<\App\Models\Municipality> $items */
                $items = $checkIntersects->checkIntersectsWithModels($polygon);

                $responseData = $this->updateResponseDataItems($responseData, $items, ['all.items', 'polygons.items']);
                $responseData = $this->updateResponseDataWithin($responseData, fn () => $checkWithin->checkWithinAllGeometriesFromModels($polygon), ['polygons.within', 'all.within']);
            }
        }

        if (isset($data['line'])) {
            /** @var \Brick\Geo\LineString $line */
            $line = (new GeoJsonReader)->read($data['line']);
            $geometryEngine = $geometryEngine ?? new PdoEngine(DB::connection()->getPdo());
            $checkIntersects = $checkIntersects ?? new CheckIntersects($geometryEngine);
            $checkWithin = $checkWithin ?? new CheckWithin($geometryEngine);

            /** @var Collection<\App\Models\Municipality> $items */
            $items = $checkIntersects->checkIntersectsWithModels($line);
            $startModel = $checkIntersects->checkIntersectsWithModels($line->startPoint());
            $endModel = $checkIntersects->checkIntersectsWithModels($line->endPoint());

            $responseData = $this->updateResponseDataItems($responseData, $items, ['all.items', 'line.items']);

            if ($start = $startModel->first()) {
                $responseData['line']['start'] = [
                    'brk_identification' => $start->brk_identification,
                    'name' => $start->name,
                ];
            }
            if ($end = $endModel->first()) {
                $responseData['line']['end'] = [
                    'brk_identification' => $end->brk_identification,
                    'name' => $end->name,
                ];
            }
            $responseData = $this->updateResponseDataWithin($responseData, fn () => $checkWithin->checkWithinAllGeometriesFromModels($line), ['line.within', 'all.within']);
            if (! $startModel->isEmpty() && ! $endModel->isEmpty()) {
                $responseData['line']['start_end_equal'] = $startModel->first()->id == $endModel->first()->id;
            }
        }

        if (isset($data['lines'])) {
            $lines = json_decode($data['lines']);
            $geometryEngine = $geometryEngine ?? new PdoEngine(DB::connection()->getPdo());
            $checkIntersects = $checkIntersects ?? new CheckIntersects($geometryEngine);
            $checkWithin = $checkWithin ?? new CheckWithin($geometryEngine);

            foreach ($lines as $lineObject) {
                /** @var \Brick\Geo\LineString $line */
                $line = (new GeoJsonReader)->read(json_encode($lineObject));

                /** @var Collection<\App\Models\Municipality> $items */
                $items = $checkIntersects->checkIntersectsWithModels($line);
                $startModel = $checkIntersects->checkIntersectsWithModels($line->startPoint());
                $endModel = $checkIntersects->checkIntersectsWithModels($line->endPoint());

                $lineData = [
                    'items' => array_values($items->select(['brk_identification', 'name'])->toArray()),
                    'within' => null,
                    'start' => null,
                    'end' => null,
                    'start_end_equal' => null,
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
                    $lineData['start_end_equal'] = $startModel->first()->id == $endModel->first()->id;
                }

                $responseData['lines'][] = $lineData;

                $responseData = $this->updateResponseDataItems($responseData, $items, ['all.items']);
                $responseData = $this->updateResponseDataWithin($responseData, fn () => $checkWithin->checkWithinAllGeometriesFromModels($line), ['all.within']);

                // Update the within value for the current line in the lines array
                $lastIndex = count($responseData['lines']) - 1;
                $responseData['lines'][$lastIndex]['within'] = $checkWithin->checkWithinAllGeometriesFromModels($line);
            }

            // If lines contains only 1 item, also set it as the single line response
            if (count($responseData['lines']) === 1) {
                $responseData['line'] = [
                    'items' => collect($responseData['lines'][0]['items']),
                    'within' => $responseData['lines'][0]['within'],
                    'start' => $responseData['lines'][0]['start'],
                    'end' => $responseData['lines'][0]['end'],
                    'start_end_equal' => $responseData['lines'][0]['start_end_equal'],
                ];
            }
        }

        if (isset($data['addresses'])) {
            $adressen = json_decode($data['addresses'], true);

            foreach ($adressen as $adres) {
                $responseData = $this->handleSingleAdres($adres, $responseData);
            }
        }

        if (isset($data['address'])) {
            $adres = json_decode($data['address'], true);
            $responseData = $this->handleSingleAdres($adres, $responseData);
        }

        $responseData['all']['object'] = $this->getObjectFromItems(Arr::get($responseData, 'all.items'));

        $responseData['all']['items'] = array_values($responseData['all']['items']->toArray());

        return response()->json([
            'data' => $responseData,
        ]);
    }

    private function handleSingleAdres(array $adres, array $responseData): array
    {
        $brkIdentificatie = (new LocatieserverService)->getBrkIdentificationByPostcodeHuisnummer($adres['postcode'], $adres['houseNumber']);
        $municipality = $brkIdentificatie ? Municipality::where('brk_identification', $brkIdentificatie)->select(['brk_identification', 'name'])->first() : null;
        if ($municipality) {
            $responseData = $this->updateResponseDataItems($responseData, collect([$municipality]), ['all.items', 'addresses.items']);
            $responseData = $this->updateResponseDataWithin($responseData, fn () => true, ['addresses.within', 'all.within']);
        } else {
            $responseData = $this->updateResponseDataWithin($responseData, fn () => false, ['addresses.within', 'all.within']);
        }

        return $responseData;
    }

    private function updateResponseDataItems(array $responseData, Collection $items, array $paths, array $fields = ['brk_identification', 'name'], string $unique = 'brk_identification'): array
    {
        foreach ($paths as $path) {
            if (Arr::get($responseData, $path) instanceof Collection) {
                Arr::set($responseData, $path, Arr::get($responseData, $path)->merge($items->select($fields))->unique($unique));
            }
        }

        return $responseData;
    }

    private function updateResponseDataWithin(array $responseData, callable $checkWithin, array $paths): array
    {
        foreach ($paths as $path) {
            if (Arr::get($responseData, $path) === null || Arr::get($responseData, $path) === true) {
                Arr::set($responseData, $path, $checkWithin());
            }
        }

        return $responseData;
    }

    // TODO Michel make this cleaner later
    private function getObjectFromItems(Collection $items): ?array
    {
        if ($items->isEmpty()) {
            return null;
        }

        $response = [];
        foreach ($items as $item) {
            $response[$item['brk_identification']] = [
                'brk_identification' => $item['brk_identification'],
                'name' => $item['name'],
            ];
        }

        return $response;
    }
}
