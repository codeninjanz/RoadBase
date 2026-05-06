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
                ST_Longitude(cs.location) AS lng,
                ST_Latitude(cs.location) AS lat
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
        // Currently only NSLR speed-limit zones are ingested. Kind kept as a
        // param so future line/segment sources can plug in without API churn.
        $kind = $request->string('kind', 'speed_limit')->whenIn(['speed_limit'])->toString();
        if ($kind === '') {
            return response()->json(['error' => 'kind must be speed_limit'], 422);
        }

        $zoom = (int) $request->integer('z', 12);
        $tolerance = $this->simplifyToleranceFor($zoom);

        $rows = DB::select(<<<'SQL'
            SELECT
                rs.id,
                rs.road_name,
                rs.zone_name,
                rs.rca,
                rs.speed_limit_kmh,
                rs.speed_limit_type,
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
                    'zone_name' => $row->zone_name,
                    'rca' => $row->rca,
                    'speed_limit_kmh' => $row->speed_limit_kmh !== null ? (int) $row->speed_limit_kmh : null,
                    'speed_limit_type' => $row->speed_limit_type,
                ],
            ], $rows),
        ]);
    }

    public function rcas(Request $request): JsonResponse
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $this->parseBbox($request);
        $zoom = (int) $request->integer('z', 8);
        $tolerance = $this->simplifyToleranceFor($zoom);

        // MySQL 8 rejects ST_Simplify on geographic MULTIPOLYGON, so we
        // round-trip the geometry through a SRID-0 (Cartesian) copy where
        // ST_Simplify is supported. Treats degrees as a flat plane — fine
        // at NZ latitudes for visual generalisation.
        $rows = DB::select(<<<'SQL'
            SELECT
                r.id, r.code, r.name, r.kind,
                ST_AsGeoJSON(
                    ST_Simplify(ST_GeomFromText(ST_AsText(r.geom), 0), ?)
                ) AS geojson
            FROM rcas r
            WHERE MBRIntersects(
                    ST_SRID(ST_GeomFromText(?), 4326),
                    r.geom
                  )
            LIMIT ?
        SQL, [$tolerance, $this->bboxWkt($minLng, $minLat, $maxLng, $maxLat), self::MAX_FEATURES]);

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => array_map(fn ($row) => [
                'type' => 'Feature',
                'id' => $row->id,
                'geometry' => json_decode($row->geojson, true),
                'properties' => [
                    'id' => $row->id,
                    'code' => $row->code,
                    'name' => $row->name,
                    'kind' => $row->kind,
                ],
            ], $rows),
        ]);
    }

    public function crashes(Request $request): JsonResponse
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $this->parseBbox($request);

        $rows = DB::select(<<<'SQL'
            SELECT id, road_name, severity, crash_year, speed_limit_kmh,
                   ST_Longitude(location) AS lng, ST_Latitude(location) AS lat
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

    /**
     * Parse bbox=minLng,minLat,maxLng,maxLat and clamp to NZ bounds.
     * Substitutes the full NZ bbox when the viewport is degenerate or
     * crosses the antimeridian (e.g. zoomed all the way out).
     *
     * @return array{0:float,1:float,2:float,3:float}
     */
    private function parseBbox(Request $request): array
    {
        // NZ-ish bounds. Stewart Island ~ -47.3, Cape Reinga ~ -34.4,
        // West coast ~ 166.4, Chathams ~ -176.5 (east of antimeridian).
        $nzBbox = [165.0, -48.0, 179.0, -34.0];

        $raw = $request->string('bbox')->toString();
        if ($raw === '') {
            return $nzBbox;
        }

        $parts = array_map('floatval', array_pad(explode(',', $raw), 4, '0'));
        [$minLng, $minLat, $maxLng, $maxLat] = $parts;

        // Antimeridian wrap (Google Maps reports SW.lng > NE.lng) — show all NZ.
        if ($minLng > $maxLng) {
            return $nzBbox;
        }

        // Clamp to NZ window.
        $minLng = max($nzBbox[0], min($nzBbox[2], $minLng));
        $maxLng = max($nzBbox[0], min($nzBbox[2], $maxLng));
        $minLat = max($nzBbox[1], min($nzBbox[3], $minLat));
        $maxLat = max($nzBbox[1], min($nzBbox[3], $maxLat));

        // Reject zero-area boxes — fall back to NZ.
        if ($maxLng - $minLng < 0.0001 || $maxLat - $minLat < 0.0001) {
            return $nzBbox;
        }

        return [$minLng, $minLat, $maxLng, $maxLat];
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
