<?php

declare(strict_types=1);

namespace App\Support\Maps;

/**
 * Accessor for the raster basemap configured in config/maps.php.
 *
 * Every map surface goes through this class: the Filament map fields, the
 * read-only GeoJSON infolist entry and the server-side map drawn into the
 * submission PDF. That keeps the tile URL in exactly one place.
 *
 * It also derives the origin for the Content-Security-Policy from that same
 * URL. Pointing the application at another tile host therefore widens the
 * policy automatically, instead of leaving the browser to block a basemap the
 * application is otherwise correctly configured to load.
 */
final class Basemap
{
    /**
     * Leaflet URL template for the raster tiles.
     */
    public static function tilesUrl(): string
    {
        return (string) config('maps.tiles.url');
    }

    /**
     * Attribution that must be shown on every map surface.
     */
    public static function attribution(): string
    {
        return (string) config('maps.tiles.attribution');
    }

    /**
     * Scheme and host of the tile service, for use in a CSP source list.
     *
     * Returns an empty string when the configured URL has no usable origin,
     * so a misconfiguration widens nothing.
     */
    public static function origin(): string
    {
        $url = self::tilesUrl();

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($scheme) || ! is_string($host) || $scheme === '' || $host === '') {
            return '';
        }

        $origin = $scheme.'://'.$host;

        $port = parse_url($url, PHP_URL_PORT);

        return is_int($port) ? $origin.':'.$port : $origin;
    }

    /**
     * Tile layer options passed straight to Leaflet.
     *
     * The keys match the option names the map picker forwards to
     * L.tileLayer, so this array can be merged into its config as-is.
     *
     * @return array{attribution: string, tileSize: int, zoomOffset: int, detectRetina: bool, maxZoom: int}
     */
    public static function leafletTileOptions(): array
    {
        return [
            'attribution' => self::attribution(),
            'tileSize' => (int) config('maps.tiles.tile_size'),
            'zoomOffset' => (int) config('maps.tiles.zoom_offset'),
            'detectRetina' => (bool) config('maps.tiles.detect_retina'),
            'maxZoom' => (int) config('maps.tiles.max_zoom'),
        ];
    }

    /**
     * Resolve the tile URL template for one concrete tile.
     */
    public static function tileUrlFor(int $zoom, int $x, int $y): string
    {
        return strtr(self::tilesUrl(), [
            '{z}' => (string) $zoom,
            '{x}' => (string) $x,
            '{y}' => (string) $y,
        ]);
    }
}
