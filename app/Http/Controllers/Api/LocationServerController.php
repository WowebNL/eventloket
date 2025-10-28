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
            ],
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
                /** @var Polygon $polygon */
                $polygon = (new GeoJsonReader)->read(json_encode($object));

                /** @var Collection<\App\Models\Municipality> $items */
                $items = $checkIntersects->checkIntersectsWithModels($polygon);

                $responseData = $this->updateResponseDataItems($responseData, $items, ['all.items', 'polygons.items']);
                $responseData = $this->updateResponseDataWithin($responseData, fn () => $checkWithin->checkWithinAllGeometriesFromModels($polygon), ['polygons.within', 'all.within']);
            }
        }

        if (isset($data['line'])) {
            $line = (new GeoJsonReader)->read($data['line']);
            $geometryEngine = $geometryEngine ?? new PdoEngine(DB::connection()->getPdo());
            $checkIntersects = $checkIntersects ?? new CheckIntersects($geometryEngine);
            $checkWithin = $checkWithin ?? new CheckWithin($geometryEngine);

            /** @var Collection<\App\Models\Municipality> $items */
            $items = $checkIntersects->checkIntersectsWithModels($line);

            $responseData = $this->updateResponseDataItems($responseData, $items, ['all.items', 'line.items']);
            $responseData = $this->updateResponseDataWithin($responseData, fn () => $checkWithin->checkWithinAllGeometriesFromModels($line), ['line.within', 'all.within']);
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
