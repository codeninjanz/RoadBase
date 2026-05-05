import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { fetchSite } from '@/lib/api';
import type { ContextBand, RoadContext, SiteDetail } from '@/types';
import { ExportMenu } from './ExportMenu';

const TABLE1_SPEEDS = [30, 40, 50, 60, 70, 80, 90, 100, 110];

function snapToTable1Speed(kmh: number | null): number {
    if (kmh === null) return 50;
    return TABLE1_SPEEDS.reduce(
        (best, s) => (Math.abs(s - kmh) < Math.abs(best - kmh) ? s : best),
        TABLE1_SPEEDS[0],
    );
}

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
                <button
                    onClick={onClose}
                    aria-label="Close site detail"
                    className="-mr-2 inline-flex h-11 min-w-11 items-center justify-center rounded text-sm text-gray-500 hover:bg-gray-100"
                >
                    Close
                </button>
            </header>

            <div className="flex-1 overflow-y-auto px-4 py-3 text-sm">
                {error && <p className="text-red-600">Failed to load: {error}</p>}
                {!site && !error && <p className="text-gray-500">Loading…</p>}

                {site && (
                    <>
                        <PrimaryStats site={site} />

                        <RoadContextCard context={site.context} />

                        <h3 className="mt-5 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Detail
                        </h3>
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
                        <Row label="Crashes (5 yr, 1 km)">{site.crash_count_5yr_1km}</Row>
                        <Row label="Road controlling authority">{site.rca ?? '—'}</Row>
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

                        <p className="mt-4 rounded bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            These figures are inputs to your own NZGTTM 2023 risk assessment, not a
                            classification of the site. Road levels were retired in the 2023 guide.
                        </p>

                        <div className="mt-4 border-t pt-4">
                            <ToolsRow
                                speed={site.speed_limit_kmh}
                                peakHourVolume={site.peak_hour_volume}
                            />
                        </div>

                        <div className="mt-4 border-t pt-4">
                            <ExportMenu site={site} />
                        </div>
                    </>
                )}
            </div>
        </aside>
    );
}

function PrimaryStats({ site }: { site: SiteDetail }) {
    return (
        <section
            aria-label="Primary site figures"
            className="-mx-4 mb-4 grid grid-cols-3 gap-px bg-gray-200 px-0 sm:gap-3 sm:bg-transparent"
        >
            <BigStat
                label="AADT"
                value={site.aadt !== null ? site.aadt.toLocaleString() : '—'}
                unit={site.aadt !== null ? 'vpd' : ''}
            />
            <BigStat
                label="Speed"
                value={site.speed_limit_kmh !== null ? String(site.speed_limit_kmh) : '—'}
                unit={site.speed_limit_kmh !== null ? 'km/h' : ''}
            />
            <BigStat
                label="Heavy"
                value={site.heavy_vehicle_pct !== null ? `${site.heavy_vehicle_pct}` : '—'}
                unit={site.heavy_vehicle_pct !== null ? '%' : ''}
            />
        </section>
    );
}

function BigStat({ label, value, unit }: { label: string; value: string; unit: string }) {
    return (
        <div className="bg-white p-3 sm:rounded-md sm:border sm:shadow-sm">
            <div className="text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                {label}
            </div>
            <div className="font-mono text-2xl font-bold tabular-nums leading-tight text-gray-900">
                {value}
                {unit && (
                    <span className="ml-1 text-xs font-normal text-gray-500">{unit}</span>
                )}
            </div>
        </div>
    );
}

function RoadContextCard({ context }: { context: RoadContext }) {
    return (
        <section>
            <h3 className="text-xs font-semibold uppercase tracking-wide text-gray-500">
                Road context
            </h3>
            <ul className="mt-2 space-y-1.5">
                <BandRow icon="🚗" name="Volume" band={context.aadt} />
                <BandRow icon="🚦" name="Speed" band={context.speed} />
                <BandRow icon="🚛" name="Heavy" band={context.heavy} />
                <BandRow icon="⚠️" name="Crashes" band={context.crash} />
            </ul>
        </section>
    );
}

function BandRow({ icon, name, band }: { icon: string; name: string; band: ContextBand }) {
    return (
        <li className="flex items-start gap-2 text-sm">
            <span aria-hidden className="w-5 text-base leading-5">
                {icon}
            </span>
            <div className="min-w-0 flex-1">
                <span className="text-gray-500">{name}: </span>
                <span className="text-gray-900">{band.label}</span>
            </div>
        </li>
    );
}

function ToolsRow({
    speed,
    peakHourVolume,
}: {
    speed: number | null;
    peakHourVolume: number | null;
}) {
    const target = snapToTable1Speed(speed);
    const demand = peakHourVolume ?? 1000;
    return (
        <div>
            <h3 className="text-sm font-semibold">NZGTTM tools</h3>
            <div className="mt-2 flex flex-col gap-2">
                <Link
                    href={`/tools/layout?speed=${target}`}
                    className="inline-flex min-h-[44px] items-center rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Layout calculator @ {target} km/h →
                </Link>
                <Link
                    href={`/tools/queue?temp_speed_kmh=${target}&demand_vph=${demand}`}
                    className="inline-flex min-h-[44px] items-center rounded border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                >
                    Queue calculator @ {demand.toLocaleString()} vph →
                </Link>
            </div>
        </div>
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
