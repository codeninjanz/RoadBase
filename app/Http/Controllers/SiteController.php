<?php

namespace App\Http\Controllers;

use App\Support\Nzgttm;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
    /** Crash-density radius (degrees ~ 1 km at NZ latitudes). */
    private const CRASH_RADIUS_DEG = 0.009;

    /** Crash-density window (years back from today). */
    private const CRASH_WINDOW_YEARS = 5;

    public function show(int $site): JsonResponse
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT
                cs.id, cs.road_name, cs.region, cs.aadt, cs.heavy_vehicle_pct,
                cs.peak_hour_volume, cs.peak_hour_start, cs.count_date,
                cs.speed_limit_kmh, cs.nzgttm_level, cs.synced_at,
                ST_Longitude(cs.location) AS lng, ST_Latitude(cs.location) AS lat,
                ds.key  AS source_key,
                ds.name AS source_name,
                ds.url  AS source_url,
                ds.last_synced_at AS source_synced_at
            FROM count_sites cs
            JOIN data_sources ds ON ds.id = cs.data_source_id
            WHERE cs.id = ?
        SQL, [$site]);

        if (! $row) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $lat = (float) $row->lat;
        $lng = (float) $row->lng;
        $aadt = $row->aadt !== null ? (int) $row->aadt : null;
        $speed = $row->speed_limit_kmh !== null ? (int) $row->speed_limit_kmh : null;
        $heavy = $row->heavy_vehicle_pct !== null ? (float) $row->heavy_vehicle_pct : null;

        $crashCount = $this->crashCountWithin($lat, $lng);
        $rca = $this->nearestRca($lat, $lng);

        return response()->json([
            'id' => (int) $row->id,
            'road_name' => $row->road_name,
            'region' => $row->region,
            'lat' => $lat,
            'lng' => $lng,
            'aadt' => $aadt,
            'heavy_vehicle_pct' => $heavy,
            'peak_hour_volume' => $row->peak_hour_volume !== null ? (int) $row->peak_hour_volume : null,
            'peak_hour_start' => $row->peak_hour_start,
            'count_date' => $row->count_date,
            'speed_limit_kmh' => $speed,
            'nzgttm_level' => $row->nzgttm_level,
            'synced_at' => $row->synced_at,
            'rca' => $rca,
            'crash_count_5yr_1km' => $crashCount,
            'context' => Nzgttm::contextBands($aadt, $speed, $heavy, $crashCount),
            'source' => [
                'key' => $row->source_key,
                'name' => $row->source_name,
                'url' => $row->source_url,
                'last_synced_at' => $row->source_synced_at,
            ],
        ]);
    }

    private function crashCountWithin(float $lat, float $lng): int
    {
        $minYear = (int) date('Y') - self::CRASH_WINDOW_YEARS;
        $r = self::CRASH_RADIUS_DEG;
        $minLng = $lng - $r;
        $maxLng = $lng + $r;
        $minLat = $lat - $r;
        $maxLat = $lat + $r;

        $row = DB::selectOne(<<<'SQL'
            SELECT COUNT(*) AS n
            FROM crashes
            WHERE crash_year >= ?
              AND MBRContains(
                    ST_SRID(ST_GeomFromText(?), 4326),
                    location
                  )
        SQL, [
            $minYear,
            sprintf(
                'POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
                $minLng, $minLat,
                $maxLng, $minLat,
                $maxLng, $maxLat,
                $minLng, $maxLat,
                $minLng, $minLat,
            ),
        ]);

        return (int) ($row->n ?? 0);
    }

    private function nearestRca(float $lat, float $lng): ?string
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT rca
            FROM road_segments
            WHERE kind = 'speed_limit'
              AND rca IS NOT NULL
              AND MBRContains(geom, ST_SRID(POINT(?, ?), 4326))
              AND ST_Contains(geom, ST_SRID(POINT(?, ?), 4326))
            ORDER BY ST_Area(geom) ASC
            LIMIT 1
        SQL, [$lng, $lat, $lng, $lat]);

        return $row->rca ?? null;
    }
}
