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
 * It also renders through its own view, which wraps the upstream Alpine
 * object. That wrapper adds two things the upstream one does not do: it
 * redraws the geometry when the state changes server side (the upstream field
 * reads the geometry once, while building the map), and it keeps the geometry
 * in the state when the map is moved. See the view for details.
 */
class BasemapMap extends Map
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->view('filament.forms.components.basemap-map')
            ->tilesUrl(Basemap::tilesUrl())
            ->extraTileControl(Basemap::leafletTileOptions());
    }
}
