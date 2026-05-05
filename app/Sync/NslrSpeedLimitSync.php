<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;

class NslrSpeedLimitSync extends AbstractSyncJob
{
    public static function sourceKey(): string
    {
        return 'nslr';
    }

    protected function runSync(SyncRun $run, DataSource $source): void
    {
        // Filter to currently-effective zones only (skip historical bylaws).
        // ArcGIS supports CURRENT_TIMESTAMP in WHERE clauses on hosted services.
        $where = "whenEffective <= CURRENT_TIMESTAMP "
            ."AND (whenIneffective IS NULL OR whenIneffective > CURRENT_TIMESTAMP)";

        $client = new ArcgisFeatureClient(
            featureUrl: config('services.nslr.url'),
            where: $where,
        );

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

                $externalId = (string) ($props['GlobalID']
                    ?? $props['speedLimitZoneId']
                    ?? $props['OBJECTID']
                    ?? '');

                $speed = self::intOrNull($props['speedLimitZoneValue'] ?? null);

                if ($externalId === '' || $speed === null) {
                    $failed++;
                    continue;
                }

                ZoneUpsert::upsert(
                    $source->id,
                    $externalId,
                    'speed_limit',
                    json_encode($geom),
                    [
                        'road_name' => null, // NSLR zones don't carry road names directly
                        'rca' => $props['rcaZoneReferenceName'] ?? null,
                        'zone_name' => $props['speedLimitZoneName'] ?? null,
                        'speed_limit_kmh' => $speed,
                        'speed_limit_type' => $props['speedCategoryName'] ?? null,
                        'raw_payload' => $props,
                        'synced_at' => now(),
                    ]
                );

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

    private static function intOrNull(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }
}
