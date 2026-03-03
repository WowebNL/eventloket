<?php

namespace App\Normalizers;

use stdClass;

class OpenFormsNormalizer
{
    public static function normalizeJson(string $value): ?string
    {
        $value = self::normalizeSingleQuotes($value);

        return json_validate($value) ? $value : null;
    }

    /**
     * Normalize single quotes in JSON strings to prevent parsing errors.
     * Protects apostrophes in words while converting JSON delimiters.
     */
    public static function normalizeSingleQuotes(string $value): string
    {
        // Convert single-quoted JSON strings to double-quoted JSON strings,
        // while keeping apostrophes that are part of words.
        $result = '';
        $inSingleString = false;
        $inDoubleString = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($inDoubleString) {
                if ($char === '\\' && $i + 1 < $length) {
                    $result .= $char.$value[$i + 1];
                    $i++;

                    continue;
                }

                if ($char === '"') {
                    $inDoubleString = false;
                }

                $result .= $char;

                continue;
            }

            if (! $inSingleString) {
                if ($char === "'") {
                    $inSingleString = true;
                    $result .= '"';

                    continue;
                }

                if ($char === '"') {
                    $inDoubleString = true;
                    $result .= $char;

                    continue;
                }

                $result .= $char;

                continue;
            }

            if ($char === "'") {
                $prev = $i > 0 ? $value[$i - 1] : '';
                $next = $i + 1 < $length ? $value[$i + 1] : '';
                $prevIsWord = $prev !== '' && preg_match('/[A-Za-z0-9]/', $prev);
                $nextIsWord = $next !== '' && preg_match('/[A-Za-z0-9]/', $next);

                // Treat as apostrophe if it looks like part of a word (e.g., d'n, O'Brien, ''t).
                if ($nextIsWord && ($prevIsWord || $prev === "'" || $prev === '')) {
                    $result .= "'";

                    continue;
                }

                $inSingleString = false;
                $result .= '"';

                continue;
            }

            if ($char === '"') {
                $result .= '\\"';

                continue;
            }

            $result .= $char;
        }

        if ($inSingleString) {
            $result .= '"';
        }

        return $result;
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
