<?php

namespace Tests\Unit;

use App\Support\NzgttmTable1;
use PHPUnit\Framework\TestCase;

class NzgttmTable1Test extends TestCase
{
    /**
     * Verification target from the plan: at 50 km/h sealed, every row in
     * Table 1 (NZGTTM 2023, p.69) must match the published value.
     */
    public function test_50_kmh_sealed_matches_published_table1(): void
    {
        $rows = $this->keyed(NzgttmTable1::lookup(50, 'sealed'));

        $expected = [
            'sign_visibility_m' => 30,
            'sign_visibility_s' => 2,
            'warning_distance_m' => 50,
            'warning_distance_s' => 4,
            'sign_spacing_m' => 25,
            'longitudinal_exclusion_m' => 50,
            'longitudinal_exclusion_s' => 4,
            'lateral_exclusion_m' => 1,
            'taper_length_m' => 50,
            'taper_length_s' => 3.5,
            'distance_between_tapers_m' => 50,
            'distance_between_tapers_s' => 4,
            'lane_width_m' => 3,
            'delineation_straight_m' => 5,
            'delineation_curve_m' => 2.5,
            'min_curve_radius_m' => 100,
            'clear_sight_m' => 165,
            'clear_sight_s' => 12,
            'sep_tail_pilot_to_work_min_m' => 45,
            'sep_tail_pilot_to_work_min_s' => 3,
            'sep_tail_pilot_to_work_max_m' => 90,
            'sep_tail_pilot_to_work_max_s' => 6,
            'sep_shadow_to_work_m' => 25,
            'sep_shadow_to_work_s' => 2,
        ];

        foreach ($expected as $key => $val) {
            $this->assertSame($val, $rows[$key]['value'], "row {$key}");
        }
    }

    public function test_50_kmh_unsealed_uses_unsealed_longitudinal_row(): void
    {
        $rows = $this->keyed(NzgttmTable1::lookup(50, 'unsealed'));
        $this->assertSame(60, $rows['longitudinal_exclusion_m']['value']);
        $this->assertSame(4, $rows['longitudinal_exclusion_s']['value']);
    }

    public function test_invalid_speed_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        NzgttmTable1::lookup(55, 'sealed');
    }

    /** @param array<int, array{key:string}> $rows */
    private function keyed(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $out[$r['key']] = $r;
        }
        return $out;
    }
}
