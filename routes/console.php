<?php

use App\Sync\CasCrashSync;
use App\Sync\CouncilTrafficCountSync;
use App\Sync\NslrSpeedLimitSync;
use App\Sync\NzRoadsCentrelineSync;
use App\Sync\NztaStateHighwayAadtSync;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new NztaStateHighwayAadtSync)
    ->weeklyOn(1, '02:30')
    ->name('sync_nzta_aadt')
    ->onOneServer();

// After the NSLR sync, run the chunked nzgttm:recompute command rather
// than dispatching RecomputeNzgttmLevels — the bulk LATERAL UPDATE in
// that job's snapSpeedLimitsToSites() stalls indefinitely on shared
// MySQL (its own docstring warns about it). The artisan command uses
// the per-site chunked path with FORCE INDEX.
Schedule::job(new NslrSpeedLimitSync)
    ->dailyAt('03:00')
    ->name('sync_nslr')
    ->onOneServer()
    ->after(fn () => Artisan::call('nzgttm:recompute'));

// NZ Roads centrelines change rarely (RCA boundaries / new subdivisions).
// Monthly is plenty and avoids re-ingesting ~600k segments unnecessarily.
Schedule::job(new NzRoadsCentrelineSync)
    ->monthlyOn(1, '04:00')
    ->name('sync_nz_roads_centrelines')
    ->onOneServer();

// CAS publishes monthly. Run weekly so we pick up republished revisions
// of recent crashes without doing the ~100k-row pull every day.
Schedule::job(new CasCrashSync)
    ->weeklyOn(3, '03:30')
    ->name('sync_cas')
    ->onOneServer();

// Fan out to every council/RCA traffic-count source registered in
// config/council_sources.php. Sources without a configured FeatureServer URL
// no-op cheaply, so a single weekly schedule covers the whole list. Stagger
// by source-key offset so we don't hammer NZ council ArcGIS hosts in parallel.
foreach (array_keys((array) config('council_sources', [])) as $i => $councilKey) {
    $hour = 4 + intdiv($i, 12);                 // 04:xx … 09:xx
    $minute = ($i % 12) * 5;                    // 0,5,10,…,55
    Schedule::job(new CouncilTrafficCountSync(sourceKey: $councilKey))
        ->weeklyOn(2, sprintf('%02d:%02d', $hour, $minute))
        ->name("sync_council_{$councilKey}")
        ->onOneServer();
}
