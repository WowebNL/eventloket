<?php

declare(strict_types=1);

namespace App\Support\Geo;

use App\Support\Uploads\DocumentUploadType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use XMLReader;

/**
 * Reads a GPX document into a GeoJSON FeatureCollection of LineStrings.
 *
 * Every track segment (`<trkseg>`) and every route (`<rte>`) becomes one
 * LineString feature, so a file holding several tracks or segments produces
 * several features in one collection.
 *
 * Only the geometry is taken over. Track names, descriptions, comments,
 * timestamps, elevations and any other element or attribute in the document
 * are ignored: the collection this parser returns is written into the form
 * state and rendered on a map, and nothing in it should come from the file
 * except the coordinates the route is made of.
 *
 * Three things keep the XML side narrow:
 *
 *  - the document is first put through the same content check the upload
 *    validation uses ({@see DocumentUploadType::looksLikeGpx()}), so this
 *    parser can never accept a file the upload rule rejects;
 *  - a document type declaration ends the parse. The upload rule refuses one
 *    too, but it does so by looking at the leading bytes of the file, which
 *    says nothing about the rest of it. The reader meets the declaration
 *    wherever it stands, so this is the check that actually holds;
 *  - external entity loading is disabled for the duration of the parse, so
 *    the parser resolves nothing outside the document;
 *  - the reader runs with LIBXML_NONET, so libxml performs no network access.
 */
final class GpxParser
{
    /**
     * Hard ceiling on the number of position elements read from one document.
     *
     * Uploads are capped at 60 MB, which is room for well over a million
     * track points; decoding all of them into a PHP array would cost far more
     * memory than the Livewire round trip that triggers the parse can spend.
     * A route recorded at one point per second reaches this ceiling after
     * roughly 28 hours, so no realistic event route runs into it.
     */
    public const MAX_POSITIONS = 100000;

    /**
     * Elements whose `lat`/`lon` attributes make up a line.
     *
     * Waypoints (`<wpt>`) are deliberately absent: they are standalone points
     * rather than part of the route's course.
     */
    private const POSITION_ELEMENTS = ['trkpt', 'rtept'];

    /**
     * Elements that open and close one line.
     */
    private const SEGMENT_ELEMENTS = ['trkseg', 'rte'];

    /**
     * @return array<string, mixed>|null A GeoJSON FeatureCollection, or null
     *                                   when the file is not a usable GPX route.
     */
    public static function fromUploadedFile(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '') {
            return null;
        }

        return self::fromPath($path);
    }

    /**
     * @param  string  $path  Absolute filesystem path of the GPX document.
     * @return array<string, mixed>|null A GeoJSON FeatureCollection, or null
     *                                   when the file is not a usable GPX route.
     */
    public static function fromPath(string $path): ?array
    {
        // Second line of defence, not a way around the first: a document the
        // upload rule refuses must not become a route here either.
        if (! DocumentUploadType::looksLikeGpx($path)) {
            return null;
        }

        $lines = self::readLines($path);

        if ($lines === null || $lines === []) {
            return null;
        }

        $features = [];

        foreach ($lines as $line) {
            $features[] = [
                'type' => 'Feature',
                'properties' => new \stdClass,
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $line,
                ],
            ];
        }

        return [
            'type' => 'FeatureCollection',
            'features' => $features,
        ];
    }

    /**
     * Walk the document and collect one list of positions per segment.
     *
     * @return list<list<array{0: float, 1: float}>>|null
     */
    private static function readLines(string $path): ?array
    {
        $reader = new XMLReader;

        // Resolve nothing outside the document while this file is being read.
        libxml_set_external_entity_loader(static fn (): null => null);
        $previousInternalErrors = libxml_use_internal_errors(true);

        try {
            if (@$reader->open($path, null, LIBXML_NONET) !== true) {
                return null;
            }

            return self::walk($reader);
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
            libxml_set_external_entity_loader(null);
        }
    }

    /**
     * @return list<list<array{0: float, 1: float}>>|null
     */
    private static function walk(XMLReader $reader): ?array
    {
        /** @var list<list<array{0: float, 1: float}>> $lines */
        $lines = [];
        /** @var list<array{0: float, 1: float}> $current */
        $current = [];
        $inSegment = false;
        $positions = 0;

        while (@$reader->read()) {
            if ($reader->nodeType === XMLReader::DOC_TYPE) {
                // GPX carries no document type declaration, and the check in
                // front of this parser can only see the start of the file.
                // Stop here, where the declaration is visible wherever it
                // stands in the document.
                return null;
            }

            $name = strtolower($reader->localName);

            if ($reader->nodeType === XMLReader::ELEMENT && in_array($name, self::SEGMENT_ELEMENTS, true)) {
                $inSegment = true;
                $current = [];

                // A self-closing segment element never reaches END_ELEMENT.
                if ($reader->isEmptyElement) {
                    $inSegment = false;
                }

                continue;
            }

            if ($reader->nodeType === XMLReader::END_ELEMENT && in_array($name, self::SEGMENT_ELEMENTS, true)) {
                $inSegment = false;

                if (count($current) >= 2) {
                    $lines[] = $current;
                }

                $current = [];

                continue;
            }

            if ($reader->nodeType !== XMLReader::ELEMENT || ! in_array($name, self::POSITION_ELEMENTS, true)) {
                continue;
            }

            // Count every position element the reader meets, not only the
            // usable ones. A document made of millions of unusable ones costs
            // the same walk, and that walk happens inside a request.
            $positions++;

            if ($positions > self::MAX_POSITIONS) {
                return null;
            }

            $position = self::positionFrom($reader);

            if ($position === null) {
                continue;
            }

            if ($inSegment) {
                $current[] = $position;

                continue;
            }

            // A track point outside any segment is not schema-valid GPX; it
            // carries no line of its own, so it is dropped rather than turned
            // into a one-point geometry the geometry engine cannot read.
        }

        return $lines;
    }

    /**
     * @return array{0: float, 1: float}|null GeoJSON position (longitude, latitude).
     */
    private static function positionFrom(XMLReader $reader): ?array
    {
        $latitude = $reader->getAttribute('lat');
        $longitude = $reader->getAttribute('lon');

        if (! is_string($latitude) || ! is_string($longitude)) {
            return null;
        }

        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90.0 || $latitude > 90.0 || $longitude < -180.0 || $longitude > 180.0) {
            return null;
        }

        // GeoJSON orders a position as longitude, latitude.
        return [$longitude, $latitude];
    }
}
