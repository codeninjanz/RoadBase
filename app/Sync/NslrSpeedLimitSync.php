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
        $client = new ArcgisFeatureClient(
            featureUrl: config('services.nslr.url'),
        );

        $upserted = 0;
        $failed = 0;

        foreach ($client->features() as $feature) {
            try {
                $attrs = $feature['attributes'] ?? [];
                $paths = $feature['geometry']['paths'] ?? [];

                if (empty($paths)) {
                    $failed++;
                    continue;
                }

                $longest = Geometry::longestPath($paths);
                $wkt = Geometry::lineStringWkt($longest);
                if ($wkt === null) {
                    $failed++;
                    continue;
                }

                $externalId = (string) ($attrs['globalId'] ?? $attrs['GlobalID'] ?? $attrs['OBJECTID'] ?? '');
                $speed = self::intOrNull(
                    $attrs['speedLimit']
                    ?? $attrs['SpeedLimit']
                    ?? $attrs['SpeedLimit_VKT']
                    ?? $attrs['SPEED_LIMIT']
                    ?? null
                );

                if ($externalId === '' || $speed === null) {
                    $failed++;
                    continue;
                }

                SegmentUpsert::upsert(
                    $source->id,
                    $externalId,
                    'speed_limit',
                    $wkt,
                    [
                        'road_name' => $attrs['roadName'] ?? $attrs['RoadName'] ?? null,
                        'rca' => $attrs['rcaName'] ?? $attrs['RCA'] ?? null,
                        'speed_limit_kmh' => $speed,
                        'speed_limit_type' => $attrs['speedLimitType'] ?? $attrs['SpeedLimitType'] ?? null,
                        'raw_payload' => $attrs,
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
