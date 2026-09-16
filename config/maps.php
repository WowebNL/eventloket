<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Map basemap configuration
|--------------------------------------------------------------------------
|
| Single source of truth for the raster basemap used by every map surface in
| this application: the Filament map fields, the read-only GeoJSON infolist
| entry and the server-side map rendered into the submission PDF.
|
| The tile URL is a Leaflet-style template. Everything that needs a basemap
| reads it through App\Support\Maps\Basemap, which also derives the origin
| that has to be allowed in the Content-Security-Policy. Changing the tile
| URL here therefore updates the CSP as well, so the browser can never end up
| blocking a basemap that the application is configured to use.
|
| The default is the national background map published as open data under
| CC BY 4.0. It covers the Netherlands only; outside that extent the service
| returns empty tiles. That is an accepted trade-off and deliberately has no
| fallback to a second source.
|
*/

return [

    'tiles' => [

        /*
         * Leaflet URL template for the raster tiles. The {z}/{x}/{y}
         * placeholders follow the Web Mercator (EPSG:3857) tiling scheme,
         * which is what Leaflet's default CRS expects.
         */
        'url' => env(
            'MAP_TILES_URL',
            'https://service.pdok.nl/kadaster/brt-achtergrondkaart/wmts/v2_0/standaard/EPSG:3857/{z}/{x}/{y}.png',
        ),

        /*
         * Attribution shown on every map surface. The tile licence requires
         * the source to be credited, so this is not optional decoration.
         */
        'attribution' => env('MAP_TILES_ATTRIBUTION', 'Kaartgegevens &copy; Kadaster (CC BY 4.0)'),

        /*
         * Tile geometry. The service publishes 256x256 tiles on the standard
         * Web Mercator quad, so no zoom offset is needed and retina detection
         * is off: enabling it would request a zoom level deeper at half size
         * and double the number of requests for no gain in detail.
         */
        'tile_size' => (int) env('MAP_TILES_TILE_SIZE', 256),
        'zoom_offset' => (int) env('MAP_TILES_ZOOM_OFFSET', 0),
        'detect_retina' => (bool) env('MAP_TILES_DETECT_RETINA', false),

        /*
         * Deepest zoom level the tile matrix publishes. Requesting beyond it
         * yields empty tiles, so Leaflet is told to stop here.
         */
        'max_zoom' => (int) env('MAP_TILES_MAX_ZOOM', 19),

        /*
         * Server-side rendering of the basemap into the submission PDF.
         *
         * Downloading tiles from a server is bulk traffic that a tile service
         * has every right to refuse, so it is bounded on three axes: a cap per
         * map, a budget per report and a cache that keeps repeat renders from
         * hitting the network at all.
         */
        'report' => [

            /*
             * User agent sent with every server-side tile request, so the
             * traffic is attributable to this application.
             */
            'user_agent' => env('MAP_TILES_USER_AGENT', 'Eventloket/1.0 (+https://github.com/WowebNL/eventloket)'),

            /*
             * How long a downloaded tile stays cached. A background map is
             * stable for months, so a long TTL removes almost all repeat
             * traffic for the areas that are rendered most often.
             */
            'cache_ttl' => (int) env('MAP_TILES_CACHE_TTL', 86400),

            /*
             * Request timeout per tile, in seconds.
             */
            'timeout' => (int) env('MAP_TILES_TIMEOUT', 8),

            /*
             * A 512x512 canvas drawn from 256x256 tiles needs at most a 3x3
             * grid. A larger grid means the tile arithmetic produced something
             * unexpected, and the mosaic is abandoned rather than fetched.
             */
            'max_tiles_per_map' => (int) env('MAP_TILES_MAX_PER_MAP', 9),

            /*
             * How many maps in one report may draw a full background. A report
             * renders one map per geometry answer, and repeater answers make
             * that count unbounded in principle. Beyond this many distinct
             * mosaics the remaining maps are drawn on a plain background, which
             * keeps a single PDF from turning into a bulk tile download.
             *
             * Tiles already cached do not count against the budget, so a report
             * that shows the same area several times still renders in full.
             */
            'max_background_maps' => (int) env('MAP_TILES_MAX_BACKGROUND_MAPS', 5),
        ],
    ],

];
