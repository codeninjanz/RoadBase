import { SPEED_BAND_COLOURS, UNKNOWN_SPEED_COLOUR } from '@/lib/nzgttm';

interface Props {
    showSpeedLimits: boolean;
    onToggleSpeedLimits: (next: boolean) => void;
    showRcas: boolean;
    onToggleRcas: (next: boolean) => void;
}

export function LayerToggles({
    showSpeedLimits,
    onToggleSpeedLimits,
    showRcas,
    onToggleRcas,
}: Props) {
    return (
        <div className="absolute bottom-4 left-4 z-10 w-64 rounded-lg bg-white p-3 text-sm shadow-lg">
            <h3 className="mb-2 font-semibold">Layers</h3>
            <label className="flex min-h-[36px] items-center gap-2">
                <input
                    type="checkbox"
                    checked={showSpeedLimits}
                    onChange={(e) => onToggleSpeedLimits(e.target.checked)}
                />
                Speed limits (NSLR, zoom &ge; 11)
            </label>
            <label className="flex min-h-[36px] items-center gap-2">
                <input
                    type="checkbox"
                    checked={showRcas}
                    onChange={(e) => onToggleRcas(e.target.checked)}
                />
                RCA boundaries (zoom &ge; 7)
            </label>

            <div className="mt-3 border-t pt-2">
                <h4 className="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Site marker — speed band
                </h4>
                <ul className="space-y-1 text-xs">
                    {SPEED_BAND_COLOURS.map((b) => (
                        <li key={b.label} className="flex items-center gap-2">
                            <span
                                className="inline-block h-3 w-3 rounded-full"
                                style={{ background: b.colour }}
                            />
                            <span className="text-gray-700">{b.label}</span>
                        </li>
                    ))}
                    <li className="flex items-center gap-2">
                        <span
                            className="inline-block h-3 w-3 rounded-full"
                            style={{ background: UNKNOWN_SPEED_COLOUR }}
                        />
                        <span className="text-gray-700">Speed unknown</span>
                    </li>
                </ul>
            </div>
        </div>
    );
}
