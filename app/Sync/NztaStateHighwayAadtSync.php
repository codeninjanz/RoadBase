<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;

class NztaStateHighwayAadtSync extends AbstractSyncJob
{
    public static function sourceKey(): string
    {
        return 'nzta_aadt';
    }

    protected function runSync(SyncRun $run, DataSource $source): void
    {
        $upserted = 0;
        $failed = 0;

        // Point sites — annual AADT count locations.
        if ($sitesUrl = config('services.nzta_aadt.sites_url')) {
            foreach ((new ArcgisFeatureClient($sitesUrl))->features() as $feature) {
                try {
                    $attrs = $feature['attributes'] ?? [];
                    $geom = $feature['geometry'] ?? null;

                    $externalId = (string) ($attrs['siteId'] ?? $attrs['SITE_ID'] ?? $attrs['OBJECTID'] ?? '');
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
                            'road_name' => $attrs['roadName'] ?? $attrs['SH_NAME'] ?? null,
                            'aadt' => self::intOrNull($attrs['aadt'] ?? $attrs['AADT'] ?? null),
                            'heavy_vehicle_pct' => self::floatOrNull($attrs['heavyPct'] ?? $attrs['HEAVY_PCT'] ?? null),
                            'raw_payload' => $attrs,
                            'synced_at' => now(),
                        ]
                    );
                    $upserted++;
                } catch (\Throwable) {
                    $failed++;
                }
            }
        }

        // Estimated AADT lines between sites.
        if ($linesUrl = config('services.nzta_aadt.lines_url')) {
            foreach ((new ArcgisFeatureClient($linesUrl))->features() as $feature) {
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

                    $externalId = (string) ($attrs['OBJECTID'] ?? $attrs['segmentId'] ?? '');
                    if ($externalId === '') {
                        $failed++;
                        continue;
                    }

                    SegmentUpsert::upsert(
                        $source->id,
                        $externalId,
                        'aadt_line',
                        $wkt,
                        [
                            'road_name' => $attrs['roadName'] ?? $attrs['SH_NAME'] ?? null,
                            'aadt' => self::intOrNull($attrs['aadt'] ?? $attrs['AADT'] ?? null),
                            'heavy_vehicle_pct' => self::floatOrNull($attrs['heavyPct'] ?? $attrs['HEAVY_PCT'] ?? null),
                            'raw_payload' => $attrs,
                            'synced_at' => now(),
                        ]
                    );
                    $upserted++;
                } catch (\Throwable) {
                    $failed++;
                }
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

    private static function floatOrNull(mixed $v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }
}
