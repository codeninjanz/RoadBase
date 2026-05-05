import { LEVEL_COLOURS, LEVEL_LABELS } from '@/lib/nzgttm';

interface Props {
    showSpeedLimits: boolean;
    onToggleSpeedLimits: (next: boolean) => void;
}

export function LayerToggles({ showSpeedLimits, onToggleSpeedLimits }: Props) {
    return (
        <div className="absolute bottom-4 left-4 z-10 w-64 rounded-lg bg-white p-3 text-sm shadow-lg">
            <h3 className="mb-2 font-semibold">Layers</h3>
            <label className="flex items-center gap-2">
                <input
                    type="checkbox"
                    checked={showSpeedLimits}
                    onChange={(e) => onToggleSpeedLimits(e.target.checked)}
                />
                Speed limits (NSLR, zoom &ge; 11)
            </label>

            <div className="mt-3 border-t pt-2">
                <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    NZGTTM levels
                </h4>
                <ul className="space-y-1 text-xs">
                    {(Object.keys(LEVEL_COLOURS) as Array<keyof typeof LEVEL_COLOURS>).map((lv) => (
                        <li key={lv} className="flex items-center gap-2">
                            <span
                                className="inline-block h-3 w-3 rounded-full"
                                style={{ background: LEVEL_COLOURS[lv] }}
                            />
                            <span className="text-gray-700">{LEVEL_LABELS[lv]}</span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
