<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Support\Carbon;

/**
 * Sync NZTA's State Highway Traffic Monitoring Sites (Assets_SHTrafficMonitoringSites).
 * This is the authoritative source of annual AADT for state-highway count locations
 * and is the primary data feeding RoadBase's NZGTTM classification for SH roads.
 *
 * Key fields (verified against the live FeatureServer):
 *   siteref, region, sh, rs, rp, description, lane, type, sitetype,
 *   percentheavy, aadt5yearsago … aadt1yearago, OBJECTID
 *
 * `aadt1yearago` is the most recent published annual AADT, which we treat as the
 * site's current AADT. Older years are kept in raw_payload for trend analysis.
 */
class NztaStateHighwayAadtSync extends AbstractSyncJob
{
    public static function sourceKey(): string
    {
        return 'nzta_aadt';
    }

    protected function runSync(SyncRun $run, DataSource $source): void
    {
        $url = config('services.nzta_aadt.sites_url');
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

                if (! $geom || ($geom['type'] ?? '') !== 'Point') {
                    $failed++;
                    continue;
                }
                [$lng, $lat] = $geom['coordinates'];

                $externalId = (string) ($props['siteref'] ?? $props['OBJECTID'] ?? '');
                if ($externalId === '') {
                    $failed++;
                    continue;
                }

                $aadt = self::intOrNull($props['aadt1yearago'] ?? null);
                $description = self::stringOrNull($props['description'] ?? null);
                $sh = self::stringOrNull($props['sh'] ?? null);
                $roadName = $sh ? "SH{$sh}".($description ? " — {$description}" : '') : $description;

                SiteUpsert::upsert(
                    $source->id,
                    $externalId,
                    (float) $lng,
                    (float) $lat,
                    [
                        'road_name' => $roadName,
                        'region' => self::stringOrNull($props['region'] ?? null),
                        'aadt' => $aadt,
                        'heavy_vehicle_pct' => self::floatOrNull($props['percentheavy'] ?? null),
                        // No explicit count_date in this dataset; use the start of the
                        // calendar year before "now" — matches "1 year ago" semantics.
                        'count_date' => Carbon::now()->subYear()->startOfYear()->toDateString(),
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
}
