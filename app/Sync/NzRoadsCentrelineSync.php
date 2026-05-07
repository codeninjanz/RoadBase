<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;

/**
 * Sync NZTA's national NZ Roads centreline dataset (the public, RCA-attributed
 * road-section feature service that aggregates every road maintained by every
 * Road Controlling Authority — state highways, council roads, DOC tracks,
 * KiwiRail level crossings, etc.).
 *
 * One feed gives us coverage for every RCA on the mobileroad.org list
 * (Auckland Transport, Christchurch CC, Dunedin CC, all districts, ...).
 * Each feature carries:
 *   - LineString geometry (occasionally MultiLineString)
 *   - road_name / fullName
 *   - rcaName (display) and rcaCode (Stats NZ TA code)
 *   - hierarchy / classification (Motorway, Arterial, Collector, Local, ...)
 *
 * Upstream field names vary by vintage of the published service, so we
 * resolve each property against a list of known aliases (same pattern as
 * RcaTaSync). If NZ_ROADS_CENTRELINES_URL isn't configured the job no-ops,
 * matching the AADT-lines stub.
 */
class NzRoadsCentrelineSync extends AbstractSyncJob
{
    public static function sourceKey(): string
    {
        return 'nz_roads_centrelines';
    }

    protected function runSync(SyncRun $run, DataSource $source): void
    {
        $url = config('services.nz_roads.centrelines_url');
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

                if (! $geom || ! in_array($geom['type'] ?? '', ['LineString', 'MultiLineString'], true)) {
                    $failed++;
                    continue;
                }

                $externalId = self::firstString($props, [
                    'roadSectionId', 'roadSectionID', 'sectionId',
                    'GlobalID', 'globalId', 'OBJECTID',
                ]) ?? '';

                if ($externalId === '') {
                    $failed++;
                    continue;
                }

                $roadName = self::firstString($props, [
                    'roadSectionName', 'roadName', 'fullRoadName', 'name', 'descr',
                ]);
                $rcaName = self::firstString($props, [
                    'rcaName', 'roadSectionRcaName', 'rca', 'rcaDescription',
                ]);
                $rcaCode = self::firstString($props, [
                    'rcaCode', 'rcaId', 'taCode', 'TA2026_V1_00', 'TA2025_V1_00',
                ]);
                $hierarchy = self::firstString($props, [
                    'hierarchy', 'roadSectionHierarchy', 'roadHierarchy',
                    'classification', 'roadClassification',
                ]);

                ZoneUpsert::upsert(
                    $source->id,
                    $externalId,
                    'centreline',
                    json_encode($geom),
                    [
                        'road_name' => $roadName,
                        'rca' => $rcaName,
                        'rca_code' => $rcaCode,
                        'hierarchy' => $hierarchy,
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
        return is_string($v) && $v !== '' ? $v : (is_numeric($v) ? (string) $v : null);
    }

    /** @param array<string, mixed> $props @param array<int, string> $keys */
    private static function firstString(array $props, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = self::stringOrNull($props[$k] ?? null);
            if ($v !== null) {
                return $v;
            }
        }
        return null;
    }
}
