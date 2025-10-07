<?php

namespace App\Livewire\Zaken;

use Brick\Geo\Io\GeoJsonReader;
use Brick\Geo\LineString;
use Brick\Geo\Point;
use Brick\Geo\Polygon;
use Livewire\Attributes\Locked;
use Webbingbrasil\FilamentMaps\Actions\CenterMapAction;
use Webbingbrasil\FilamentMaps\Actions\ZoomAction;
use Webbingbrasil\FilamentMaps\Marker;
use Webbingbrasil\FilamentMaps\Polygone;
use Webbingbrasil\FilamentMaps\Polyline;
use Webbingbrasil\FilamentMaps\Widgets\MapWidget;

class Map extends MapWidget
{
    protected int|string|array $columnSpan = 2;

    protected bool $hasBorder = false;

    #[Locked]
    public ?array $geojson;

    #[Locked]
    public array $route = [];

    #[Locked]
    public array $outsidePlaces = [];

    #[Locked]
    public array $addresses = [];

    public function mount(?array $geojson = null): void
    {
        $this->geojson = $geojson;

        if (! empty($this->geojson)) {
            /** @var \Brick\Geo\GeometryCollection $geometryCollection */
            $geometryCollection = (new GeoJsonReader)->read(json_encode($this->geojson));
            $bbox = $geometryCollection->getBoundingBox();

            $this->fitBounds([
                [$bbox->swY, $bbox->swX],
                [$bbox->neY, $bbox->neX],
            ]);

            foreach ($geometryCollection->geometries() as $geometry) {
                if ($geometry instanceof LineString) {
                    foreach ($geometry->points() as $point) {
                        $this->route[] = [$point->y(), $point->x()];
                    }
                } elseif ($geometry instanceof Polygon) {
                    $polygon = [];
                    foreach ($geometry->exteriorRing()->points() as $point) {
                        $polygon[] = [$point->y(), $point->x()];
                    }
                    $this->outsidePlaces[] = $polygon;
                } elseif ($geometry instanceof Point) {
                    $this->addresses[] = [$geometry->y(), $geometry->x()];
                }
            }

        }
        parent::mount();
    }

    public function getPolygones(): array
    {
        if (! $this->outsidePlaces) {
            return [];
        }

        $polygons = [];

        foreach ($this->outsidePlaces as $key =>  $polygon) {
            $polygons[] = Polygone::make('polygone-' . $key)
                ->latlngs($polygon)
                ->options(['color' => 'blue', 'weight' => '2', 'fillColor' => 'blue', 'fillOpacity' => '0.4'])
                ->tooltip('Buitenlocatie van het evenement')
                ->popup('Buitenlocatie van het evenement');
        }

        return $polygons;
    }

    public function getPolylines(): array
    {
        if (! $this->route) {
            return [];
        }

        return [
            Polyline::make('polyline')
                ->latlngs($this->route)
                ->options(['color' => 'blue', 'weight' => '5'])
                ->popup(__('Route van het evenement'))
                ->tooltip(__('Route van het evenement')),
        ];
    }

    public function getMarkers(): array
    {
        return array_map(fn ($address) => Marker::make('address-'.implode('-', $address))
            ->lat($address[0])
            ->lng($address[1])
            ->popup(__('Adres van het evenement'))
            ->tooltip(__('Adres van het evenement')), $this->addresses);
    }

    public function getActions(): array
    {
        return [
            // ZoomAction::make(),
            // CenterMapAction::make()->zoom(2),
        ];
    }
}
