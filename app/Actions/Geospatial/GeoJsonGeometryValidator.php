<?php

declare(strict_types=1);

namespace App\Actions\Geospatial;

/**
 * Validates that a decoded GeoJSON geometry is complete enough for the
 * geometry engine (PostGIS) to process.
 *
 * A user drawing on the map can produce a degenerate geometry, for example a
 * polygon whose ring has fewer than four positions (an unfinished shape). The
 * live location check would then send it to PostGIS, which rejects it with
 * "Polygon must have at least four points in each ring" and the whole Livewire
 * update fails with a 500. Skipping such geometries up front avoids that.
 */
final class GeoJsonGeometryValidator
{
    /**
     * A closed polygon ring needs at least four positions
     * (three distinct corners plus the closing position).
     */
    private const MIN_RING_POSITIONS = 4;

    /**
     * A line needs at least two positions to have a start and an end.
     */
    private const MIN_LINE_POSITIONS = 2;

    /**
     * @param  array<string, mixed>  $geometry  A decoded GeoJSON geometry object.
     */
    public static function isProcessable(array $geometry): bool
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_string($type) || ! is_array($coordinates)) {
            return false;
        }

        return match ($type) {
            'Polygon' => self::ringsAreValid($coordinates),
            'MultiPolygon' => $coordinates !== [] && array_all(
                $coordinates,
                fn (mixed $polygon): bool => is_array($polygon) && self::ringsAreValid($polygon),
            ),
            'LineString' => self::hasAtLeast($coordinates, self::MIN_LINE_POSITIONS),
            'MultiLineString' => $coordinates !== [] && array_all(
                $coordinates,
                fn (mixed $line): bool => is_array($line) && self::hasAtLeast($line, self::MIN_LINE_POSITIONS),
            ),
            default => $coordinates !== [],
        };
    }

    /**
     * @param  array<int|string, mixed>  $rings
     */
    private static function ringsAreValid(array $rings): bool
    {
        return $rings !== [] && array_all(
            $rings,
            fn (mixed $ring): bool => is_array($ring) && self::hasAtLeast($ring, self::MIN_RING_POSITIONS),
        );
    }

    /**
     * @param  array<int|string, mixed>  $positions
     */
    private static function hasAtLeast(array $positions, int $minimum): bool
    {
        return count($positions) >= $minimum;
    }
}
