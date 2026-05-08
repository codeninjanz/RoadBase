<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Support\Facades\DB;

/**
 * Sync NZTA's Crash Analysis System (CAS) public feed into the crashes table.
 *
 * CAS is the authoritative national crash record — every reported crash on
 * every road in NZ since 2000 — and what RoadBase uses for the
 * "Crashes (5 yr, 1 km)" stat on the site detail panel. Filters to the last
 * five complete years on the upstream side so we don't drag ~800k rows
 * across the wire when only the recent window is read by the UI.
 *
 * Severity values come back from the upstream as either single-letter
 * codes ("F","S","M","N") or full strings ("Fatal Crash"/"Non-Injury
 * Crash"); both are normalised to the enum on the crashes table.
 */
class CasCrashSync extends AbstractSyncJob
{
    public int $timeout = 3600;

    /** Match SiteController's crash-density window so the panel stat is
     * never querying outside the synced range. */
    private const RETAIN_YEARS = 5;

    public static function sourceKey(): string
    {
        return 'cas';
    }

    protected function runSync(SyncRun $run, DataSource $source): void
    {
        $url = config('services.cas.url');
        if (! $url) {
            $run->update(['records_upserted' => 0, 'records_failed' => 0]);
            return;
        }

        // Filter at the FeatureServer rather than client-side: ArcGIS
        // happily evaluates numeric comparisons in the WHERE param and we
        // avoid pulling years we'll never read.
        $minYear = (int) date('Y') - self::RETAIN_YEARS;
        $where = "crashYear >= {$minYear}";

        $client = new ArcgisFeatureClient(featureUrl: $url, where: $where);

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
                if (! is_numeric($lng) || ! is_numeric($lat)) {
                    $failed++;
                    continue;
                }

                $externalId = (string) ($props['OBJECTID']
                    ?? $props['objectId']
                    ?? $props['crashId']
                    ?? '');
                if ($externalId === '') {
                    $failed++;
                    continue;
                }

                self::upsert(
                    $externalId,
                    (float) $lng,
                    (float) $lat,
                    [
                        'road_name' => self::firstString($props, [
                            'crashLocation1', 'crashLocation2', 'roadName', 'tlaName',
                        ]),
                        'severity' => self::normaliseSeverity($props['crashSeverity']
                            ?? $props['severity'] ?? null),
                        'speed_limit_kmh' => self::intOrNull($props['speedLimit'] ?? $props['advisorySpeed'] ?? null),
                        'crash_year' => self::intOrNull($props['crashYear'] ?? null),
                        'raw_payload' => $props,
                        'synced_at' => now(),
                    ]
                );
                $upserted++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        // Drop crash rows that fell out of the rolling window so the table
        // stays bounded — without this, every quarterly republish would
        // accumulate stale years forever.
        DB::table('crashes')
            ->where('crash_year', '<', $minYear)
            ->delete();

        $run->update([
            'records_upserted' => $upserted,
            'records_failed' => $failed,
        ]);
    }

    private static function upsert(string $externalId, float $lng, float $lat, array $fields): void
    {
        $columns = ['external_id', 'location'];
        $placeholders = ['?', 'ST_SRID(POINT(?, ?), 4326)'];
        $bindings = [$externalId, $lng, $lat];

        $updates = [];
        foreach ($fields as $col => $value) {
            $columns[] = $col;
            $placeholders[] = '?';
            $bindings[] = is_array($value)
                ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : $value;
            $updates[] = "{$col} = VALUES({$col})";
        }

        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';

        $sql = sprintf(
            'INSERT INTO crashes (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s, location = VALUES(location), updated_at = NOW()',
            implode(',', $columns),
            implode(',', $placeholders),
            implode(',', $updates)
        );

        DB::statement($sql, $bindings);
    }

    /**
     * CAS publishes severity as a single letter (F/S/M/N) on some layer
     * vintages and as a full label ("Fatal Crash"/"Non-Injury Crash") on
     * others. Map either form onto the table enum.
     */
    private static function normaliseSeverity(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $s = strtolower(trim($raw));
        return match (true) {
            $s === 'f' || str_contains($s, 'fatal') => 'fatal',
            $s === 's' || str_contains($s, 'serious') => 'serious',
            $s === 'm' || (str_contains($s, 'minor') && ! str_contains($s, 'non')) => 'minor',
            $s === 'n' || str_contains($s, 'non-injury') || str_contains($s, 'non injury') => 'non_injury',
            default => null,
        };
    }

    private static function intOrNull(mixed $v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    private static function stringOrNull(mixed $v): ?string
    {
        return is_string($v) && $v !== '' ? $v : null;
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
