<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;

/**
 * Sync Stats NZ Territorial Authority 2023 polygons into the rcas table.
 *
 * NZGTTM 2023 (p.41) requires every TMP to reach the correct Road Controlling
 * Authority. NSLR speed-limit zones already carry an RCA name where they
 * exist, but coverage is patchy outside posted-speed corridors. TA polygons
 * give us authoritative jurisdiction for every NZ point as a fallback.
 *
 * Endpoint expected: an ArcGIS REST FeatureServer layer query URL exposing
 * the TA polygons with name + code. Set STATS_NZ_TA_URL in .env to enable.
 */
class RcaTaSync extends AbstractSyncJob
{
    public static function sourceKey(): string
    {
        return 'stats_nz_ta';
    }

    protected function runSync(SyncRun $run, DataSource $source): void
    {
        $url = config('services.stats_nz.ta_url');
        if (! $url) {
            $run->update(['records_upserted' => 0, 'records_failed' => 0]);
            return;
        }

        $client = new ArcgisFeatureClient(featureUrl: $url);

        $upserted = 0;
        $failed = 0;

        foreach ($client->features() as $feature) {
            try {
                $props = $feature['properties'] ?? [];
                $geom = $feature['geometry'] ?? null;

                if (! $geom || ! in_array($geom['type'] ?? '', ['Polygon', 'MultiPolygon'], true)) {
                    $failed++;
                    continue;
                }

                // Stats NZ TA layer field naming varies between vintages —
                // try the common candidates in priority order.
                $code = self::stringOrNull(
                    $props['TA2023_V1_00'] ?? $props['TA2023_V_1_00'] ?? $props['TA2023_code']
                    ?? $props['TA2023_V1'] ?? $props['TA_CODE'] ?? $props['code'] ?? null
                );
                $name = self::stringOrNull(
                    $props['TA2023_V1_00_NAME'] ?? $props['TA2023_V_1_00_NAME']
                    ?? $props['TA2023_NAME'] ?? $props['TA_NAME'] ?? $props['name'] ?? null
                );
                $externalId = (string) ($code ?? $props['OBJECTID'] ?? '');

                if ($externalId === '' || $name === null) {
                    $failed++;
                    continue;
                }

                $multi = self::asMultiPolygon($geom);
                self::upsert($source->id, $externalId, $code, $name, json_encode($multi), $props);
                $upserted++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $run->update([
            'records_upserted' => $upserted,
            'records_failed' => $failed,
        ]);
    }

    /**
     * Coerce a Polygon to a single-element MultiPolygon so the column's fixed
     * MULTIPOLYGON type matches every row.
     *
     * @param array{type:string, coordinates:array} $geom
     * @return array{type:string, coordinates:array}
     */
    private static function asMultiPolygon(array $geom): array
    {
        if (($geom['type'] ?? '') === 'Polygon') {
            return [
                'type' => 'MultiPolygon',
                'coordinates' => [$geom['coordinates']],
            ];
        }
        return $geom;
    }

    private static function upsert(
        int $sourceId,
        string $externalId,
        ?string $code,
        string $name,
        string $geomGeoJson,
        array $rawPayload,
    ): void {
        DB::statement(
            'INSERT INTO rcas (data_source_id, external_id, code, name, kind, raw_payload, synced_at, created_at, updated_at, geom)
             VALUES (?, ?, ?, ?, "territorial_authority", ?, NOW(), NOW(), NOW(), ST_GeomFromGeoJSON(?, 1, 4326))
             ON DUPLICATE KEY UPDATE
                 code = VALUES(code),
                 name = VALUES(name),
                 raw_payload = VALUES(raw_payload),
                 synced_at = NOW(),
                 updated_at = NOW(),
                 geom = VALUES(geom)',
            [
                $sourceId, $externalId, $code, $name,
                json_encode($rawPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $geomGeoJson,
            ],
        );
    }

    private static function stringOrNull(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : null;
    }
}
