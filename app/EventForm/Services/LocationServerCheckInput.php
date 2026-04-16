<?php

declare(strict_types=1);

namespace App\EventForm\Services;

/**
 * Genormaliseerde input voor `LocationServerCheckService`.
 *
 * Values zijn GeoJSON-objecten (decoded arrays) of null. De caller is
 * verantwoordelijk voor het decoden van eventuele JSON-strings vóór
 * constructie — de service werkt consistent met decoded data ongeacht
 * bron (HTTP-controller of Filament-form).
 */
final readonly class LocationServerCheckInput
{
    /**
     * @param  list<array<string, mixed>>|null  $polygons  GeoJSON Polygon/MultiPolygon objecten
     * @param  array<string, mixed>|null  $line  GeoJSON LineString
     * @param  list<array<string, mixed>>|null  $lines  Lijst van GeoJSON LineStrings
     * @param  list<array{postcode: string, houseNumber: string}>|null  $addresses
     * @param  array{postcode: string, houseNumber: string}|null  $address
     */
    public function __construct(
        public ?array $polygons = null,
        public ?array $line = null,
        public ?array $lines = null,
        public ?array $addresses = null,
        public ?array $address = null,
    ) {}

    public function hasAnyInput(): bool
    {
        return $this->polygons !== null
            || $this->line !== null
            || $this->lines !== null
            || $this->addresses !== null
            || $this->address !== null;
    }
}
