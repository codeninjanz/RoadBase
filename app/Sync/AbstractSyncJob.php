<?php

namespace App\Sync;

use App\Models\DataSource;
use App\Models\SyncRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for source-specific sync jobs. Subclasses implement sourceKey() and
 * runSync(SyncRun $run) — the run() wrapper handles run rows, freshness,
 * and source status updates.
 */
abstract class AbstractSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public bool $force = false) {}

    abstract public static function sourceKey(): string;

    abstract protected function runSync(SyncRun $run, DataSource $source): void;

    public function handle(): void
    {
        $source = DataSource::where('key', static::sourceKey())->firstOrFail();

        if (! $this->force && ! $source->isStale()) {
            Log::info("Skipping sync for {$source->key} — last sync is fresh.");
            return;
        }

        $run = $source->syncRuns()->create([
            'started_at' => now(),
            'status' => 'running',
        ]);

        try {
            $this->runSync($run, $source);

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
            Log::error("Sync failed for {$source->key}: {$e->getMessage()}", [
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
}
