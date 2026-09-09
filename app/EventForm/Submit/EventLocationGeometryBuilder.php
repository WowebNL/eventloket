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

    private bool $unreachableLookups = false;

    public function __construct(private readonly LocatieserverService $locationService) {}

    /**
     * A copy of this builder that gives the address lookups the longer budget
     * meant for callers with nobody waiting on them.
     */
    public function forBackgroundWork(): self
    {
        return new self($this->locationService->forBackgroundWork());
    }

    /**
     * @param  array<string, mixed>  $eventLocation
     */
    public function buildGeoJson(array $eventLocation): ?string
    {
        $this->collectedAddresses = [];
        $this->unreachableLookups = false;

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

    /**
     * Did the last build lose an address because the location service could
     * not be reached? The result is then incomplete through no fault of the
     * input: the same build over the same input succeeds once the service is
     * back. A caller that can run again later should therefore discard this
     * result rather than store it, because a partial geometry is easily
     * mistaken for a complete one afterwards.
     */
    public function hadUnreachableLookups(): bool
    {
        return $this->unreachableLookups;
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
     * differ. Three shapes are supported:
     *
     *  1. Current: one Map state object with several features in
     *     `geojson.features[]`. Since the Repeater was dropped, a single
     *     map can hold several routes/polygons.
     *  2. Old (Repeater rows): `[{<candidateKey>: {...}}, ...]` —
     *     backward compatibility for existing drafts.
     *  3. Old (pre-Map): the state is a bare GeoJSON geometry.
     *
     * In the first two cases `features[].geometry` is taken; if that is
     * missing we fall back to a recursive search for `coordinates` (pre-Map
     * state shapes from the old OF flow).
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

        // A state that is itself a bare geometry is one geometry, not a list of
        // map states: it has to be recognised before the split below, which
        // would otherwise walk into its `coordinates` member and lose the type.
        if (isset($decoded['type'], $decoded['coordinates'])) {
            return [(new GeoJsonReader)->read((string) json_encode($decoded))];
        }

        // Collect every Map state present (one in the current shape, N in the
        // old one).
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
                // Fallback for pre-Map state shapes (for example a bare
                // geometry inside an old repeater row).
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
            if ($this->locationService->lastRequestWasUnreachable()) {
                $this->unreachableLookups = true;
            }

            return null;
        }

        $this->collectedAddresses[] = $bagObject;

        return Point::fromText($bagObject->centroide_ll, 4326);
    }
}
