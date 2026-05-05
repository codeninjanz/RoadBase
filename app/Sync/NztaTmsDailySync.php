<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Support\Facades\Log;

/**
 * Stub: TMS_Telemetry_Sites publishes per-lane daily count records, not AADT.
 *
 * Computing AADT from these counts requires aggregating ~365 daily records per
 * site per direction per lane and applying NZTA's seasonal/day-of-week weighting.
 * That's a Phase 2 feature — for MVP we use Assets_SHTrafficMonitoringSites
 * (NztaStateHighwayAadtSync), which publishes pre-computed annual AADT.
 *
 * This class is kept so the sync_runs audit trail can record that we
 * intentionally skipped the source rather than failed silently.
 */
class NztaTmsDailySync extends AbstractSyncJob
{
    public static function sourceKey(): string
    {
        return 'nzta_tms';
    }

    protected function runSync(SyncRun $run, DataSource $source): void
    {
        Log::info('Skipping NZTA TMS sync — daily count → AADT aggregation is a Phase 2 feature.');
        $run->update([
            'records_upserted' => 0,
            'records_failed' => 0,
            'error_message' => 'Source not yet implemented (Phase 2).',
        ]);
    }
}
