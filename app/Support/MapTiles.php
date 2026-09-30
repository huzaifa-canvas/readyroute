<?php

namespace App\Support;

/**
 * Where the panel's maps get their basemap tiles.
 *
 * Every provider here serves the same {z}/{x}/{y} raster tiles, so swapping
 * between them is a config change and nothing in a view has to know which one
 * is in use. A provider whose key is missing falls back to OpenStreetMap
 * rather than rendering a watermarked or blank map.
 */
class MapTiles
{
    /**
     * The tile URL and attribution for the configured provider.
     *
     * @return array{url: string, attribution: string, max_zoom: int, provider: string}
     */
    public static function current(): array
    {
        $provider = config('readyroute.map.provider', 'osm');

        return match ($provider) {
            'carto'    => static::carto()    ?? static::osm(),
            'maptiler' => static::maptiler() ?? static::osm(),
            'stadia'   => static::stadia()   ?? static::osm(),
            default    => static::osm(),
        };
    }

    /**
     * OpenStreetMap's own tiles. Free and keyless, which is what makes this a
     * safe default and a safe fallback.
     *
     * @return array{url: string, attribution: string, max_zoom: int, provider: string}
     */
    public static function osm(): array
    {
        return [
            'provider'    => 'osm',
            'url'         => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'attribution' => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            'max_zoom'    => 19,
        ];
    }

    /**
     * @return array{url: string, attribution: string, max_zoom: int, provider: string}|null
     */
    private static function carto(): ?array
    {
        $key = config('readyroute.map.carto_key');

        if (blank($key)) {
            return null;
        }

        return [
            'provider'    => 'carto',
            'url'         => 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png?api_key=' . $key,
            'attribution' => '&copy; <a href="https://carto.com/attributions">CARTO</a> &copy; OpenStreetMap contributors',
            'max_zoom'    => 20,
        ];
    }

    /**
     * @return array{url: string, attribution: string, max_zoom: int, provider: string}|null
     */
    private static function maptiler(): ?array
    {
        $key = config('readyroute.map.maptiler_key');

        if (blank($key)) {
            return null;
        }

        return [
            'provider'    => 'maptiler',
            'url'         => 'https://api.maptiler.com/maps/streets-v2/{z}/{x}/{y}.png?key=' . $key,
            'attribution' => '&copy; <a href="https://www.maptiler.com/copyright/">MapTiler</a> &copy; OpenStreetMap contributors',
            'max_zoom'    => 20,
        ];
    }

    /**
     * @return array{url: string, attribution: string, max_zoom: int, provider: string}|null
     */
    private static function stadia(): ?array
    {
        $key = config('readyroute.map.stadia_key');

        if (blank($key)) {
            return null;
        }

        return [
            'provider'    => 'stadia',
            'url'         => 'https://tiles.stadiamaps.com/tiles/alidade_smooth/{z}/{x}/{y}{r}.png?api_key=' . $key,
            'attribution' => '&copy; <a href="https://stadiamaps.com/">Stadia Maps</a> &copy; OpenStreetMap contributors',
            'max_zoom'    => 20,
        ];
    }
}
