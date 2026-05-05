<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;

/**
 * Single-statement upsert for road_segments rows whose geom is a Polygon
 * or MultiPolygon (e.g. NSLR speed-limit zones). Lets us keep the geom
 * column NOT NULL — required for the SPATIAL INDEX on MySQL 8.
 */
class ZoneUpsert
{
    public static function upsert(
        int $dataSourceId,
        string $externalId,
        string $kind,
        string $geomGeoJson,
        array $fields,
    ): void {
        $columns = ['data_source_id', 'external_id', 'kind', 'geom'];
        $placeholders = ['?', '?', '?', 'ST_GeomFromGeoJSON(?, 1, 4326)'];
        $bindings = [$dataSourceId, $externalId, $kind, $geomGeoJson];

        $updates = [];
        foreach ($fields as $col => $value) {
            $columns[] = $col;
            $placeholders[] = '?';
            $bindings[] = self::serialize($value);
            $updates[] = "{$col} = VALUES({$col})";
        }

        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            'INSERT INTO road_segments (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s, geom = VALUES(geom), updated_at = NOW()',
            implode(',', $columns),
            implode(',', $placeholders),
            implode(',', $updates)
        );

        DB::statement($sql, $bindings);
    }

    private static function serialize(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return $value;
    }
}
