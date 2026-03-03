<?php

namespace App\Support\Helpers;

class ArrayHelper
{
    /**
     * Recursively search through an array to find the first element containing a specific key.
     *
     * @param  mixed  $data  The array to search through
     * @param  string  $key  The key to search for
     * @return array|null The first element containing the key, or null if not found
     */
    public static function findElementWithKey(mixed $data, string $key): ?array
    {
        if (! is_array($data)) {
            return null;
        }

        // Check if current level has the key
        if (isset($data[$key])) {
            return $data;
        }

        // Search recursively in all array elements
        foreach ($data as $element) {
            if (is_array($element)) {
                $result = self::findElementWithKey($element, $key);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }
}
