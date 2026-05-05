<?php

namespace App\Support;

/**
 * NZGTTM Table 1 — Common geometric dimensions (April 2023 guide, p.69).
 *
 * Source: Waka Kotahi NZ Transport Agency, "New Zealand guide to temporary
 * traffic management", April 2023, CC-BY-4.0.
 *
 * Speed columns are operating speed in km/h. Values for distance are metres
 * unless otherwise marked; time values are seconds.
 *
 * Footnotes from the guide are propagated via row "footnote" markers so the
 * UI can show the asterisks the table itself uses (e.g. shadow-to-work
 * distances are subject to amendment from further research — guide p.68).
 */
class NzgttmTable1
{
    public const SPEEDS = [30, 40, 50, 60, 70, 80, 90, 100, 110];

    /**
     * Each row: ['key' => slug, 'label' => display, 'unit' => 'm'|'sec'|'',
     *            'values' => [9 values aligned to SPEEDS], 'footnote' => null|string,
     *            'group' => grouping for UI].
     *
     * @return array<int, array{key:string,label:string,unit:string,values:array<int,float|int|string>,footnote:?string,group:string}>
     */
    public static function rowsForSurface(string $surface): array
    {
        $rows = self::rowsCommon();

        if ($surface === 'unsealed') {
            $rows[] = [
                'key' => 'longitudinal_exclusion_m',
                'label' => 'Longitudinal exclusion (unsealed)',
                'unit' => 'm',
                'values' => [30, 40, 60, 80, 105, 135, 165, 215, 255],
                'footnote' => null,
                'group' => 'exclusion',
            ];
            $rows[] = [
                'key' => 'longitudinal_exclusion_s',
                'label' => 'Longitudinal exclusion (unsealed)',
                'unit' => 'sec',
                'values' => [3, 4, 4, 5, 6, 6, 7, 8, 8],
                'footnote' => null,
                'group' => 'exclusion',
            ];
        } else {
            $rows[] = [
                'key' => 'longitudinal_exclusion_m',
                'label' => 'Longitudinal exclusion (sealed)',
                'unit' => 'm',
                'values' => [25, 35, 50, 65, 85, 105, 125, 165, 195],
                'footnote' => null,
                'group' => 'exclusion',
            ];
            $rows[] = [
                'key' => 'longitudinal_exclusion_s',
                'label' => 'Longitudinal exclusion (sealed)',
                'unit' => 'sec',
                'values' => [3, 3, 4, 4, 4, 5, 5, 6, 6],
                'footnote' => null,
                'group' => 'exclusion',
            ];
        }

        usort($rows, fn ($a, $b) => self::groupOrder($a['group']) <=> self::groupOrder($b['group']));

        return $rows;
    }

    /**
     * Look up every row's value for a given speed (km/h). Throws when speed
     * isn't in the canonical column list.
     *
     * @return array<int, array{key:string,label:string,unit:string,value:float|int|string,footnote:?string,group:string}>
     */
    public static function lookup(int $speedKmh, string $surface): array
    {
        $idx = array_search($speedKmh, self::SPEEDS, true);
        if ($idx === false) {
            throw new \InvalidArgumentException("Speed {$speedKmh} km/h not in NZGTTM Table 1");
        }

        return array_map(fn ($r) => [
            'key' => $r['key'],
            'label' => $r['label'],
            'unit' => $r['unit'],
            'value' => $r['values'][$idx],
            'footnote' => $r['footnote'],
            'group' => $r['group'],
        ], self::rowsForSurface($surface));
    }

