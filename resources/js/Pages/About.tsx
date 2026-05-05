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
                    RoadBase aggregates publicly available NZ road traffic-volume data into a single
                    interactive map for traffic-management planners producing TMPs under the NZGTTM
                    framework. Data is auto-synced from each source on its natural cadence.
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

                <h2 className="mt-8 text-xl font-semibold">NZGTTM road-level classification</h2>
                <p className="mt-3 text-gray-700">
                    RoadBase auto-computes the NZGTTM road level for each count site by spatially
                    joining it to the National Speed Limit Register and applying the standard rules:
                </p>
                <ul className="mt-3 list-disc pl-6 text-gray-700">
                    <li>LV: AADT &lt; 500 vpd</li>
                    <li>1: AADT 500–10,000 vpd</li>
                    <li>2: AADT &gt; 10,000 vpd</li>
                    <li>3: AADT &gt; 10,000 vpd and speed limit &gt; 75 km/h</li>
                </ul>
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
