<?php

namespace App\Console\Commands;

use App\Jobs\RecomputeNzgttmLevels;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Two phases:
 *  - Phase 1 (snap): single bulk UPDATE that copies the smallest-area NSLR
 *    speed-limit zone speed onto each count_sites row. Drives map marker
 *    colour and the calculator pre-fill. Always run.
 *  - Phase 2 (classify): per-row UPDATE that writes the deprecated
 *    nzgttm_level column. The 2023 NZGTTM retired road levels and no UI
 *    reads this column any more, but the legacy bulk loop is slow on
 *    large NZTA datasets. Default skip; pass --classify if you really
 *    want to repopulate the deprecated column.
 */
class NzgttmRecomputeCommand extends Command
{
    protected $signature = 'nzgttm:recompute {--classify : Also rewrite the deprecated nzgttm_level column}';

    protected $description = 'Snap count sites to NSLR speed-limit zones; optionally rewrite the deprecated nzgttm_level column.';

    public function handle(): int
    {
        $this->info('Phase 1: snapping NSLR speed-limit zone → count_sites.speed_limit_kmh…');
        $started = microtime(true);

        // The bulk LATERAL UPDATE form scans the spatial index for every
        // candidate site in one transaction and stalls indefinitely on
        // shared MySQL hosts even after dropping the ORDER BY. Chunk it
        // into per-site updates instead — 2k sites × ~50 ms each = ~100 s
        // and the progress bar makes it visible.
        $sites = DB::table('count_sites')
            ->whereNotNull('location')
            ->select('id')
            ->orderBy('id')
            ->get();

        $total = $sites->count();
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%  %elapsed:6s% / ~%estimated:-6s%');
        $bar->start();

        // Scalar-subquery SET form is consistently faster than LATERAL on
        // shared MySQL. Plus we read the site's location once, so the
        // optimiser can pin the spatial index lookup to a single point.
        $matched = 0;
        foreach ($sites as $row) {
            $hit = DB::selectOne(<<<'SQL'
                SELECT rs.speed_limit_kmh AS speed
                FROM count_sites cs
                JOIN road_segments rs FORCE INDEX (road_segments_geom_spatial)
                  ON rs.kind = 'speed_limit'
                 AND MBRContains(rs.geom, cs.location)
                WHERE cs.id = ?
                  AND ST_Contains(rs.geom, cs.location)
                LIMIT 1
            SQL, [$row->id]);

            if ($hit !== null && $hit->speed !== null) {
                DB::update('UPDATE count_sites SET speed_limit_kmh = ? WHERE id = ?', [
                    (int) $hit->speed, $row->id,
                ]);
                $matched++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $secs = round(microtime(true) - $started, 1);
        $this->info("Phase 1 done in {$secs}s. Snapped {$matched}/{$total} sites.");

        if ($this->option('classify')) {
            $this->info('Phase 2: rewriting deprecated nzgttm_level (slow, per-row)…');
            (new RecomputeNzgttmLevels())->handle();
            $this->info('Phase 2 done.');
        } else {
            $this->line('Skipping deprecated nzgttm_level rewrite. Pass --classify if you need it.');
        }

        return self::SUCCESS;
    }
}
