<?php

namespace App\Filament\Infolists;

use Closure;
use Filament\Infolists\Components\Entry;

/**
 * Read-only kaartcomponent voor het weergeven van GeoJSON in een Filament infolist.
 *
 * Gebruikt de Leaflet-instantie (window.L) die dotswan/filament-map-picker
 * als UMD-bundel al globaal beschikbaar stelt. Er wordt geen Leaflet PM /
 * dotswan Alpine-component gebruikt, zodat er geen IntersectionObserver-
 * destructie, PM-toolbar of onnodige $wire.set-aanroepen optreden.
 */
class GeoJsonMapEntry extends Entry
{
    protected string $view = 'filament.infolists.components.geojson-map-entry';

    protected ?Closure $geojsonDataCallback = null;

    protected float $defaultLat = 52.3676;

    protected float $defaultLng = 4.9041;

    public function geojsonData(Closure $callback): static
    {
        $this->geojsonDataCallback = $callback;

        return $this;
    }

    public function defaultLocation(float $lat, float $lng): static
    {
        $this->defaultLat = $lat;
        $this->defaultLng = $lng;

        return $this;
    }

    /**
     * Evalueert de geojsonData-closure met het huidige record en retourneert
     * de FeatureCollection als PHP-array, of null als er geen data is.
     *
     * @return array<mixed>|null
     */
    public function getGeoJsonData(): ?array
    {
        if ($this->geojsonDataCallback === null) {
            return null;
        }

        $result = call_user_func($this->geojsonDataCallback, $this->getRecord());

        return is_array($result) ? $result : null;
    }

    /**
     * @return array{lat: float, lng: float}
     */
    public function getDefaultLocation(): array
    {
        return ['lat' => $this->defaultLat, 'lng' => $this->defaultLng];
    }
}
