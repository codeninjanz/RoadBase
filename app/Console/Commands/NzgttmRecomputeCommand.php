<?php

namespace App\Console\Commands;

use App\Jobs\RecomputeNzgttmLevels;
use Illuminate\Console\Command;

class NzgttmRecomputeCommand extends Command
{
    protected $signature = 'nzgttm:recompute';

    protected $description = 'Snap count sites/AADT lines to nearest NSLR speed limit and recompute NZGTTM road levels.';

    public function handle(): int
    {
        $this->info('Recomputing NZGTTM levels…');
        (new RecomputeNzgttmLevels())->handle();
        $this->info('Done.');
        return self::SUCCESS;
    }
}
