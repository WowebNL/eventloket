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
            foreach ($this->parseLines($eventLocation['line']) as $geometry) {
                $geometries[] = $geometry;
            }
        }

        if ($this->notEmpty($eventLocation['multipolygons'] ?? null)) {
            foreach ($this->parseMultipolygons($eventLocation['multipolygons']) as $geometry) {
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
     * Pak ALLE LineString-geometrieën uit de input. Zelfde logica als
     * `parseMultipolygons` maar voor lijnen — twee shapes:
     *
     *  1. Nieuw: één Map-state-object met meerdere features in
     *     `geojson.features[]`. Sinds de Repeater eruit kunnen er
     *     meerdere routes op één kaart staan.
     *  2. Oud (Repeater-rows): `[{routeVanHetEvenement: {...}}, ...]`
     *     — backward-compat voor bestaande drafts.
     *
     * @return list<Geometry>
     */
    private function parseLines(mixed $line): array
    {
        $json = is_array($line) ? json_encode($line) : OpenFormsNormalizer::normalizeGeoJson($line);
        $decoded = json_decode((string) $json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $mapStates = isset($decoded['geojson'])
            ? [$decoded]
            : array_values(array_filter($decoded, static fn ($row) => is_array($row)));

        $out = [];
        foreach ($mapStates as $mapState) {
            $candidate = is_array($mapState) ? ($mapState['routeVanHetEvenement'] ?? $mapState) : null;
            if (! is_array($candidate)) {
                continue;
            }
            $features = $candidate['geojson']['features'] ?? null;
            if (! is_array($features)) {
                // Fallback voor pre-Map state-shapes (bv. wanneer OF nog
                // een platte LineString in de FormState had staan).
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
     * Pak ALLE polygon-geometrieën uit de input. Twee shapes mogelijk:
     *
     *  1. Nieuw: één Map-state-object `{lat, lng, geojson: {features: [...]}}`.
     *     Map ondersteunt multi-feature, dus features[] kan N polygonen
     *     bevatten — die we allemaal teruggeven.
     *  2. Oud (Repeater-rows): `[{...}, {...}]` met per rij een
     *     `buitenLocatieVanHetEvenement` Map-state. Backward-compat
     *     voor bestaande drafts.
     *
     * In beide gevallen pakken we `features[].geometry` — dat zijn
     * losstaande GeoJSON-shapes die `GeoJsonReader::read()` direct
     * inleest.
     *
     * @return list<Geometry>
     */
    private function parseMultipolygons(mixed $multipolygons): array
    {
        $json = is_array($multipolygons) ? json_encode($multipolygons) : OpenFormsNormalizer::normalizeJson($multipolygons);
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
            $candidate = is_array($mapState) ? ($mapState['buitenLocatieVanHetEvenement'] ?? $mapState) : null;
            if (! is_array($candidate)) {
                continue;
            }
            $features = $candidate['geojson']['features'] ?? null;
            if (! is_array($features)) {
                // Fallback: oude flow waar de FormSubmissionObject hier al
                // genormaliseerde geometrieën leverde — recursief zoeken
                // naar coordinates, exact zoals de oorspronkelijke code.
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
