<?php

use App\Jobs\RecomputeNzgttmLevels;
use App\Sync\NslrSpeedLimitSync;
use App\Sync\NzRoadsCentrelineSync;
use App\Sync\NztaStateHighwayAadtSync;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new NztaStateHighwayAadtSync)
    ->weeklyOn(1, '02:30')
    ->name('sync_nzta_aadt')
    ->onOneServer();

Schedule::job(new NslrSpeedLimitSync)
    ->dailyAt('03:00')
    ->name('sync_nslr')
    ->onOneServer()
    ->after(fn () => dispatch(new RecomputeNzgttmLevels));

// NZ Roads centrelines change rarely (RCA boundaries / new subdivisions).
// Monthly is plenty and avoids re-ingesting ~600k segments unnecessarily.
Schedule::job(new NzRoadsCentrelineSync)
    ->monthlyOn(1, '04:00')
    ->name('sync_nz_roads_centrelines')
    ->onOneServer();
