<?php

namespace App\Normalizers;

use stdClass;

class OpenFormsNormalizer
{
    public static function normalizeJson(string $value): ?string
    {
        // incomming json is not valid so make it valid
        $value = str_replace('\'', '"', $value);

        return json_validate($value) ? $value : null;
    }

    public static function normalizeCoordinates(stdClass $object): stdClass
    {
        if (isset($object->coordinates)) {
            array_walk_recursive($object->coordinates, function (&$item) {
                $item = is_string($item) ? floatval($item) : $item;
            });
        }

        return $object;
    }

    public static function normalizeGeoJson(string $value): string
    {
        $value = json_decode($value);

        // check if value is an array, if so it contains multiple geojsons
        if (is_array($value)) {
            foreach ($value as $key => &$item) {
                // item is an array which contains multiple items, we only need the item with a coordinates key
                $item = collect($item)->first(fn ($element) => isset($element->coordinates));
                if ($item) {
                    $item = self::normalizeCoordinates($item);
                }
            }
        } elseif (is_object($value)) {
            $value = self::normalizeCoordinates($value);
        }

        return json_encode($value);
    }

    public static function normalizeAddress(string $value): string
    {
        $value = json_decode($value);
        if (is_array($value)) {
            foreach ($value as $key => &$item) {
                // item is an array which contains multiple items, we only need the item with a postcode key
                $item = collect($item)->first(fn ($element) => isset($element->postcode));
            }
        }

        return json_encode($value);
    }
}
