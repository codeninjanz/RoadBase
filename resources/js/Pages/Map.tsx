import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { LayerToggles } from '@/components/LayerToggles';
import { MapView } from '@/components/MapView';
import { SiteDetailPanel } from '@/components/SiteDetailPanel';
import type { InertiaSharedProps } from '@/types';

export default function MapPage() {
    const { props } = usePage<InertiaSharedProps>();
    const [selected, setSelected] = useState<number | null>(null);
    const [showSpeedLimits, setShowSpeedLimits] = useState(false);
    const [showRcas, setShowRcas] = useState(false);
    const [showCentrelines, setShowCentrelines] = useState(false);

    return (
        <>
            <Head title="Map" />
            <div className="flex h-screen flex-col">
                <header className="z-20 flex items-center justify-between border-b bg-white px-4 py-2 shadow-sm">
                    <div className="flex items-center gap-3">
                        <span className="text-xl">🛣️</span>
                        <h1 className="text-lg font-bold tracking-tight">RoadBase</h1>
                        <span className="hidden text-xs text-gray-500 sm:inline">
                            NZ traffic volumes for TMPs under NZGTTM
                        </span>
                    </div>
                    <nav className="flex items-center gap-3 text-sm">
                        <Link href="/tools/layout" className="text-gray-600 hover:text-gray-900">
                            Layout
                        </Link>
                        <Link href="/tools/queue" className="text-gray-600 hover:text-gray-900">
                            Queue
                        </Link>
                        <Link href="/about" className="text-gray-600 hover:text-gray-900">
                            About
                        </Link>
                    </nav>
                </header>

                <main className="relative flex-1">
                    <MapView
                        apiKey={props.maps.browserKey}
                        onSelect={setSelected}
                        showSpeedLimits={showSpeedLimits}
                        showRcas={showRcas}
                        showCentrelines={showCentrelines}
                    />
                    <LayerToggles
                        showSpeedLimits={showSpeedLimits}
                        onToggleSpeedLimits={setShowSpeedLimits}
                        showRcas={showRcas}
                        onToggleRcas={setShowRcas}
                        showCentrelines={showCentrelines}
                        onToggleCentrelines={setShowCentrelines}
                    />
                    {selected !== null && (
                        <SiteDetailPanel siteId={selected} onClose={() => setSelected(null)} />
                    )}
                </main>
            </div>
        </>
    );
}
