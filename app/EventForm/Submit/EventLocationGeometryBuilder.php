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
            if ($geometry = $this->parseLine($eventLocation['line'])) {
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

    private function parseLine(mixed $line): ?Geometry
    {
        $json = is_array($line) ? json_encode($line) : OpenFormsNormalizer::normalizeGeoJson($line);
        $array = json_decode((string) $json, true);
        $array = is_array($array) ? ArrayHelper::findElementWithKey($array, 'coordinates') : null;
        if (! $array) {
            return null;
        }

        return (new GeoJsonReader)->read((string) json_encode($array));
    }

    /**
     * @return list<Geometry>
     */
    private function parseMultipolygons(mixed $multipolygons): array
    {
        $json = is_array($multipolygons) ? json_encode($multipolygons) : OpenFormsNormalizer::normalizeJson($multipolygons);
        $decoded = json_decode((string) $json, true);
        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $entry) {
            $array = is_array($entry) ? ArrayHelper::findElementWithKey($entry, 'coordinates') : null;
            if (! $array) {
                continue;
            }
            $out[] = (new GeoJsonReader)->read((string) json_encode($array));
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
