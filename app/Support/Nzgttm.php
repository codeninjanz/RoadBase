<?php

namespace App\Support;

class Nzgttm
{
    public const LEVEL_LV = 'LV';
    public const LEVEL_1 = '1';
    public const LEVEL_2 = '2';
    public const LEVEL_3 = '3';

    /**
     * Marker colours, retained for backward compatibility with the legacy
     * level-based palette. Not surfaced as the primary classification any
     * more — the 2023 NZGTTM replaces road-level shortcuts with per-site
     * risk assessment.
     */
    public const COLOURS = [
        self::LEVEL_LV => '#4caf50',
        self::LEVEL_1 => '#2196f3',
        self::LEVEL_2 => '#ff9800',
        self::LEVEL_3 => '#f44336',
    ];

    public const GUIDE_URL = 'https://www.nzta.govt.nz/assets/Road-Efficiency-Group-2/docs/temporary-traffic-management-tools/nzgttm.pdf';
    public const GUIDE_LABEL = 'NZGTTM (Waka Kotahi, April 2023)';

    /**
     * Legacy CoPTTM-style classification by AADT and posted speed. Retained
     * because the count_sites.nzgttm_level column is still populated by the
     * sync job; UI no longer surfaces it. The 2023 guide explicitly says
     * "road levels were a simplified risk assessment ... no longer necessary".
     *
     * @deprecated Use risk-context bands and per-site risk assessment.
     */
    public static function classify(?int $aadt, ?int $speedKmh): ?string
    {
        if ($aadt === null) {
            return null;
        }

        if ($aadt < 500) {
            return self::LEVEL_LV;
        }

        if ($aadt <= 10_000) {
            return self::LEVEL_1;
        }

        if ($speedKmh !== null && $speedKmh > 75) {
            return self::LEVEL_3;
        }

        return self::LEVEL_2;
    }

    /** @deprecated paired with classify(); kept for sync-time compatibility. */
    public static function description(string $level): string
    {
        return match ($level) {
            self::LEVEL_LV => 'Low-volume rural road (AADT < 500)',
            self::LEVEL_1 => 'Level 1 road (AADT 500–10,000)',
            self::LEVEL_2 => 'Level 2 road (AADT > 10,000, ≤ 75 km/h)',
            self::LEVEL_3 => 'Level 3 road (AADT > 10,000, > 75 km/h, motorway/expressway)',
            default => 'Unknown',
        };
    }

    /**
     * Risk-context bands replacing the level shortcut. Each band is an input
     * the planner risk-assesses themselves; none of these are TMP outputs.
     *
     * @return array{
     *     aadt: array{band: ?string, label: string},
     *     speed: array{band: ?string, label: string},
     *     heavy: array{band: ?string, label: string},
     *     crash: array{band: ?string, label: string},
     * }
     */
    public static function contextBands(
        ?int $aadt,
        ?int $speedKmh,
        ?float $heavyPct,
        ?int $crashCount5yr,
    ): array {
        return [
            'aadt' => self::aadtBand($aadt),
            'speed' => self::speedBand($speedKmh),
            'heavy' => self::heavyBand($heavyPct),
            'crash' => self::crashBand($crashCount5yr),
        ];
    }

    /** @return array{band: ?string, label: string} */
    private static function aadtBand(?int $aadt): array
    {
        if ($aadt === null) {
            return ['band' => null, 'label' => 'No AADT'];
        }
        if ($aadt < 500) {
            return ['band' => 'low', 'label' => 'Low volume (< 500 vpd)'];
        }
        if ($aadt < 5_000) {
            return ['band' => 'moderate', 'label' => 'Moderate volume (500–5,000 vpd)'];
        }
        if ($aadt < 15_000) {
            return ['band' => 'high', 'label' => 'High volume (5,000–15,000 vpd)'];
        }
        return ['band' => 'very_high', 'label' => 'Very high volume (> 15,000 vpd)'];
    }

    /** @return array{band: ?string, label: string} */
    private static function speedBand(?int $kmh): array
    {
        if ($kmh === null) {
            return ['band' => null, 'label' => 'Speed unknown'];
        }
        if ($kmh <= 30) {
            return ['band' => 'safe_system', 'label' => 'Safe-system zone (≤ 30 km/h)'];
        }
        if ($kmh <= 50) {
            return ['band' => 'urban', 'label' => 'Urban (40–50 km/h)'];
        }
        if ($kmh <= 70) {
            return ['band' => 'transition', 'label' => 'Transition (60–70 km/h)'];
        }
        if ($kmh <= 90) {
            return ['band' => 'rural', 'label' => 'Rural (80–90 km/h)'];
        }
        return ['band' => 'high_speed', 'label' => 'High-speed (≥ 100 km/h)'];
    }

    /** @return array{band: ?string, label: string} */
    private static function heavyBand(?float $pct): array
    {
        if ($pct === null) {
            return ['band' => null, 'label' => 'Heavy % unknown'];
        }
        if ($pct < 5) {
            return ['band' => 'low', 'label' => 'Low heavy share (< 5%)'];
        }
        if ($pct < 12) {
            return ['band' => 'moderate', 'label' => 'Moderate heavy share (5–12%)'];
        }
        return ['band' => 'high', 'label' => 'High heavy share (> 12%)'];
    }

    /** @return array{band: ?string, label: string} */
    private static function crashBand(?int $count): array
    {
        if ($count === null) {
            return ['band' => null, 'label' => 'No crash data'];
        }
        if ($count === 0) {
            return ['band' => 'none', 'label' => 'No recorded crashes within 1 km (5 yr)'];
        }
        if ($count <= 3) {
            return ['band' => 'low', 'label' => "{$count} crashes within 1 km (5 yr)"];
        }
        if ($count <= 10) {
            return ['band' => 'cluster', 'label' => "{$count} crashes within 1 km (5 yr) — cluster"];
        }
        return ['band' => 'high', 'label' => "{$count} crashes within 1 km (5 yr) — high"];
    }
}
