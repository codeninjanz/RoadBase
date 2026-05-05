<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;

/**
 * Single-statement upsert for count_sites including the SRID-4326 POINT.
 * Doing this in one INSERT ... ON DUPLICATE KEY UPDATE lets us keep the
 * location column NOT NULL (required for the SPATIAL INDEX in MySQL 8).
 */
class SiteUpsert
{
    public static function upsert(int $dataSourceId, string $externalId, float $lng, float $lat, array $fields): void
    {
        $columns = ['data_source_id', 'external_id', 'location'];
        $placeholders = ['?', '?', 'ST_SRID(POINT(?, ?), 4326)'];
        $bindings = [$dataSourceId, $externalId, $lng, $lat];

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
            'INSERT INTO count_sites (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s, location = VALUES(location), updated_at = NOW()',
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
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        return $value;
    }
}
