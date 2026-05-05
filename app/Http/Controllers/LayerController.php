<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LayerController extends Controller
{
    private const MAX_FEATURES = 2000;

    public function sites(Request $request): JsonResponse
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $this->parseBbox($request);

        $rows = DB::select(<<<'SQL'
            SELECT
                cs.id,
                cs.road_name,
                cs.aadt,
                cs.heavy_vehicle_pct,
                cs.speed_limit_kmh,
                cs.nzgttm_level,
                cs.count_date,
                ds.key  AS source_key,
                ds.name AS source_name,
                ST_X(cs.location) AS lng,
                ST_Y(cs.location) AS lat
            FROM count_sites cs
            JOIN data_sources ds ON ds.id = cs.data_source_id
            WHERE MBRContains(
                ST_SRID(ST_GeomFromText(?), 4326),
                cs.location
            )
            LIMIT ?
        SQL, [$this->bboxWkt($minLng, $minLat, $maxLng, $maxLat), self::MAX_FEATURES]);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => array_map(fn ($row) => [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $row->lng, (float) $row->lat],
                ],
                'properties' => [
                    'id' => $row->id,
                    'road_name' => $row->road_name,
                    'aadt' => $row->aadt !== null ? (int) $row->aadt : null,
                    'heavy_vehicle_pct' => $row->heavy_vehicle_pct !== null ? (float) $row->heavy_vehicle_pct : null,
                    'speed_limit_kmh' => $row->speed_limit_kmh !== null ? (int) $row->speed_limit_kmh : null,
                    'nzgttm_level' => $row->nzgttm_level,
                    'count_date' => $row->count_date,
                    'source_key' => $row->source_key,
                    'source_name' => $row->source_name,
                ],
            ], $rows),
        ]);
    }

    public function segments(Request $request): JsonResponse
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $this->parseBbox($request);
        $kind = $request->string('kind')->whenIn(['aadt_line', 'speed_limit'])->toString();
        if ($kind === '') {
            return response()->json(['error' => 'kind must be aadt_line or speed_limit'], 422);
        }

        $zoom = (int) $request->integer('z', 12);
        $tolerance = $this->simplifyToleranceFor($zoom);

        $rows = DB::select(<<<'SQL'
            SELECT
                rs.id,
                rs.road_name,
                rs.aadt,
                rs.speed_limit_kmh,
                rs.nzgttm_level,
                ST_AsGeoJSON(ST_Simplify(rs.geom, ?)) AS geojson
            FROM road_segments rs
            WHERE rs.kind = ?
              AND MBRIntersects(
                    ST_SRID(ST_GeomFromText(?), 4326),
                    rs.geom
                  )
            LIMIT ?
        SQL, [$tolerance, $kind, $this->bboxWkt($minLng, $minLat, $maxLng, $maxLat), self::MAX_FEATURES]);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => array_map(fn ($row) => [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => json_decode($row->geojson, true),
                'properties' => [
                    'id' => $row->id,
                    'road_name' => $row->road_name,
                    'aadt' => $row->aadt !== null ? (int) $row->aadt : null,
                    'speed_limit_kmh' => $row->speed_limit_kmh !== null ? (int) $row->speed_limit_kmh : null,
                    'nzgttm_level' => $row->nzgttm_level,
                ],
            ], $rows),
        ]);
    }

    public function crashes(Request $request): JsonResponse
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $this->parseBbox($request);

        $rows = DB::select(<<<'SQL'
            SELECT id, road_name, severity, crash_year, speed_limit_kmh,
                   ST_X(location) AS lng, ST_Y(location) AS lat
            FROM crashes
            WHERE MBRContains(
                ST_SRID(ST_GeomFromText(?), 4326),
                location
            )
            LIMIT ?
        SQL, [$this->bboxWkt($minLng, $minLat, $maxLng, $maxLat), self::MAX_FEATURES]);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => array_map(fn ($row) => [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [(float) $row->lng, (float) $row->lat],
                ],
                'properties' => [
                    'severity' => $row->severity,
                    'crash_year' => $row->crash_year,
                    'speed_limit_kmh' => $row->speed_limit_kmh,
                    'road_name' => $row->road_name,
                ],
            ], $rows),
        ]);
    }

    /** @return array{0:float,1:float,2:float,3:float} */
    private function parseBbox(Request $request): array
    {
        $bbox = $request->string('bbox')->toString();
        $parts = array_map('floatval', array_pad(explode(',', $bbox), 4, '0'));
        // Clamp to NZ-ish bounds to avoid pathological queries.
        $parts[0] = max(160.0, min(180.0, $parts[0]));
        $parts[2] = max(160.0, min(180.0, $parts[2]));
        $parts[1] = max(-50.0, min(-30.0, $parts[1]));
        $parts[3] = max(-50.0, min(-30.0, $parts[3]));
        return $parts;
    }

    private function bboxWkt(float $minLng, float $minLat, float $maxLng, float $maxLat): string
    {
        return sprintf(
            'POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
            $minLng, $minLat,
            $maxLng, $minLat,
            $maxLng, $maxLat,
            $minLng, $maxLat,
            $minLng, $minLat
        );
    }

    private function simplifyToleranceFor(int $zoom): float
    {
        return match (true) {
            $zoom >= 15 => 0.00001,
            $zoom >= 13 => 0.00005,
            $zoom >= 11 => 0.0002,
            $zoom >= 9 => 0.001,
            default => 0.005,
        };
    }
}
