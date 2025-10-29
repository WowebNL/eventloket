<?php

namespace App\ValueObjects\ObjectsApi;

use App\Normalizers\OpenFormsNormalizer;
use App\Services\LocatieserverService;
use Brick\Geo\Geometry;
use Brick\Geo\GeometryCollection;
use Brick\Geo\Io\GeoJson\Feature;
use Brick\Geo\Io\GeoJson\FeatureCollection;
use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\Io\GeoJsonWriter;
use Brick\Geo\Point;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;

class FormSubmissionObject implements Arrayable
{
    public readonly ?array $otherParams;

    public readonly ?array $zaakeigenschappen;

    public readonly ?array $zaakeigenschappen_key_value;

    public readonly string $organisation_uuid;

    public readonly string $user_uuid;

    public readonly array $initiator;

    private readonly array $event_location;

    public array $zaakEventAddresses = [];

    public function __construct(
        public readonly string $uuid,
        public readonly string $type,
        public readonly array $record,
        ...$otherParams
    ) {
        $this->otherParams = $otherParams;
        $this->organisation_uuid = $this->record['data'][strtolower(config('app.name')).'_organisation_uuid'] ?? '';
        $this->user_uuid = $this->record['data'][strtolower(config('app.name')).'_user_uuid'] ?? '';
        $this->initiator = $this->record['data']['initiator'] ?? [];
        $this->event_location = $this->record['data']['event_location'] ?? [];
        $this->zaakeigenschappen = $this->record['data']['zaakeigenschappen'] ?? null;
        $this->zaakeigenschappen_key_value = $this->zaakeigenschappen
            ? Arr::mapWithKeys($this->zaakeigenschappen, fn ($item) => [key($item) => current($item)])
            : [];
    }

    public function getFormattedEventLocation(bool $asFeatures = false): ?string
    {
        // feature collection is not suported in OpenZaak so use geometry collection for now (no support for properties)
        if (empty($this->event_location)) {
            return null;
        }

        $features = [];
        $geometries = [];

        if (isset($this->event_location['line']) && ! empty($this->event_location['line']) && $this->event_location['line'] != 'None') {
            $json = OpenFormsNormalizer::normalizeGeoJson(OpenFormsNormalizer::normalizeJson($this->event_location['line']));
            $array = json_decode($json, true);
            $geometry = (new GeoJsonReader)->read(json_encode($array));
            if ($asFeatures) {
                $feature = new Feature($geometry);
                $feature = $feature->withProperty('name', __('Route evenement'));
                $features[] = $feature;
            }
            $geometries[] = $geometry;
        }

        if (isset($this->event_location['multipolygons']) && ! empty($this->event_location['multipolygons']) && $this->event_location['multipolygons'] != 'None') {
            $json = OpenFormsNormalizer::normalizeJson($this->event_location['multipolygons']);
            foreach (json_decode($json, true) as $array) {
                $array = Arr::first($array);
                $geometry = (new GeoJsonReader)->read(json_encode($array));
                if ($asFeatures) {
                    $feature = new Feature($geometry);
                    $feature = $feature->withProperty('name', __('Gebied evenement'));
                    $features[] = $feature;
                }
                $geometries[] = $geometry;
            }
        }

        if (isset($this->event_location['bag_addresses']) && ! empty($this->event_location['bag_addresses'])) {
            $json = OpenFormsNormalizer::normalizeJson($this->event_location['bag_addresses']);
            $locationService = new LocatieserverService;
            foreach (json_decode($json, true) as $array) {
                $array = Arr::first($array);
                $geometry = $this->getGeometryFromAddress($array, $locationService, $asFeatures);

                if ($geometry) {
                    $geometries[] = $geometry;
                }
            }
        }

        if (isset($this->event_location['bag_address']) && ! empty($this->event_location['bag_address'])) {
            $json = OpenFormsNormalizer::normalizeJson($this->event_location['bag_address']);
            $locationService = new LocatieserverService;
            $array = json_decode($json, true);
            $geometry = $this->getGeometryFromAddress($array, $locationService, $asFeatures);
            if ($geometry) {
                $geometries[] = $geometry;
            }
        }

        if ($asFeatures && $features) {
            $collection = new FeatureCollection(...$features);
            $writer = new GeoJsonWriter;

            return $writer->write($collection);
        }

        if ($geometries) {
            $collection = GeometryCollection::of(...$geometries);
            $writer = new GeoJsonWriter;

            return $writer->write($collection);
        }

        return '';
    }

    private function getGeometryFromAddress(array $address, LocatieserverService $locationService, bool $asFeatures = false): Point|Feature|null
    {
        $bagObject = $locationService->getBagObjectByPostcodeHuisnummer(
            $address['postcode'],
            $address['houseNumber'],
            $address['houseLetter'] ?? null,
            $address['houseNumberAddition'] ?? null
        );

        if ($bagObject) {
            $this->zaakEventAddresses[] = $bagObject;
            $geometry = Point::fromText($bagObject->centroide_ll, 4326);
            if ($asFeatures) {
                $feature = new Feature($geometry);
                $feature = $feature->withProperty('name', $bagObject->weergavenaam);

                return $feature;
            }

            return $geometry;
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'record' => $this->record,
            'organisation_uuid' => $this->organisation_uuid,
            'user_uuid' => $this->user_uuid,
            'initiator' => $this->initiator,
            'otherParams' => $this->otherParams,
        ];
    }
}
