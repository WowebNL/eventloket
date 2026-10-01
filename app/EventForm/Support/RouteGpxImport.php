<?php

declare(strict_types=1);

namespace App\EventForm\Support;

use App\Support\Geo\GpxParser;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Turns an uploaded GPX file into the value of the route map field.
 *
 * The map field's state is a GeoJSON FeatureCollection wrapped in
 * `{lat, lng, geojson}`, and everything that reads the route already reads
 * that state: the map renders it, the municipality check collects its lines,
 * the validation rules on the field weigh it, the draft stores it and the case
 * system hands it on. Writing the parsed GPX there is therefore the whole
 * integration. Drawing by hand stops being necessary because the field is
 * filled, not because a rule was relaxed.
 *
 * The value goes straight onto the route field rather than into a row of
 * something: the route question renders as one map that holds as many lines as
 * the organiser needs (see `LocatiePolygonsPatch`), so a file with several
 * tracks or segments becomes several features in that one collection.
 */
final class RouteGpxImport
{
    /** The form field holding the route geometry. */
    public const ROUTE_FIELD = 'routesOpKaart';

    /**
     * The freshly uploaded file in a file-upload field's state, if any.
     *
     * A resumed draft carries stored paths rather than upload objects; those
     * are files that were already imported when they were uploaded, so they
     * are left alone. Re-importing one would overwrite a route the organiser
     * has edited on the map since.
     */
    public static function uploadedFile(mixed $state): ?TemporaryUploadedFile
    {
        if ($state instanceof TemporaryUploadedFile) {
            return $state;
        }

        if (! is_array($state)) {
            return null;
        }

        foreach ($state as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Read a GPX file into the state of a route map field.
     *
     * @return array<string, mixed>|null `{lat, lng, geojson}`, or null when the
     *                                   file holds no usable route.
     */
    public static function mapStateFrom(TemporaryUploadedFile $file): ?array
    {
        $collection = GpxParser::fromUploadedFile($file);

        if ($collection === null) {
            return null;
        }

        $centre = self::centreOf($collection);

        if ($centre === null) {
            return null;
        }

        return [
            'lat' => $centre[1],
            'lng' => $centre[0],
            'geojson' => $collection,
        ];
    }

    /**
     * Centre of the collection's bounding box, so the map opens on the route.
     *
     * @param  array<string, mixed>  $collection
     * @return array{0: float, 1: float}|null Longitude, latitude.
     */
    private static function centreOf(array $collection): ?array
    {
        $features = $collection['features'] ?? null;

        if (! is_array($features)) {
            return null;
        }

        $minX = $minY = INF;
        $maxX = $maxY = -INF;

        foreach ($features as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $geometry = $feature['geometry'] ?? null;

            if (! is_array($geometry)) {
                continue;
            }

            $coordinates = $geometry['coordinates'] ?? null;

            if (! is_array($coordinates)) {
                continue;
            }

            foreach ($coordinates as $position) {
                if (! is_array($position) || ! is_numeric($position[0] ?? null) || ! is_numeric($position[1] ?? null)) {
                    continue;
                }

                $x = (float) $position[0];
                $y = (float) $position[1];

                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        if ($minX === INF) {
            return null;
        }

        return [($minX + $maxX) / 2, ($minY + $maxY) / 2];
    }
}
