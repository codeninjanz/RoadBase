import { Head, Link } from '@inertiajs/react';

interface Source {
    key: string;
    name: string;
    url: string | null;
    update_frequency_hours: number;
    last_synced_at: string | null;
    last_sync_status: string | null;
}

interface Props {
    sources: Source[];
}

export default function AboutPage({ sources }: Props) {
    return (
        <>
            <Head title="About" />
            <div className="mx-auto max-w-4xl px-6 py-10">
                <Link href="/" className="text-sm text-blue-600 hover:underline">
                    ← Back to map
                </Link>
                <h1 className="mt-4 text-2xl font-bold">About RoadBase</h1>
                <p className="mt-3 text-gray-700">
                    RoadBase aggregates publicly available NZ road traffic-volume, speed-limit and
                    crash data into a single interactive map. It is a public reference tool for TTM
                    practitioners assembling the activity and environment context that the 2023
                    NZGTTM risk-based workflow needs. Data is auto-synced from each source on its
                    natural cadence.
                </p>

                <h2 className="mt-8 text-xl font-semibold">Data sources</h2>
                <table className="mt-3 w-full table-auto border-collapse text-sm">
                    <thead>
                        <tr className="border-b text-left text-xs uppercase tracking-wide text-gray-500">
                            <th className="py-2 pr-4">Source</th>
                            <th className="py-2 pr-4">Refresh</th>
                            <th className="py-2 pr-4">Last sync</th>
                            <th className="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {sources.map((s) => (
                            <tr key={s.key} className="border-b">
                                <td className="py-2 pr-4">
                                    {s.url ? (
                                        <a href={s.url} className="text-blue-600 hover:underline" target="_blank" rel="noreferrer">
                                            {s.name}
                                        </a>
                                    ) : (
                                        s.name
                                    )}
                                </td>
                                <td className="py-2 pr-4 text-gray-600">
                                    Every {Math.round(s.update_frequency_hours / 24)} d
                                </td>
                                <td className="py-2 pr-4 text-gray-600">{s.last_synced_at ?? 'Never'}</td>
                                <td className="py-2">
                                    <StatusPill status={s.last_sync_status} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>

                <h2 className="mt-8 text-xl font-semibold">NZGTTM 2023 — road levels retired</h2>
                <p className="mt-3 text-gray-700">
                    Earlier versions of RoadBase auto-classified each count site into NZGTTM road
                    levels (LV / 1 / 2 / 3) by AADT and posted speed. The April 2023{' '}
                    <a
                        href="https://www.nzta.govt.nz/assets/Road-Efficiency-Group-2/docs/temporary-traffic-management-tools/nzgttm.pdf"
                        target="_blank"
                        rel="noreferrer"
                        className="text-blue-600 hover:underline"
                    >
                        New Zealand guide to temporary traffic management
                    </a>{' '}
                    explicitly retires this shortcut:
                </p>
                <blockquote className="mt-3 border-l-4 border-gray-300 pl-4 italic text-gray-700">
                    “Road levels were a simplified risk assessment. By undertaking a risk assessment
                    for each site, road levels are no longer necessary.”
                    <span className="block text-xs not-italic text-gray-500">
                        NZGTTM 2023, p.36 (Clarifications)
                    </span>
                </blockquote>
                <p className="mt-3 text-gray-700">
                    The site detail panel now surfaces road-context bands (volume, speed, heavy
                    share, crash density) as inputs to your risk assessment, not as a level
                    classification. The legacy <code>nzgttm_level</code> column remains in the
                    database for backward compatibility but is not displayed.
                </p>
            </div>
        </>
    );
}

function StatusPill({ status }: { status: string | null }) {
    const colours: Record<string, string> = {
        success: 'bg-green-100 text-green-800',
        failed: 'bg-red-100 text-red-800',
    };
    return (
        <span className={`rounded px-2 py-0.5 text-xs ${colours[status ?? ''] ?? 'bg-gray-100 text-gray-700'}`}>
            {status ?? 'pending'}
        </span>
    );
}
