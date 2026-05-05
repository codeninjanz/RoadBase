import { useEffect, useState } from 'react';
import { fetchSite } from '@/lib/api';
import type { SiteDetail } from '@/types';
import { NzgttmBadge } from './NzgttmBadge';
import { ExportMenu } from './ExportMenu';

interface Props {
    siteId: number;
    onClose: () => void;
}

export function SiteDetailPanel({ siteId, onClose }: Props) {
    const [site, setSite] = useState<SiteDetail | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        const ac = new AbortController();
        setSite(null);
        setError(null);
        fetchSite(siteId, ac.signal)
            .then(setSite)
            .catch((e) => {
                if (ac.signal.aborted) return;
                setError(String(e));
            });
        return () => ac.abort();
    }, [siteId]);

    return (
        <aside className="absolute right-0 top-0 z-10 flex h-full w-full max-w-md flex-col bg-white shadow-xl md:w-96">
            <header className="flex items-center justify-between border-b px-4 py-3">
                <div>
                    <div className="text-xs uppercase tracking-wide text-gray-500">Count site</div>
                    <h2 className="text-lg font-semibold">{site?.road_name ?? `Site ${siteId}`}</h2>
                </div>
                <button onClick={onClose} className="rounded p-1 text-gray-500 hover:bg-gray-100">
                    Close
                </button>
            </header>

            <div className="flex-1 overflow-y-auto px-4 py-3 text-sm">
                {error && <p className="text-red-600">Failed to load: {error}</p>}
                {!site && !error && <p className="text-gray-500">Loading…</p>}

                {site && (
                    <>
                        <Row label="NZGTTM road level">
                            <NzgttmBadge level={site.nzgttm_level} showLabel />
                        </Row>
                        <Row label="AADT">
                            {site.aadt !== null ? `${site.aadt.toLocaleString()} vpd` : '—'}
                        </Row>
                        <Row label="Speed limit">
                            {site.speed_limit_kmh !== null ? `${site.speed_limit_kmh} km/h` : '—'}
                        </Row>
                        <Row label="Heavy vehicles">
                            {site.heavy_vehicle_pct !== null ? `${site.heavy_vehicle_pct}%` : '—'}
                        </Row>
                        <Row label="Peak hour volume">
                            {site.peak_hour_volume !== null
                                ? `${site.peak_hour_volume.toLocaleString()} vph`
                                : '—'}
                        </Row>
                        <Row label="Peak hour start">{site.peak_hour_start ?? '—'}</Row>
                        <Row label="Count date">{site.count_date ?? '—'}</Row>
                        <Row label="Region">{site.region ?? '—'}</Row>
                        <Row label="Coordinates">
                            {site.lat.toFixed(6)}, {site.lng.toFixed(6)}
                        </Row>
                        <Row label="Source">
                            {site.source.url ? (
                                <a
                                    className="text-blue-600 underline"
                                    href={site.source.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    {site.source.name}
                                </a>
                            ) : (
                                site.source.name
                            )}
                        </Row>
                        <Row label="Last synced">{site.synced_at ?? '—'}</Row>

                        <div className="mt-4 border-t pt-4">
                            <ExportMenu site={site} />
                        </div>
                    </>
                )}
            </div>
        </aside>
    );
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="flex justify-between gap-3 border-b border-gray-100 py-2">
            <span className="text-gray-500">{label}</span>
            <span className="text-right font-medium">{children}</span>
        </div>
    );
}
