<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Support\Carbon;

class NztaTmsDailySync extends AbstractSyncJob
{
    public static function sourceKey(): string
    {
        return 'nzta_tms';
    }

    protected function runSync(SyncRun $run, DataSource $source): void
    {
        $client = new ArcgisFeatureClient(
            featureUrl: config('services.nzta_tms.url'),
        );

        $upserted = 0;
        $failed = 0;

        foreach ($client->features() as $feature) {
            try {
                $attrs = $feature['attributes'] ?? [];
                $geom = $feature['geometry'] ?? null;

                $externalId = (string) ($attrs['siteRef'] ?? $attrs['SITE_NUMBER'] ?? $attrs['OBJECTID'] ?? '');
                if ($externalId === '' || ! isset($geom['x'], $geom['y'])) {
                    $failed++;
                    continue;
                }

                SiteUpsert::upsert(
                    $source->id,
                    $externalId,
                    (float) $geom['x'],
                    (float) $geom['y'],
                    [
                        'road_name' => self::stringOrNull($attrs['roadName'] ?? $attrs['ROAD_NAME'] ?? null),
                        'region' => self::stringOrNull($attrs['regionName'] ?? null),
                        'aadt' => self::intOrNull($attrs['aadt'] ?? $attrs['AADT'] ?? null),
                        'heavy_vehicle_pct' => self::floatOrNull($attrs['percentHeavy'] ?? $attrs['HEAVY_PCT'] ?? null),
                        'count_date' => self::dateOrNull($attrs['endDate'] ?? $attrs['LAST_UPDATED'] ?? null),
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

    private static function stringOrNull(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : null;
    }

    private static function intOrNull(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    private static function floatOrNull(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    private static function dateOrNull(mixed $v): ?string
    {
        if (is_numeric($v)) {
            return Carbon::createFromTimestampMs((int) $v)->toDateString();
        }
        if (is_string($v) && $v !== '') {
            try {
                return Carbon::parse($v)->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }
        return null;
    }
}
