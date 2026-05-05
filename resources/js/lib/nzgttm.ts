import type { NzgttmLevel } from '@/types';

export const LEVEL_COLOURS: Record<NzgttmLevel, string> = {
    LV: '#4caf50',
    '1': '#2196f3',
    '2': '#ff9800',
    '3': '#f44336',
};

export const LEVEL_LABELS: Record<NzgttmLevel, string> = {
    LV: 'Level LV — AADT < 500',
    '1': 'Level 1 — AADT 500–10,000',
    '2': 'Level 2 — AADT > 10,000',
    '3': 'Level 3 — AADT > 10,000, > 75 km/h',
};

export function colourFor(level: NzgttmLevel | null): string {
    if (!level) return '#9e9e9e';
    return LEVEL_COLOURS[level];
}
