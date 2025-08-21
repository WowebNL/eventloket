<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class NormalizeOpenformsInput
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($value = $request->header('X-OpenForms-Normalize')) {
            $fields = explode(',', $value);

            foreach ($fields as $field) {
                if ($request->has($field)) {
                    $value = $request->input($field);

                    // param sometimes is 'None' when empty
                    if ($value === 'None') {
                        $request->merge([$field => null]);

                        continue;
                    }

                    $value = $this->normalizeJson($value);

                    if (str_contains($value, '"coordinates"')) {
                        $value = $this->normalizeGeoJson($value);
                    } elseif (preg_match('(postcode|houseNumber|houseLetter|city|streetName)', $value) === 1) {
                        $value = $this->normalizeAddress($value);
                    }

                    $request->merge([$field => $value]);
                }
            }
        }

        return $next($request);
    }

    private function normalizeJson(string $value): ?string
    {
        // incomming json is not valid so make it valid
        $value = str_replace('\'', '"', $value);

        return json_validate($value) ? $value : null;
    }

    private function normalizeGeoJson(string $value): string
    {
        $value = json_decode($value);

        // check if value is an array, if so it contains multiple geojsons
        if (is_array($value)) {
            foreach ($value as $key => &$item) {
                // the object key in an array is the name of the input field it is comming from, we dont need that
                $item = reset($item);
                $item = $this->normalizeCoordinates($item);
            }
        } elseif (is_object($value)) {
            $value = $this->normalizeCoordinates($value);
        }

        return json_encode($value);
    }

    private function normalizeAddress(string $value): string
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

    /**
     * Format coordinates in a GeoJSON string to floats if it are strings
     */
    private function normalizeCoordinates(stdClass $object): stdClass
    {
        if (isset($object->coordinates)) {
            array_walk_recursive($object->coordinates, function (&$item) {
                $item = is_string($item) ? floatval($item) : $item;
            });
        }

        return $object;
    }
}
