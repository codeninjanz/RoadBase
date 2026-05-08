<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Config-driven sync for council / district-council Average-Daily-Traffic
 * count sites. One class handles every Road Controlling Authority on the
 * mobileroad.org list (Auckland Transport, Christchurch CC, Hamilton CC,
 * Hutt CC, ...). Each RCA is registered in config/council_sources.php with
 * its FeatureServer URL, rca display name, and field aliases for the seven
 * count_sites columns that vary between councils.
 *
 * Doesn't extend AbstractSyncJob because that base class assumes one fixed
 * sourceKey() per class — we need an instance-level key so a single class
 * can be re-dispatched for every council.
 */
class CouncilTrafficCountSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public string $sourceKey, public bool $force = false) {}

    public function handle(): void
    {
        $cfg = config("council_sources.{$this->sourceKey}");
        if (! is_array($cfg)) {
            Log::warning("CouncilTrafficCountSync: no config for {$this->sourceKey} — skipping.");
            return;
        }

        // Self-heal: create the data_sources row from the registry on first
        // run so operators don't have to re-seed every time a new RCA is
        // added to config/council_sources.php.
        $source = DataSource::updateOrCreate(
            ['key' => $this->sourceKey],
            [
                'key' => $this->sourceKey,
                'name' => $cfg['name'] ?? $this->sourceKey,
                'url' => $cfg['url'] ?? null,
                'update_frequency_hours' => $cfg['update_frequency_hours'] ?? 24 * 30,
            ],
        );

        if (! $this->force && ! $source->isStale()) {
            Log::info("Skipping council sync for {$source->key} — last sync is fresh.");
            return;
        }

        $run = $source->syncRuns()->create([
            'started_at' => now(),
            'status' => 'running',
        ]);

        try {
            $this->runSync($run, $source, $cfg);

            $run->update([
                'finished_at' => now(),
                'status' => 'success',
            ]);

            $source->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_error' => null,
            ]);
        } catch (Throwable $e) {
            Log::error("Council sync failed for {$source->key}: {$e->getMessage()}", [
                'exception' => $e,
            ]);
            $run->update([
                'finished_at' => now(),
                'status' => 'failed',
                'error_message' => substr($e->getMessage(), 0, 60_000),
            ]);
            $source->update([
                'last_synced_at' => now(),
                'last_sync_status' => 'failed',
                'last_sync_error' => substr($e->getMessage(), 0, 60_000),
            ]);
            throw $e;
        }
    }

    private function runSync(SyncRun $run, DataSource $source, array $cfg): void
    {
        $url = $cfg['url'] ?? null;
        if (! $url) {
            // Council registered but its FeatureServer URL isn't configured
            // yet — record a successful no-op so the dashboard reports zero
            // upserts rather than a hard failure.
            $run->update(['records_upserted' => 0, 'records_failed' => 0]);
            return;
        }

        $client = new ArcgisFeatureClient(
            featureUrl: $url,
            where: $cfg['where'] ?? '1=1',
        );

        $aliases = $cfg['fields'] ?? [];
        $rcaName = $cfg['rca'] ?? null;

        $upserted = 0;
        $failed = 0;

        foreach ($client->features() as $feature) {
            try {
                $props = $feature['properties'] ?? [];
                $geom = $feature['geometry'] ?? null;

                // Only point feeds are meaningful here. Council ADT layers
                // are usually points; some publish line segments — skip those
                // (a future enhancement could centroid them).
                if (! $geom || ($geom['type'] ?? '') !== 'Point') {
                    $failed++;
                    continue;
                }
                [$lng, $lat] = $geom['coordinates'];
                if (! is_numeric($lng) || ! is_numeric($lat)) {
                    $failed++;
                    continue;
                }

                $externalId = self::firstString($props, $aliases['external_id'] ?? ['OBJECTID']);
                if ($externalId === null) {
                    $failed++;
                    continue;
                }

                [$aadt, $aadtAlias] = self::firstIntWithAlias($props, $aliases['aadt'] ?? []);
                $heavyPct = self::firstFloat($props, $aliases['heavy_pct'] ?? []);
                $peakVol = self::firstInt($props, $aliases['peak_hour_volume'] ?? []);
                $speedLimit = self::firstInt($props, $aliases['speed_limit'] ?? []);
                $countDate = self::firstDate($props, $aliases['count_date'] ?? []);
                $roadName = self::firstString($props, $aliases['road_name'] ?? []);

                // Per-year pivot feeds (Hamilton CC and similar) carry the
                // year only in the column name, e.g. AADT came from
                // 'Year2023'. When the explicit date column is missing,
                // back-fill count_date as Jan 1 of the matched year so the
                // detail panel and exports still report when the count was
                // taken.
                if ($countDate === null && $aadtAlias !== null
                    && preg_match('/^Year(\d{4})$/i', $aadtAlias, $m)) {
                    $countDate = "{$m[1]}-01-01";
                }

                SiteUpsert::upsert(
                    $source->id,
                    $externalId,
                    (float) $lng,
                    (float) $lat,
                    [
                        'road_name' => $roadName,
                        'rca' => $rcaName,
                        'aadt' => $aadt,
                        'heavy_vehicle_pct' => $heavyPct,
                        'peak_hour_volume' => $peakVol,
                        'speed_limit_kmh' => $speedLimit,
                        'count_date' => $countDate,
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
        if (is_string($v) && $v !== '') return $v;
        if (is_numeric($v)) return (string) $v;
        return null;
    }

    /** @param array<string, mixed> $props @param array<int, string> $keys */
    private static function firstString(array $props, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = self::stringOrNull($props[$k] ?? null);
            if ($v !== null) return $v;
        }
        return null;
    }

    /** @param array<string, mixed> $props @param array<int, string> $keys */
    private static function firstInt(array $props, array $keys): ?int
    {
        return self::firstIntWithAlias($props, $keys)[0];
    }

    /**
     * Like firstInt but also returns which alias matched. Lets the caller
     * derive metadata from the column name (e.g. "Year2023" → count_date
     * 2023-01-01).
     *
     * @param  array<string, mixed>  $props
     * @param  array<int, string>  $keys
     * @return array{0:?int, 1:?string}
     */
    private static function firstIntWithAlias(array $props, array $keys): array
    {
        foreach ($keys as $k) {
            $v = $props[$k] ?? null;
            if (is_numeric($v)) {
                return [(int) $v, $k];
            }
        }
        return [null, null];
    }

    /** @param array<string, mixed> $props @param array<int, string> $keys */
    private static function firstFloat(array $props, array $keys): ?float
    {
        foreach ($keys as $k) {
            $v = $props[$k] ?? null;
            if (is_numeric($v)) {
                $f = (float) $v;
                // Some councils publish heavy-vehicle as a 0..1 ratio rather
                // than a percent. Normalise to percent (0..100) so it lines up
                // with NZTA's percentheavy column.
                if ($f > 0 && $f <= 1.0) {
                    $f *= 100;
                }
                return $f;
            }
        }
        return null;
    }

    /**
     * ArcGIS commonly emits dates as epoch milliseconds (integer); some
     * councils stringify them as ISO-8601 or "YYYY-MM-DD". Accept either.
     *
     * @param array<string, mixed> $props @param array<int, string> $keys
     */
    private static function firstDate(array $props, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = $props[$k] ?? null;
            if ($v === null || $v === '') continue;
            try {
                if (is_numeric($v)) {
                    // Epoch ms — values smaller than 10^11 are plausibly seconds.
                    $n = (float) $v;
                    $ts = $n > 1e11 ? (int) ($n / 1000) : (int) $n;
                    return Carbon::createFromTimestampUTC($ts)->toDateString();
                }
                if (is_string($v)) {
                    return Carbon::parse($v)->toDateString();
                }
            } catch (\Throwable) {
                continue;
            }
        }
        return null;
    }
}
