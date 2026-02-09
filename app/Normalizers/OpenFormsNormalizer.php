<?php

namespace App\Normalizers;

use stdClass;

class OpenFormsNormalizer
{
    public static function normalizeJson(string $value): ?string
    {
        // incomming json is not valid so make it valid
        $value = self::normalizeSingleQuotes($value);

        return json_validate($value) ? $value : null;
    }

    /**
     * Normalize single quotes in JSON strings to prevent parsing errors.
     * Protects apostrophes in words while converting JSON delimiters.
     */
    public static function normalizeSingleQuotes(string $value): string
    {
        // If the JSON uses single quotes instead of double quotes for strings,
        // we need to handle apostrophes within those strings
        // Replace single quotes with a temporary placeholder
        $placeholder = '___APOSTROPHE___';

        // Protect apostrophes that are part of words:

        // 0. Double quotes at start (e.g., ''t schip' becomes "'t schip")
        //    Match: two apostrophes followed by a word character
        //    Keep the second apostrophe as it's part of the word
        $protected = preg_replace("/\'\'(\w)/u", "'{$placeholder}$1", $value);

        // 1. Standalone contractions after a space (e.g., 'n, 't, 's)
        //    Match: space + apostrophe + 1-2 word-chars + word boundary
        $protected = preg_replace("/(\s)\'(\w{1,2})\b/u", "$1{$placeholder}$2", $protected);

        // 2. Apostrophe within or at start of compound words (e.g., d'n, O'Brien, 's-Hertogenbosch)
        //    Match: word-chars (optional) + apostrophe + word-chars, within word boundaries
        $protected = preg_replace("/\b(\w*)\'(\w+)\b/u", "$1{$placeholder}$2", $protected);

        // 3. Apostrophe at the end of a word (e.g., boys', James')
        //    Match: word-chars + apostrophe + word boundary
        $protected = preg_replace("/\b(\w+)\'\b/u", "$1{$placeholder}", $protected);

        // Now replace ALL remaining single quotes with double quotes (these are JSON delimiters)
        $normalized = str_replace("'", '"', $protected);

        // Restore the apostrophes within words
        return str_replace($placeholder, "'", $normalized);
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
