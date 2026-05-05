import type { NzgttmLevel } from '@/types';

/**
 * Legacy CoPTTM-style level palette. Retained because the count_sites table
 * still carries an nzgttm_level column populated by the sync job, but no
 * longer surfaced in the UI. The 2023 NZGTTM retired road levels in favour
 * of per-site risk assessment.
 *
 * @deprecated Use speedColour() for marker / zone tint.
 */
export const LEVEL_COLOURS: Record<NzgttmLevel, string> = {
    LV: '#4caf50',
    '1': '#2196f3',
    '2': '#ff9800',
    '3': '#f44336',
};

export const SPEED_BAND_COLOURS: Array<{ max: number; colour: string; label: string }> = [
    { max: 30, colour: '#7e57c2', label: '≤ 30 km/h' },
    { max: 50, colour: '#5c6bc0', label: '40–50 km/h' },
    { max: 70, colour: '#26a69a', label: '60–70 km/h' },
    { max: 80, colour: '#66bb6a', label: '80 km/h' },
    { max: 100, colour: '#ffa726', label: '90–100 km/h' },
    { max: 999, colour: '#ef5350', label: '≥ 110 km/h' },
];

export const UNKNOWN_SPEED_COLOUR = '#9e9e9e';

export function speedColour(kmh: number | null): string {
    if (kmh === null) return UNKNOWN_SPEED_COLOUR;
    for (const band of SPEED_BAND_COLOURS) {
        if (kmh <= band.max) return band.colour;
    }
    return UNKNOWN_SPEED_COLOUR;
}
