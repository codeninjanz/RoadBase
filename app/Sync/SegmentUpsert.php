<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;

/**
 * Equivalent of SiteUpsert for road_segments. Geometry is a LINESTRING in WKT.
 */
class SegmentUpsert
{
    public static function upsert(int $dataSourceId, string $externalId, string $kind, string $wkt, array $fields): void
    {
        $columns = ['data_source_id', 'external_id', 'kind', 'geom'];
        $placeholders = ['?', '?', '?', 'ST_GeomFromText(?, 4326, \'axis-order=long-lat\')'];
        $bindings = [$dataSourceId, $externalId, $kind, $wkt];

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
