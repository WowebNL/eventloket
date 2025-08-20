<?php

namespace App\Http\Controllers\Api;

use App\Actions\Geospatial\CheckIntersects;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocationServerCheckRequest;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\Polygon;

class LocationServerController extends Controller
{
    public function check(LocationServerCheckRequest $request)
    {
        $data = $request->validated();
        $municipalities = collect();

        if (isset($data['polygons'])) {
            $polygons = json_decode($data['polygons']);
            foreach ($polygons as $object) {
                /** @var Polygon $polygon */
                $polygon = (new GeoJsonReader)->read(json_encode($object));

                /** @var array<\App\Models\Municipality> $items */
                $items = (new CheckIntersects)->checkIntersectsWithModels($polygon);

                $municipalities = $municipalities->merge($items);
            }
        }

        if (isset($data['line'])) {
            $line = (new GeoJsonReader)->read($data['line']);
            $items = (new CheckIntersects)->checkIntersectsWithModels($line);
            $$municipalities = $municipalities->merge($items);
        }

        if (isset($data['addresses'])) {
            $adressen = json_decode($data['addresses'], true);
            // TODO get gemeente by adres
        }
        // TODO return resource
    }
}
