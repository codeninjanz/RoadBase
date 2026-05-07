<?php

namespace App\Console\Commands;

use App\Jobs\RecomputeNzgttmLevels;
use App\Sync\AbstractSyncJob;
use App\Sync\CouncilTrafficCountSync;
use App\Sync\NslrSpeedLimitSync;
use App\Sync\NzRoadsCentrelineSync;
use App\Sync\NztaStateHighwayAadtSync;
use App\Sync\RcaTaSync;
use Illuminate\Console\Command;

class SyncRunCommand extends Command
{
    protected $signature = 'sync:run {source : Source key (nzta_aadt, nslr, stats_nz_ta, nz_roads_centrelines, councils, all, or any council key)} {--force : Run even if last sync is fresh} {--no-recompute : Skip the NZGTTM recompute after run}';

    protected $description = 'Run a data-source sync inline (not queued).';

    /** @var array<string, class-string<AbstractSyncJob>> */
    private array $jobs = [
        'nzta_aadt' => NztaStateHighwayAadtSync::class,
        'nslr' => NslrSpeedLimitSync::class,
        'stats_nz_ta' => RcaTaSync::class,
        'nz_roads_centrelines' => NzRoadsCentrelineSync::class,
    ];

    public function handle(): int
    {
        $source = $this->argument('source');
        $force = (bool) $this->option('force');

        /** @var array<int, string> $councilKeys */
        $councilKeys = array_keys((array) config('council_sources', []));

        $keys = match (true) {
            $source === 'all' => array_merge(array_keys($this->jobs), $councilKeys),
            $source === 'councils' => $councilKeys,
            default => [$source],
        };

        foreach ($keys as $key) {
            if (isset($this->jobs[$key])) {
                $this->info("Syncing {$key}…");
                $job = new ($this->jobs[$key])(force: $force);
                $job->handle();
                $this->info("Done {$key}.");
                continue;
            }

            if (in_array($key, $councilKeys, true)) {
                $this->info("Syncing council {$key}…");
                (new CouncilTrafficCountSync(sourceKey: $key, force: $force))->handle();
                $this->info("Done {$key}.");
                continue;
            }

            $this->error("Unknown source key: {$key}");
            return self::FAILURE;
        }

        if (! $this->option('no-recompute')) {
            $this->info('Recomputing NZGTTM levels…');
            (new RecomputeNzgttmLevels)->handle();
            $this->info('Recompute done.');
        }

        return self::SUCCESS;
    }
}
