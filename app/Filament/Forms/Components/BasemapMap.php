<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use App\Support\Maps\Basemap;
use Dotswan\MapPicker\Fields\Map;

/**
 * Map picker field pre-configured with the application's basemap.
 *
 * The upstream field defaults to its own tile service and ships no
 * attribution at all, and it exposes no setter for the attribution or the
 * tile geometry. Both are applied here through extraTileControl(), which
 * merges straight into the config the field hands to L.tileLayer.
 *
 * Use this class instead of the upstream field everywhere, so that no map
 * can be added that renders without a basemap and without its attribution.
 *
 * The field keeps the upstream view name on purpose. The application
 * overrides that view in resources/views/vendor/map-picker, and the override
 * carries the map behaviour this form depends on: drawn shapes are committed
 * right away, several maps on one page stay apart, there is an address
 * search, moving the map keeps the geometry, and geometry that reaches the
 * state from the server (a route read from an uploaded file) is redrawn.
 * Setting another view here would silently bypass all of that.
 */
class BasemapMap extends Map
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->tilesUrl(Basemap::tilesUrl())
            ->extraTileControl(Basemap::leafletTileOptions());
    }
}
