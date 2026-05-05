<?php

namespace App\Sync;

/**
 * WKT helpers for ArcGIS-style geometries. ArcGIS returns x/y as lng/lat;
 * MySQL ST_GeomFromText with axis-order=long-lat plus SRID 4326 stores the
 * canonical (lng, lat) ordering.
 */
class Geometry
{
    public static function pointWkt(float $lng, float $lat): string
    {
        return sprintf('POINT(%F %F)', $lng, $lat);
    }

    /**
     * @param  array<int, array<int, float>>  $path  ArcGIS path: [[lng,lat],...]
     */
    public static function lineStringWkt(array $path): ?string
    {
        if (count($path) < 2) {
            return null;
        }

        $points = array_map(
            fn ($pair) => sprintf('%F %F', (float) $pair[0], (float) $pair[1]),
            $path
        );

        return 'LINESTRING('.implode(',', $points).')';
    }

    /**
     * Flattens a polyline (which may have multiple paths) to its longest path.
     *
     * @param  array<int, array<int, array<int, float>>>  $paths
     */
    public static function longestPath(array $paths): array
    {
        usort($paths, fn ($a, $b) => count($b) <=> count($a));
        return $paths[0] ?? [];
    }
}
