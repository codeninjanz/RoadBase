<?php

namespace App\Console\Commands;

use App\Jobs\RecomputeNzgttmLevels;
use App\Sync\AbstractSyncJob;
use App\Sync\NslrSpeedLimitSync;
use App\Sync\NztaStateHighwayAadtSync;
use App\Sync\NztaTmsDailySync;
use Illuminate\Console\Command;

class SyncRunCommand extends Command
{
    protected $signature = 'sync:run {source : Source key (nzta_tms, nzta_aadt, nslr, all)} {--force : Run even if last sync is fresh} {--no-recompute : Skip the NZGTTM recompute after run}';

    protected $description = 'Run a data-source sync inline (not queued).';

    /** @var array<string, class-string<AbstractSyncJob>> */
    private array $jobs = [
        'nzta_tms' => NztaTmsDailySync::class,
        'nzta_aadt' => NztaStateHighwayAadtSync::class,
        'nslr' => NslrSpeedLimitSync::class,
    ];

    public function handle(): int
    {
        $source = $this->argument('source');
        $force = (bool) $this->option('force');

        $keys = $source === 'all' ? array_keys($this->jobs) : [$source];

        foreach ($keys as $key) {
            if (! isset($this->jobs[$key])) {
                $this->error("Unknown source key: {$key}");
                return self::FAILURE;
            }

            $this->info("Syncing {$key}…");
            $job = new ($this->jobs[$key])(force: $force);
            $job->handle();
            $this->info("Done {$key}.");
        }

        if (! $this->option('no-recompute') && in_array('nslr', $keys, true)) {
            $this->info('Recomputing NZGTTM levels…');
            (new RecomputeNzgttmLevels())->handle();
            $this->info('Recompute done.');
        }

        return self::SUCCESS;
    }
}
