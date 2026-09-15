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
