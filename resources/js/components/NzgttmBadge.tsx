import { colourFor, LEVEL_LABELS } from '@/lib/nzgttm';
import type { NzgttmLevel } from '@/types';

interface Props {
    level: NzgttmLevel | null;
    showLabel?: boolean;
}

export function NzgttmBadge({ level, showLabel = false }: Props) {
    if (!level) {
        return <span className="rounded bg-gray-200 px-2 py-1 text-xs text-gray-600">No level</span>;
    }

    return (
        <span className="inline-flex items-center gap-2">
            <span
                className="inline-block rounded px-2 py-0.5 text-xs font-bold text-white"
                style={{ backgroundColor: colourFor(level) }}
            >
                {level}
            </span>
            {showLabel && <span className="text-xs text-gray-600">{LEVEL_LABELS[level]}</span>}
        </span>
    );
}
