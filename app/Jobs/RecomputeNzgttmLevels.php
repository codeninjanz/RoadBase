<?php

namespace App\Jobs;

use App\Support\Nzgttm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * For every count_sites row, find the NSLR speed-limit zone (polygon) that
 * contains the site's location and copy the speed limit across, then apply
 * the NZGTTM classification rules to set nzgttm_level.
 *
 * Two zones can overlap (e.g. an urban 50 km/h zone with a 30 km/h school zone
 * inside it). We pick the highest-precision zone (smallest ST_Area), which
 * matches NZGTTM's intent — site-specific posted limits override defaults.
 */
class RecomputeNzgttmLevels implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public function handle(): void
    {
        $this->snapSpeedLimitsToSites();
        $this->classifySites();
    }

    private function snapSpeedLimitsToSites(): void
    {
        DB::statement(<<<'SQL'
            UPDATE count_sites cs
            JOIN LATERAL (
                SELECT rs.speed_limit_kmh
                FROM road_segments rs
                WHERE rs.kind = 'speed_limit'
                  AND MBRContains(rs.geom, cs.location)
                  AND ST_Contains(rs.geom, cs.location)
                ORDER BY ST_Area(rs.geom) ASC
                LIMIT 1
            ) z ON TRUE
            SET cs.speed_limit_kmh = z.speed_limit_kmh
        SQL);
    }

    private function classifySites(): void
    {
        DB::table('count_sites')
            ->whereNotNull('aadt')
            ->orderBy('id')
            ->chunkById(2000, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('count_sites')
                        ->where('id', $row->id)
                        ->update([
                            'nzgttm_level' => Nzgttm::classify($row->aadt, $row->speed_limit_kmh),
                        ]);
                }
            });
    }
}
