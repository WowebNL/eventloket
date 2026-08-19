<?php

declare(strict_types=1);

namespace App\EventForm\Submit;

use App\Normalizers\OpenFormsNormalizer;
use App\Services\LocatieserverService;
use App\Support\Helpers\ArrayHelper;
use App\ValueObjects\Pdok\BagObject;
use Brick\Geo\Geometry;
use Brick\Geo\GeometryCollection;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\Io\GeoJsonWriter;
use Brick\Geo\Point;

/**
 * Bouwt GeoJSON-geometrie en BAG-adressen voor ZGW uit een event-location-
 * array. Vervangt de gelijknamige logica die eerder op `FormSubmissionObject`
 * leefde — dezelfde code, alleen nu met een externe input-array zodat 'ie
 * los staat van Objects API.
 */
final class EventLocationGeometryBuilder
{
    /** @var list<BagObject> */
    private array $collectedAddresses = [];

    public function __construct(private readonly LocatieserverService $locationService) {}

    /**
     * @param  array<string, mixed>  $eventLocation
     */
    public function buildGeoJson(array $eventLocation): ?string
    {
        $this->collectedAddresses = [];

        if ($eventLocation === []) {
            return null;
        }

        $geometries = [];

        if ($this->notEmpty($eventLocation['line'] ?? null)) {
            foreach (self::parseLines($eventLocation['line']) as $geometry) {
                $geometries[] = $geometry;
            }
        }

        if ($this->notEmpty($eventLocation['multipolygons'] ?? null)) {
            foreach (self::parseMultipolygons($eventLocation['multipolygons']) as $geometry) {
                $geometries[] = $geometry;
            }
        }

        if ($this->notEmpty($eventLocation['bag_addresses'] ?? null)) {
            foreach ($this->parseBagAddresses($eventLocation['bag_addresses']) as $geometry) {
                $geometries[] = $geometry;
            }
        }

        if ($this->notEmpty($eventLocation['bag_address'] ?? null)) {
            if ($geometry = $this->parseBagAddress($eventLocation['bag_address'])) {
                $geometries[] = $geometry;
            }
        }

        if ($geometries === []) {
            return null;
        }

        return (new GeoJsonWriter)->write(GeometryCollection::of(...$geometries));
    }

    /**
     * @return list<BagObject>
     */
    public function collectedAddresses(): array
    {
        return $this->collectedAddresses;
    }

    private function notEmpty(mixed $value): bool
    {
        return ! empty($value) && $value !== 'None';
    }

    /**
     * Take ALL LineString geometries from the route input.
     *
     * Public and static on purpose: everything that has to reason about the
     * drawn routes (the submit flow itself, but also `CreateDoorkomstZaken`)
     * must read them with exactly the same parser, so no caller ends up
     * looking at the first route only.
     *
     * @return list<Geometry>
     */
    public static function parseLines(mixed $line): array
    {
        return self::parseGeometries(
            $line,
            OpenFormsNormalizer::normalizeGeoJson(...),
            'routeVanHetEvenement',
        );
    }

    /**
     * Take ALL polygon geometries from the location input.
     *
     * @return list<Geometry>
     */
    private static function parseMultipolygons(mixed $multipolygons): array
    {
        return self::parseGeometries(
            $multipolygons,
            OpenFormsNormalizer::normalizeJson(...),
            'buitenLocatieVanHetEvenement',
        );
    }

    /**
     * Shared parser for lines and polygons: both arrive as identically
     * shaped Map state, only the normalizer and the Repeater row key
     * differ. Two shapes are supported:
     *
     *  1. Current: one Map state object with several features in
     *     `geojson.features[]`. Since the Repeater was dropped, a single
     *     map can hold several routes/polygons.
     *  2. Old (Repeater rows): `[{<candidateKey>: {...}}, ...]` —
     *     backward compatibility for existing drafts.
     *
     * In both cases `features[].geometry` is taken; if that is missing we
     * fall back to a recursive search for `coordinates` (pre-Map state
     * shapes from the old OF flow).
     *
     * @param  callable(string): ?string  $normalizer  turns a raw string payload into valid JSON
     * @param  string  $candidateKey  Repeater row key for the old shape
     * @return list<Geometry>
     */
    private static function parseGeometries(mixed $input, callable $normalizer, string $candidateKey): array
    {
        $json = is_array($input) ? json_encode($input) : $normalizer($input);
        $decoded = json_decode((string) $json, true);
        if (! is_array($decoded)) {
            return [];
        }

        // Verzamel alle Map-states die we tegenkomen (één in nieuwe shape,
        // N in de oude shape).
        $mapStates = isset($decoded['geojson'])
            ? [$decoded]
            : array_values(array_filter($decoded, static fn ($row) => is_array($row)));

        $out = [];
        foreach ($mapStates as $mapState) {
            $candidate = is_array($mapState) ? ($mapState[$candidateKey] ?? $mapState) : null;
            if (! is_array($candidate)) {
                continue;
            }
            $features = $candidate['geojson']['features'] ?? null;
            if (! is_array($features)) {
                // Fallback voor pre-Map state-shapes (bv. wanneer OF nog
                // een platte geometrie in de FormState had staan).
                $array = ArrayHelper::findElementWithKey($candidate, 'coordinates');
                if ($array) {
                    $out[] = (new GeoJsonReader)->read((string) json_encode($array));
                }

                continue;
            }
            foreach ($features as $feature) {
                $geometry = is_array($feature) ? ($feature['geometry'] ?? null) : null;
                if (! is_array($geometry) || ! isset($geometry['type'], $geometry['coordinates'])) {
                    continue;
                }
                $out[] = (new GeoJsonReader)->read((string) json_encode($geometry));
            }
        }

        return $out;
    }

    /**
     * @return list<Geometry>
     */
    private function parseBagAddresses(mixed $bagAddresses): array
    {
        $json = is_array($bagAddresses) ? json_encode($bagAddresses) : OpenFormsNormalizer::normalizeJson($bagAddresses);
        $decoded = json_decode((string) $json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $entry) {
            $array = is_array($entry) ? ArrayHelper::findElementWithKey($entry, 'postcode') : null;
            if (! $array) {
                continue;
            }
            if ($geometry = $this->geometryFromAddress($array)) {
                $out[] = $geometry;
            }
        }

        return $out;
    }

    private function parseBagAddress(mixed $bagAddress): ?Geometry
    {
        $json = is_array($bagAddress) ? json_encode($bagAddress) : OpenFormsNormalizer::normalizeJson($bagAddress);
        $array = json_decode((string) $json, true);
        if (! is_array($array)) {
            return null;
        }

        return $this->geometryFromAddress($array);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function geometryFromAddress(array $address): ?Geometry
    {
        $bagObject = $this->locationService->getBagObjectByPostcodeHuisnummer(
            (string) ($address['postcode'] ?? ''),
            (string) ($address['houseNumber'] ?? $address['huisnummer'] ?? ''),
            isset($address['houseLetter']) ? (string) $address['houseLetter'] : null,
            isset($address['houseNumberAddition']) ? (string) $address['houseNumberAddition'] : null,
        );

        if (! $bagObject) {
            return null;
        }

        $this->collectedAddresses[] = $bagObject;

        return Point::fromText($bagObject->centroide_ll, 4326);
    }
}
