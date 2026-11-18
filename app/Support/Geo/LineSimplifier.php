<?php

declare(strict_types=1);

namespace App\Support\Geo;

/**
 * Reduces a line to a point budget while keeping its course.
 *
 * The municipality check runs `intersects` once per municipality with a
 * geometry, synchronously inside the Livewire round trip that follows every
 * change to a location field. The cost of that loop grows with the number of
 * positions in the line, and a recorded route carries thousands of them where
 * a hand-drawn one carries tens.
 *
 * This is a separate step on purpose: only the municipality check works on the
 * reduced line. The route that is shown, stored and handed to the case system
 * stays the full one.
 *
 * The reduction keeps a position whenever it lies further than a tolerance
 * from the last position that was kept, and raises that tolerance until the
 * line fits the budget. Every dropped position therefore lies within the
 * tolerance of a kept one, which bounds how far the reduced line can stray
 * from the original, and each pass costs one walk over the line. A shape-based
 * method (Ramer-Douglas-Peucker) gives a slightly better line for the same
 * budget, but its worst case is quadratic, and a route of tens of thousands of
 * positions arrives here straight from an upload.
 */
final class LineSimplifier
{
    /**
     * Point budget per line for the municipality check.
     *
     * A municipality boundary is a coarse shape compared to a recorded route:
     * the answer to "does this line cross this municipality" does not change
     * when the line is thinned from thousands of positions to hundreds, while
     * the time the check takes does. See the measurement in the pull request.
     */
    public const MAX_POSITIONS = 500;

    /**
     * Floor under the tolerance, in degrees.
     *
     * Roughly one metre at Dutch latitudes, so even a route that covers almost
     * no ground still drops the dense clusters a GPS recording produces while
     * standing still.
     */
    private const INITIAL_TOLERANCE = 0.00001;

    /**
     * Upper bound on the doubling steps, so a line that resists thinning
     * cannot spin here. From the floor above this reaches well beyond the
     * width of the country.
     */
    private const MAX_TOLERANCE_STEPS = 32;

    /**
     * Reduce every line in a decoded GeoJSON geometry to the point budget.
     *
     * Geometries that are not lines, and lines that already fit, are returned
     * unchanged.
     *
     * @param  array<string, mixed>  $geometry  A decoded GeoJSON geometry object.
     * @return array<string, mixed>
     */
    public static function simplifyGeometry(array $geometry, int $maxPositions = self::MAX_POSITIONS): array
    {
        $type = $geometry['type'] ?? null;
        $coordinates = $geometry['coordinates'] ?? null;

        if (! is_array($coordinates)) {
            return $geometry;
        }

        if ($type === 'LineString') {
            $geometry['coordinates'] = self::simplify(array_values($coordinates), $maxPositions);

            return $geometry;
        }

        if ($type === 'MultiLineString') {
            $lines = [];

            foreach ($coordinates as $line) {
                $lines[] = is_array($line)
                    ? self::simplify(array_values($line), $maxPositions)
                    : $line;
            }

            $geometry['coordinates'] = $lines;

            return $geometry;
        }

        return $geometry;
    }

    /**
     * @param  list<mixed>  $positions
     * @return list<mixed>
     */
    public static function simplify(array $positions, int $maxPositions = self::MAX_POSITIONS): array
    {
        $maxPositions = max(2, $maxPositions);

        if (count($positions) <= $maxPositions) {
            return $positions;
        }

        $tolerance = self::startingTolerance($positions, $maxPositions);

        for ($step = 0; $step < self::MAX_TOLERANCE_STEPS; $step++) {
            $reduced = self::thin($positions, $tolerance);

            if (count($reduced) <= $maxPositions) {
                return $reduced;
            }

            $tolerance *= 2;
        }

        // Unreachable for any line with real coordinates, but a budget is a
        // budget: fall back to evenly spaced positions with both ends kept.
        return self::sample($positions, $maxPositions);
    }

    /**
     * A first guess at the tolerance, so the doubling search does not have to
     * climb from one metre to the size of a route every time.
     *
     * Spreading the budget evenly over the line's bounding box gives the order
     * of magnitude; a quarter of that is a deliberate under-estimate, so the
     * search approaches the budget from below and never thins further than it
     * has to.
     *
     * @param  list<mixed>  $positions
     */
    private static function startingTolerance(array $positions, int $maxPositions): float
    {
        $minX = $minY = INF;
        $maxX = $maxY = -INF;

        foreach ($positions as $position) {
            $point = self::xy($position);

            if ($point === null) {
                continue;
            }

            $minX = min($minX, $point[0]);
            $maxX = max($maxX, $point[0]);
            $minY = min($minY, $point[1]);
            $maxY = max($maxY, $point[1]);
        }

        if ($minX === INF) {
            return self::INITIAL_TOLERANCE;
        }

        $span = hypot($maxX - $minX, $maxY - $minY);

        return max(self::INITIAL_TOLERANCE, $span / ($maxPositions * 4));
    }

    /**
     * Keep the first position, then every position further than the tolerance
     * from the last one kept, then the last position.
     *
     * The start and the end are never dropped: the municipality check reads
     * them to work out which municipality the route starts and ends in.
     *
     * @param  list<mixed>  $positions
     * @return list<mixed>
     */
    private static function thin(array $positions, float $tolerance): array
    {
        $last = count($positions) - 1;

        $result = [$positions[0]];
        $anchor = self::xy($positions[0]);

        for ($index = 1; $index < $last; $index++) {
            $position = self::xy($positions[$index]);

            if ($position === null || $anchor === null) {
                // An unreadable position carries no measurable distance. Keep
                // it rather than drop it silently, so the geometry validator
                // still gets to reject the line it belongs to.
                $result[] = $positions[$index];
                $anchor = $position;

                continue;
            }

            if (hypot($position[0] - $anchor[0], $position[1] - $anchor[1]) < $tolerance) {
                continue;
            }

            $result[] = $positions[$index];
            $anchor = $position;
        }

        $result[] = $positions[$last];

        return $result;
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private static function xy(mixed $position): ?array
    {
        if (! is_array($position)) {
            return null;
        }

        $x = $position[0] ?? null;
        $y = $position[1] ?? null;

        if (! is_numeric($x) || ! is_numeric($y)) {
            return null;
        }

        return [(float) $x, (float) $y];
    }

    /**
     * @param  list<mixed>  $positions
     * @return list<mixed>
     */
    private static function sample(array $positions, int $maxPositions): array
    {
        $last = count($positions) - 1;
        $result = [];

        for ($index = 0; $index < $maxPositions - 1; $index++) {
            $result[] = $positions[(int) floor($index * $last / ($maxPositions - 1))];
        }

        $result[] = $positions[$last];

        return $result;
    }
}
