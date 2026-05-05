import { Head, Link, router } from '@inertiajs/react';

type Surface = 'sealed' | 'unsealed';

interface Row {
    key: string;
    label: string;
    unit: 'm' | 'sec' | '';
    value: number | string;
    footnote: string | null;
    group: 'signs' | 'exclusion' | 'tapers' | 'lanes' | 'curve' | 'vehicles';
}

interface Props {
    speeds: number[];
    surfaces: Surface[];
    speed: number;
    surface: Surface;
    rows: Row[];
    guide: { url: string; label: string };
}

const GROUP_LABELS: Record<Row['group'], string> = {
    signs: 'Traffic signs',
    exclusion: 'Exclusion zones',
    tapers: 'Tapers',
    lanes: 'Lanes & delineation',
    curve: 'Curve',
    vehicles: 'Vehicle operations',
};

const GROUP_ORDER: Row['group'][] = ['signs', 'exclusion', 'tapers', 'lanes', 'curve', 'vehicles'];

export default function LayoutCalculator({ speeds, surfaces, speed, surface, rows, guide }: Props) {
    const setSpeed = (next: number) => {
        router.get(
            '/tools/layout',
            { speed: next, surface },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const setSurface = (next: Surface) => {
        router.get(
            '/tools/layout',
            { speed, surface: next },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const grouped = GROUP_ORDER.map((g) => ({
        group: g,
        rows: rows.filter((r) => r.group === g),
    })).filter((g) => g.rows.length > 0);

    return (
        <>
            <Head title="NZGTTM layout calculator" />
            <div className="mx-auto max-w-5xl px-4 py-8 md:px-6 md:py-10">
                <Link href="/" className="text-sm text-blue-600 hover:underline">
                    ← Back to map
                </Link>
                <h1 className="mt-4 text-2xl font-bold md:text-3xl">NZGTTM layout calculator</h1>
                <p className="mt-2 text-sm text-gray-700">
                    Looks up the common geometric dimensions from{' '}
                    <a href={guide.url} target="_blank" rel="noreferrer" className="text-blue-600 hover:underline">
                        {guide.label}
                    </a>
                    , Table 1 (p.69). These are inputs to your site-specific risk assessment, not an
                    approved design.
                </p>

                <section className="mt-6 rounded-lg border bg-white p-4 shadow-sm">
                    <h2 className="text-sm font-semibold text-gray-700">Operating speed</h2>
                    <div className="mt-2 flex flex-wrap gap-1.5">
                        {speeds.map((s) => (
                            <button
                                key={s}
                                type="button"
                                onClick={() => setSpeed(s)}
                                className={`min-h-[44px] min-w-[56px] rounded-md border px-3 py-2 text-sm font-medium ${
                                    s === speed
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                }`}
                            >
                                {s}
                                <span className="ml-1 text-xs font-normal opacity-70">km/h</span>
                            </button>
                        ))}
                    </div>

                    <h2 className="mt-5 text-sm font-semibold text-gray-700">Surface</h2>
                    <div className="mt-2 flex gap-2">
                        {surfaces.map((s) => (
                            <button
                                key={s}
                                type="button"
                                onClick={() => setSurface(s)}
                                className={`min-h-[44px] rounded-md border px-4 py-2 text-sm font-medium capitalize ${
                                    s === surface
                                        ? 'border-blue-600 bg-blue-600 text-white'
                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                }`}
                            >
                                {s}
                            </button>
                        ))}
                    </div>
                </section>

                <Schematic />

                <section className="mt-6 space-y-5">
                    {grouped.map(({ group, rows }) => (
                        <div key={group} className="rounded-lg border bg-white shadow-sm">
                            <h3 className="border-b bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                {GROUP_LABELS[group]}
                            </h3>
                            <ul className="divide-y">
                                {rows.map((r) => (
                                    <li key={r.key} className="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3">
                                        <div className="min-w-0 flex-1">
                                            <div className="text-sm text-gray-900">{r.label}</div>
                                            {r.footnote && (
                                                <div className="mt-0.5 text-xs text-gray-500">{r.footnote}</div>
                                            )}
                                        </div>
                                        <div className="font-mono text-lg font-semibold tabular-nums text-gray-900">
                                            {r.value}
                                            {r.unit && <span className="ml-1 text-sm font-normal text-gray-500">{r.unit}</span>}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </section>

                <p className="mt-6 rounded bg-amber-50 px-3 py-2 text-xs text-amber-900">
                    Pre-approved schemes / generic plans must never be assumed applicable to a site
                    without a fresh risk assessment (NZGTTM 2023, p.36).
                </p>

                <p className="mt-2 text-xs text-gray-500">
                    Source: {guide.label} — Creative Commons Attribution 4.0.
                </p>
            </div>
        </>
    );
}

/**
 * Tiny schematic showing where each Table 1 dimension sits in a typical
 * lane-closure layout: advance warning → taper → lateral exclusion → work
 * area → return to normal.
 */
function Schematic() {
    return (
        <figure className="mt-6 overflow-x-auto rounded-lg border bg-white p-4 shadow-sm">
            <svg
                viewBox="0 0 720 200"
                className="block min-w-[640px] text-gray-700"
                aria-label="Lane-closure schematic showing Table 1 dimensions"
            >
                {/* road outline */}
                <rect x="0" y="60" width="720" height="80" fill="#f3f4f6" stroke="#9ca3af" />
                <line x1="0" y1="100" x2="720" y2="100" stroke="#fff" strokeDasharray="8 8" strokeWidth="2" />

                {/* advance warning sign */}
                <g transform="translate(60,30)">
                    <polygon points="0,30 14,5 28,30" fill="#facc15" stroke="#854d0e" strokeWidth="1.5" />
                    <text x="14" y="50" textAnchor="middle" fontSize="10">Advance warning</text>
                </g>

                {/* warning distance arrow */}
                <line x1="90" y1="80" x2="280" y2="80" stroke="#374151" strokeWidth="1" markerEnd="url(#arr)" />
                <text x="185" y="74" textAnchor="middle" fontSize="10">Warning distance</text>

                {/* taper cones */}
                {[0, 1, 2, 3, 4].map((i) => (
                    <circle key={i} cx={290 + i * 18} cy={140 - i * 8} r="4" fill="#fb923c" />
                ))}
                <text x="320" y="170" textAnchor="middle" fontSize="10">Taper length</text>

                {/* exclusion zone */}
                <rect x="380" y="68" width="160" height="60" fill="#fef3c7" stroke="#d97706" strokeDasharray="4 3" />
                <text x="460" y="102" textAnchor="middle" fontSize="10">Lateral exclusion</text>
                <text x="460" y="116" textAnchor="middle" fontSize="9" fill="#6b7280">(work area inside)</text>

                {/* end of works */}
                <g transform="translate(620,30)">
                    <polygon points="0,30 14,5 28,30" fill="#10b981" stroke="#064e3b" strokeWidth="1.5" />
                    <text x="14" y="50" textAnchor="middle" fontSize="10">End of works</text>
                </g>

                <defs>
                    <marker id="arr" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                        <path d="M0,0 L8,4 L0,8 z" fill="#374151" />
                    </marker>
                </defs>
            </svg>
            <figcaption className="mt-2 text-xs text-gray-500">
                Schematic for orientation only. Use the values in the table for actual layout.
            </figcaption>
        </figure>
    );
}
