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

        // ST_Area on geographic MULTIPOLYGON silently produces no rows in
        // MySQL 8 (same restriction family as ST_Simplify / ST_Centroid).
        // Round-trip the geometry through SRID 0 (Cartesian) just for the
        // size sort — degree-squared ranking is fine since we only need
        // smallest-first ordering, not absolute area in m².
        DB::statement(<<<'SQL'
            UPDATE count_sites cs
            JOIN LATERAL (
                SELECT rs.speed_limit_kmh
                FROM road_segments rs
                WHERE rs.kind = 'speed_limit'
                  AND MBRContains(rs.geom, cs.location)
                  AND ST_Contains(rs.geom, cs.location)
                ORDER BY ST_Area(ST_GeomFromText(ST_AsText(rs.geom), 0)) ASC
                LIMIT 1
            ) z ON TRUE
            SET cs.speed_limit_kmh = z.speed_limit_kmh
        SQL);

        $secs = round(microtime(true) - $started, 1);
        $this->info("Phase 1 done in {$secs}s.");

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
