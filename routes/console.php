<?php

use App\Jobs\RecomputeNzgttmLevels;
use App\Sync\NslrSpeedLimitSync;
use App\Sync\NztaStateHighwayAadtSync;
use App\Sync\NztaTmsDailySync;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new NztaTmsDailySync)->dailyAt('02:00')->name('sync_nzta_tms')->onOneServer();

Schedule::job(new NztaStateHighwayAadtSync)->weeklyOn(1, '02:30')->name('sync_nzta_aadt')->onOneServer();

Schedule::job(new NslrSpeedLimitSync)
    ->dailyAt('03:00')
    ->name('sync_nslr')
    ->onOneServer()
    ->after(fn () => dispatch(new RecomputeNzgttmLevels));
