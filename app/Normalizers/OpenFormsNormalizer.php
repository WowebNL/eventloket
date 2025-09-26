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
                // the object key in an array is the name of the input field it is comming from, we dont need that
                $item = reset($item);
                $item = self::normalizeCoordinates($item);
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
                // the object key in an array is the name of the input field it is comming from, we dont need that
                $item = reset($item);
            }
        }

        return json_encode($value);
    }
}
