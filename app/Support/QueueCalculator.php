<?php

namespace App\Support;

/**
 * Traffic-impact / queue calculations from NZGTTM 2023, pp.72–73.
 *
 * Source: Waka Kotahi NZ Transport Agency, "New Zealand guide to temporary
 * traffic management", April 2023, CC-BY-4.0.
 *
 * Worked examples in the guide (verification targets):
 *   • Stop/go: 1000 vph, stopped 40 s → ~88.9 m queue.
 *   • Merge: 1500 vph demand, 1300 vph capacity, 2 hr → 400 vehicles, ~3.2 km.
 */
class QueueCalculator
{
    public const SCENARIO_STOP_GO = 'stop_go';
    public const SCENARIO_MERGE = 'merge';
    public const SCENARIO_REDUCED_LANES = 'reduced_lanes';

    public const VEHICLE_LENGTH_M = 8.0;

    /** Per-lane capacity at typical operating speeds (vph). */
    public const CAPACITY_NORMAL_PER_LANE = 1800;
    public const CAPACITY_30KMH_PER_LANE = 1500;
    public const CAPACITY_MERGE = 1300;

    /**
     * @param array{
     *     scenario: string,
     *     temp_speed_kmh: int,
     *     lanes_open: int,
     *     demand_vph: int,
     *     duration_hours: float,
     *     stop_seconds?: int,
     *     site_length_m?: int,
     * } $input
     *
     * @return array{
     *     capacity_per_lane_vph: int,
     *     total_capacity_vph: int,
     *     queue_vehicles: int,
     *     queue_length_m: float,
     *     queue_length_km: float,
     *     max_delay_s: float,
     *     site_traverse_s: ?float,
     *     advance_warning_required_m: int,
     *     notes: array<int, string>,
     * }
     */
    public static function compute(array $input): array
    {
        $scenario = $input['scenario'];
        $tempSpeed = (int) $input['temp_speed_kmh'];
        $lanes = max(1, (int) $input['lanes_open']);
        $demand = max(0, (int) $input['demand_vph']);
        $duration = max(0.0, (float) $input['duration_hours']);
        $stopSeconds = max(0, (int) ($input['stop_seconds'] ?? 0));
        $siteLength = max(0, (int) ($input['site_length_m'] ?? 0));

        $perLaneCap = self::perLaneCapacity($scenario, $tempSpeed);
        $totalCap = $perLaneCap * $lanes;

        $notes = [];
        $queueVehicles = 0;
        $queueLengthM = 0.0;
        $maxDelay = 0.0;

        if ($scenario === self::SCENARIO_STOP_GO) {
            // Queue grows during the stop period at the demand rate.
            $queueVehicles = (int) round($demand * ($stopSeconds / 3600.0));
            $queueLengthM = $queueVehicles * self::VEHICLE_LENGTH_M;
            // First-arriving vehicle waits the full stop period; average ≈ half.
            $maxDelay = (float) $stopSeconds;
            $notes[] = 'Stop/go queue grows at demand rate during the stop period (NZGTTM p.73).';
        } else {
            // Demand-vs-capacity accumulation. Queue only grows when demand > capacity.
            $excess = max(0, $demand - $totalCap);
            $queueVehicles = (int) round($excess * $duration);
            $queueLengthM = $queueVehicles * self::VEHICLE_LENGTH_M;
            // Approx max delay (vertical queueing): queue / departure rate × 3600 s.
            $maxDelay = $totalCap > 0 ? ($queueVehicles / $totalCap) * 3600.0 : 0.0;
            if ($excess === 0) {
                $notes[] = 'Demand is at or below capacity — no growing queue expected.';
            } else {
                $notes[] = "Demand exceeds capacity by {$excess} vph; queue grows over time.";
            }
        }

        $traverse = $siteLength > 0 && $tempSpeed > 0
            ? round($siteLength / ($tempSpeed * 1000.0 / 3600.0), 1)
            : null;

        // Advance warning must be at least the queue length so the warning
        // sign isn't standing inside the stationary queue (NZGTTM p.64).
        $advanceWarning = (int) ceil($queueLengthM);
        if ($queueLengthM > 0) {
            $notes[] = "Place advance-warning sign at least {$advanceWarning} m upstream — the warning sign must not stand inside the stationary queue (NZGTTM p.64).";
        }

        return [
            'capacity_per_lane_vph' => $perLaneCap,
            'total_capacity_vph' => $totalCap,
            'queue_vehicles' => $queueVehicles,
            'queue_length_m' => round($queueLengthM, 1),
            'queue_length_km' => round($queueLengthM / 1000, 3),
            'max_delay_s' => round($maxDelay, 1),
            'site_traverse_s' => $traverse,
            'advance_warning_required_m' => $advanceWarning,
            'notes' => $notes,
        ];
    }

    public static function perLaneCapacity(string $scenario, int $tempSpeedKmh): int
    {
        if ($scenario === self::SCENARIO_MERGE) {
            return self::CAPACITY_MERGE;
        }
        if ($tempSpeedKmh <= 30) {
            return self::CAPACITY_30KMH_PER_LANE;
        }
        return self::CAPACITY_NORMAL_PER_LANE;
    }
}
