<?php

namespace Tests\Unit;

use App\Support\QueueCalculator;
use PHPUnit\Framework\TestCase;

class QueueCalculatorTest extends TestCase
{
    /**
     * NZGTTM 2023 p.73 worked example: stop/go at flow 1000 vph, stopped 40 s
     * → ~88.9 m queue. Our integerised calculation rounds to 11 vehicles ×
     * 8 m = 88 m; published 88.9 m comes from continuous arithmetic. We
     * accept either form within ≤ 1 m tolerance.
     */
    public function test_stop_go_matches_guide_p73_worked_example(): void
    {
        $r = QueueCalculator::compute([
            'scenario' => QueueCalculator::SCENARIO_STOP_GO,
            'temp_speed_kmh' => 30,
            'lanes_open' => 1,
            'demand_vph' => 1000,
            'duration_hours' => 1.0,
            'stop_seconds' => 40,
            'site_length_m' => 150,
        ]);

        $this->assertEqualsWithDelta(88.9, $r['queue_length_m'], 1.0);
        $this->assertSame(11, $r['queue_vehicles']);
        $this->assertSame(40.0, $r['max_delay_s']);
    }

    /**
     * NZGTTM 2023 p.73: 1500 vph demand vs 1300 vph merge capacity, 2 hours
     * → 400 vehicles queued, ~3.2 km.
     */
    public function test_merge_demand_exceeds_capacity_matches_guide_p73(): void
    {
        $r = QueueCalculator::compute([
            'scenario' => QueueCalculator::SCENARIO_MERGE,
            'temp_speed_kmh' => 50,
            'lanes_open' => 1,
            'demand_vph' => 1500,
            'duration_hours' => 2.0,
            'stop_seconds' => 0,
            'site_length_m' => 0,
        ]);

        $this->assertSame(QueueCalculator::CAPACITY_MERGE, $r['capacity_per_lane_vph']);
        $this->assertSame(400, $r['queue_vehicles']);
        $this->assertEqualsWithDelta(3200.0, $r['queue_length_m'], 1.0);
        $this->assertEqualsWithDelta(3.2, $r['queue_length_km'], 0.01);
    }

    /**
     * NZGTTM 2023 p.73: at 30 km/h or 8.34 m/s a 150 m site takes ~18 s
     * to traverse.
     */
    public function test_site_traverse_time_matches_guide_p73(): void
    {
        $r = QueueCalculator::compute([
            'scenario' => QueueCalculator::SCENARIO_STOP_GO,
            'temp_speed_kmh' => 30,
            'lanes_open' => 1,
            'demand_vph' => 0,
            'duration_hours' => 0,
            'stop_seconds' => 0,
            'site_length_m' => 150,
        ]);

        $this->assertEqualsWithDelta(18.0, $r['site_traverse_s'], 0.5);
    }

    public function test_30_kmh_uses_reduced_capacity(): void
    {
        $this->assertSame(
            QueueCalculator::CAPACITY_30KMH_PER_LANE,
            QueueCalculator::perLaneCapacity('reduced_lanes', 30),
        );
        $this->assertSame(
            QueueCalculator::CAPACITY_NORMAL_PER_LANE,
            QueueCalculator::perLaneCapacity('reduced_lanes', 50),
        );
    }
}
