<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SiteController extends Controller
{
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

        return response()->json([
            'id' => (int) $row->id,
            'road_name' => $row->road_name,
            'region' => $row->region,
            'lat' => (float) $row->lat,
            'lng' => (float) $row->lng,
            'aadt' => $row->aadt !== null ? (int) $row->aadt : null,
            'heavy_vehicle_pct' => $row->heavy_vehicle_pct !== null ? (float) $row->heavy_vehicle_pct : null,
            'peak_hour_volume' => $row->peak_hour_volume !== null ? (int) $row->peak_hour_volume : null,
            'peak_hour_start' => $row->peak_hour_start,
            'count_date' => $row->count_date,
            'speed_limit_kmh' => $row->speed_limit_kmh !== null ? (int) $row->speed_limit_kmh : null,
            'nzgttm_level' => $row->nzgttm_level,
            'synced_at' => $row->synced_at,
            'source' => [
                'key' => $row->source_key,
                'name' => $row->source_name,
                'url' => $row->source_url,
                'last_synced_at' => $row->source_synced_at,
            ],
        ]);
    }
}