    /** @return array<int, array{key:string,label:string,unit:string,values:array<int,float|int|string>,footnote:?string,group:string}> */
    private static function rowsCommon(): array
    {
        return [
            // Traffic signs ------------------------------------------------
            ['key' => 'sign_visibility_m', 'label' => 'Sign visibility distance', 'unit' => 'm',
                'values' => [20, 25, 30, 50, 60, 70, 95, 105, 115], 'footnote' => null, 'group' => 'signs'],
            ['key' => 'sign_visibility_s', 'label' => 'Sign visibility distance', 'unit' => 'sec',
                'values' => [2, 2, 2, 3, 3, 3, 4, 4, 4], 'footnote' => null, 'group' => 'signs'],
            ['key' => 'warning_distance_m', 'label' => 'Warning distance', 'unit' => 'm',
                'values' => [30, 40, 50, 80, 100, 120, 160, 180, 200], 'footnote' => null, 'group' => 'signs'],
            ['key' => 'warning_distance_s', 'label' => 'Warning distance', 'unit' => 'sec',
                'values' => [4, 4, 4, 5, 5, 5, 6, 6, 6], 'footnote' => null, 'group' => 'signs'],
            ['key' => 'sign_spacing_m', 'label' => 'Sign spacing', 'unit' => 'm',
                'values' => [15, 20, 25, 40, 50, 60, 80, 90, 100], 'footnote' => null, 'group' => 'signs'],

            // Exclusion zones (longitudinal added per surface) -------------
            ['key' => 'lateral_exclusion_m', 'label' => 'Lateral exclusion', 'unit' => 'm',
                'values' => [1, 1, 1, 1.5, 1.5, 1.5, 2, 2, 2], 'footnote' => null, 'group' => 'exclusion'],

            // Tapers -------------------------------------------------------
            ['key' => 'taper_length_m', 'label' => 'Taper length', 'unit' => 'm',
                'values' => [30, 40, 50, 60, 70, 80, 90, 100, 110], 'footnote' => null, 'group' => 'tapers'],
            ['key' => 'taper_length_s', 'label' => 'Taper length', 'unit' => 'sec',
                'values' => [3.5, 3.5, 3.5, 3.5, 3.5, 3.5, 3.5, 3.5, 3.5], 'footnote' => null, 'group' => 'tapers'],
            ['key' => 'distance_between_tapers_m', 'label' => 'Distance between tapers', 'unit' => 'm',
                'values' => [25, 35, 50, 65, 85, 105, 125, 165, 195], 'footnote' => null, 'group' => 'tapers'],
            ['key' => 'distance_between_tapers_s', 'label' => 'Distance between tapers', 'unit' => 'sec',
                'values' => [3, 3, 4, 4, 4, 5, 5, 6, 6], 'footnote' => null, 'group' => 'tapers'],

            // Lanes --------------------------------------------------------
            ['key' => 'lane_width_m', 'label' => 'Temporary lane width', 'unit' => 'm',
                'values' => [2.75, 2.75, 3, 3, 3.25, 3.25, 3.5, 3.5, 3.5], 'footnote' => null, 'group' => 'lanes'],
            ['key' => 'delineation_straight_m', 'label' => 'Delineation spacing — straights', 'unit' => 'm',
                'values' => [5, 5, 5, 10, 10, 10, 15, 15, 15], 'footnote' => null, 'group' => 'lanes'],
            ['key' => 'delineation_curve_m', 'label' => 'Delineation spacing — curves & tapers', 'unit' => 'm',
                'values' => [2.5, 2.5, 2.5, 5, 5, 5, 10, 10, 10], 'footnote' => null, 'group' => 'lanes'],
            ['key' => 'threshold_length_m', 'label' => 'Threshold length', 'unit' => 'm',
                'values' => [10, 10, 10, 20, 20, 20, 40, 40, 40],
                'footnote' => 'Added since NZGTTM Table 1 v1 (guide p.69 footnote *).', 'group' => 'lanes'],
            ['key' => 'delineation_threshold_m', 'label' => 'Delineation spacing in threshold', 'unit' => 'm',
                'values' => [2.5, 2.5, 2.5, 5, 5, 5, 10, 10, 10],
                'footnote' => 'Added since NZGTTM Table 1 v1 (guide p.69 footnote *).', 'group' => 'lanes'],

            // Curve --------------------------------------------------------
            ['key' => 'min_curve_radius_m', 'label' => 'Min curve radius for generic design', 'unit' => 'm',
                'values' => [35, 60, 100, 140, 190, 250, 315, 390, 470], 'footnote' => null, 'group' => 'curve'],

            // Vehicle operations ------------------------------------------
            ['key' => 'clear_sight_m', 'label' => 'Clear sight distance', 'unit' => 'm',
                'values' => [100, 135, 165, 200, 235, 265, 300, 335, 365], 'footnote' => null, 'group' => 'vehicles'],
            ['key' => 'clear_sight_s', 'label' => 'Clear sight distance', 'unit' => 'sec',
                'values' => [12, 12, 12, 12, 12, 12, 12, 12, 12], 'footnote' => null, 'group' => 'vehicles'],
            ['key' => 'sep_tail_pilot_to_work_min_m', 'label' => 'Tail pilot → work vehicle (min)', 'unit' => 'm',
                'values' => [25, 35, 45, 65, 75, 85, 120, 130, 150], 'footnote' => null, 'group' => 'vehicles'],
            ['key' => 'sep_tail_pilot_to_work_min_s', 'label' => 'Tail pilot → work vehicle (min)', 'unit' => 'sec',
                'values' => [3, 3, 3, 4, 4, 4, 5, 5, 5], 'footnote' => null, 'group' => 'vehicles'],
            ['key' => 'sep_tail_pilot_to_work_max_m', 'label' => 'Tail pilot → work vehicle (max)', 'unit' => 'm',
                'values' => [50, 70, 90, 130, 150, 190, 240, 260, 300], 'footnote' => null, 'group' => 'vehicles'],
            ['key' => 'sep_tail_pilot_to_work_max_s', 'label' => 'Tail pilot → work vehicle (max)', 'unit' => 'sec',
                'values' => [6, 6, 6, 8, 8, 8, 10, 10, 10], 'footnote' => null, 'group' => 'vehicles'],
            ['key' => 'sep_shadow_to_work_m', 'label' => 'Shadow vehicle → work vehicle', 'unit' => 'm',
                'values' => [15, 20, 25, 30, 35, 40, 45, 50, 55],
                'footnote' => 'Subject to amendment from further research (guide p.68 footnote **).', 'group' => 'vehicles'],
            ['key' => 'sep_shadow_to_work_s', 'label' => 'Shadow vehicle → work vehicle', 'unit' => 'sec',
                'values' => [2, 2, 2, 2, 2, 2, 2, 2, 2],
                'footnote' => 'Subject to amendment from further research (guide p.68 footnote **).', 'group' => 'vehicles'],
            ['key' => 'sep_work_to_lead_pilot_min_m', 'label' => 'Work vehicle → lead pilot (min)', 'unit' => 'm',
                'values' => [25, 35, 45, 65, 75, 85, 120, 130, 150], 'footnote' => null, 'group' => 'vehicles'],
            ['key' => 'sep_work_to_lead_pilot_max_m', 'label' => 'Work vehicle → lead pilot (max)', 'unit' => 'm',
                'values' => [50, 70, 90, 130, 150, 190, 240, 260, 300], 'footnote' => null, 'group' => 'vehicles'],
        ];
    }

    private static function groupOrder(string $group): int
    {
        return match ($group) {
            'signs' => 1,
            'exclusion' => 2,
            'tapers' => 3,
            'lanes' => 4,
            'curve' => 5,
            'vehicles' => 6,
            default => 99,
        };
    }
}
