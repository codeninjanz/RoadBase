<?php

namespace App\Jobs;

use App\Support\Nzgttm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RecomputeNzgttmLevels implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    /** Buffer (metres) used when snapping a count site to the nearest speed-limit segment. */
    private const SPEED_SNAP_METRES = 25;

    public function handle(): void
    {
        $this->snapSpeedLimitsToSites();
        $this->snapSpeedLimitsToAadtLines();
        $this->classifySites();
        $this->classifyAadtLines();
    }

    private function snapSpeedLimitsToSites(): void
    {
        // For every count site, find the nearest speed_limit segment within
        // SPEED_SNAP_METRES and copy its speed_limit_kmh.
        DB::statement(<<<'SQL'
            UPDATE count_sites cs
            JOIN LATERAL (
                SELECT rs.speed_limit_kmh
                FROM road_segments rs
                WHERE rs.kind = 'speed_limit'
                  AND ST_Distance_Sphere(cs.location, rs.geom) <= ?
                ORDER BY ST_Distance_Sphere(cs.location, rs.geom) ASC
                LIMIT 1
            ) nearest ON TRUE
            SET cs.speed_limit_kmh = nearest.speed_limit_kmh
        SQL, [self::SPEED_SNAP_METRES]);
    }

    private function snapSpeedLimitsToAadtLines(): void
    {
        // Use the centroid (line midpoint approximation) of an AADT line to
        // join — line-to-line nearest is expensive.
        DB::statement(<<<'SQL'
            UPDATE road_segments aadt
            JOIN LATERAL (
                SELECT rs.speed_limit_kmh
                FROM road_segments rs
                WHERE rs.kind = 'speed_limit'
                  AND ST_Distance_Sphere(
                        ST_PointN(aadt.geom, GREATEST(1, ST_NumPoints(aadt.geom) DIV 2)),
                        rs.geom
                      ) <= ?
                ORDER BY ST_Distance_Sphere(
                        ST_PointN(aadt.geom, GREATEST(1, ST_NumPoints(aadt.geom) DIV 2)),
                        rs.geom
                      ) ASC
                LIMIT 1
            ) nearest ON TRUE
            SET aadt.speed_limit_kmh = nearest.speed_limit_kmh
            WHERE aadt.kind = 'aadt_line'
        SQL, [self::SPEED_SNAP_METRES]);
    }

    private function classifySites(): void
    {
        DB::table('count_sites')->whereNotNull('aadt')->orderBy('id')->chunkById(2000, function ($rows) {
            foreach ($rows as $row) {
                $level = Nzgttm::classify($row->aadt, $row->speed_limit_kmh);
                DB::table('count_sites')->where('id', $row->id)->update(['nzgttm_level' => $level]);
            }
        });
    }

    private function classifyAadtLines(): void
    {
        DB::table('road_segments')
            ->where('kind', 'aadt_line')
            ->whereNotNull('aadt')
            ->orderBy('id')
            ->chunkById(2000, function ($rows) {
                foreach ($rows as $row) {
                    $level = Nzgttm::classify($row->aadt, $row->speed_limit_kmh);
                    DB::table('road_segments')->where('id', $row->id)->update(['nzgttm_level' => $level]);
                }
            });
    }
}
