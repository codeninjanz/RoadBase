<?php

namespace App\Support;

class Nzgttm
{
    public const LEVEL_LV = 'LV';
    public const LEVEL_1 = '1';
    public const LEVEL_2 = '2';
    public const LEVEL_3 = '3';

    public const COLOURS = [
        self::LEVEL_LV => '#4caf50',
        self::LEVEL_1 => '#2196f3',
        self::LEVEL_2 => '#ff9800',
        self::LEVEL_3 => '#f44336',
    ];

    /**
     * Classify a road segment per NZGTTM:
     *   LV  : AADT < 500
     *   1   : 500 <= AADT <= 10,000
     *   2   : AADT > 10,000 and (speed unknown OR speed <= 75 km/h)
     *   3   : AADT > 10,000 and speed > 75 km/h
     *
     * Returns null when AADT is missing.
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
}
