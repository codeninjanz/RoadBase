import { APIProvider, Map, useMap } from '@vis.gl/react-google-maps';
import { useEffect, useRef, useState } from 'react';
import type { Bbox } from '@/lib/api';
import { fetchRcas, fetchSites, fetchSpeedLimitZones } from '@/lib/api';
import { speedColour } from '@/lib/nzgttm';
import type {
    FeatureCollection,
    RcaFeature,
    SiteFeature,
    SpeedLimitZoneFeature,
} from '@/types';

interface Props {
    apiKey: string | null;
    onSelect: (siteId: number) => void;
    showSpeedLimits: boolean;
    showRcas: boolean;
}

const NZ_CENTRE = { lat: -41.0, lng: 174.0 };

export function MapView({ apiKey, onSelect, showSpeedLimits, showRcas }: Props) {
    if (!apiKey) {
        return (
            <div className="flex h-full items-center justify-center bg-gray-50 text-center text-sm text-gray-600">
                <p>
                    Google Maps API key not configured. Set <code>GOOGLE_MAPS_BROWSER_KEY</code> in
                    your <code>.env</code> and reload.
                </p>
            </div>
        );
    }

    return (
        <APIProvider apiKey={apiKey}>
            <Map
                defaultCenter={NZ_CENTRE}
                defaultZoom={6}
                gestureHandling="greedy"
                disableDefaultUI={false}
                style={{ width: '100%', height: '100%' }}
            >
                <BboxLayers
                    onSelect={onSelect}
                    showSpeedLimits={showSpeedLimits}
                    showRcas={showRcas}
                />
                <MyLocationControl />
            </Map>
        </APIProvider>
    );
}

function BboxLayers({
    onSelect,
    showSpeedLimits,
    showRcas,
}: {
    onSelect: (id: number) => void;
    showSpeedLimits: boolean;
    showRcas: boolean;
}) {
    const map = useMap();
    const [sites, setSites] = useState<FeatureCollection<SiteFeature> | null>(null);
    const [zones, setZones] = useState<FeatureCollection<SpeedLimitZoneFeature> | null>(null);
    const [rcas, setRcas] = useState<FeatureCollection<RcaFeature> | null>(null);

    const debounceRef = useRef<number | null>(null);

    useEffect(() => {
        if (!map) return;
        console.log('[roadbase] BboxLayers effect:', { showSpeedLimits, showRcas });

        const refresh = () => {
            if (debounceRef.current) window.clearTimeout(debounceRef.current);
            debounceRef.current = window.setTimeout(() => {
                const bounds = map.getBounds();
                const zoom = map.getZoom() ?? 6;
                if (!bounds) {
                    console.warn('[roadbase] no bounds yet, skipping refresh');
                    return;
                }
                const sw = bounds.getSouthWest();
                const ne = bounds.getNorthEast();
                const bbox: Bbox = [sw.lng(), sw.lat(), ne.lng(), ne.lat()];
                console.log('[roadbase] refresh', { zoom, bbox, showSpeedLimits, showRcas });

                const ac = new AbortController();
                fetchSites(bbox, zoom, ac.signal)
                    .then((fc) => {
                        console.log('[roadbase] sites response', fc.features.length);
                        setSites(fc);
                    })
                    .catch((e) => console.warn('[roadbase] sites fetch failed', e));
                if (showSpeedLimits && zoom >= 11) {
                    console.log('[roadbase] fetching speed zones');
                    fetchSpeedLimitZones(bbox, zoom, ac.signal)
                        .then((fc) => {
                            console.log('[roadbase] zones response', fc.features.length);
                            setZones(fc);
                        })
                        .catch((e) => console.warn('[roadbase] zones fetch failed', e));
                } else {
                    if (showSpeedLimits) {
                        console.log('[roadbase] zoom < 11 (got', zoom, ') — speed zones suppressed');
                    }
                    setZones(null);
                }
                if (showRcas) {
                    console.log('[roadbase] fetching rcas at zoom', zoom);
                    fetchRcas(bbox, zoom, ac.signal)
                        .then((fc) => {
                            console.log('[roadbase] rcas response', fc.features.length, 'first:', fc.features[0]);
                            setRcas(fc);
                        })
                        .catch((e) => console.warn('[roadbase] rcas fetch failed', e));
                } else {
                    setRcas(null);
                }
            }, 250);
        };
        const idle = map.addListener('idle', refresh);
        refresh();
        return () => idle.remove();
    }, [map, showSpeedLimits, showRcas]);

    useSiteMarkers(map, sites, onSelect);
    useZonePolygons(map, zones);
    useRcaPolygons(map, rcas);

    return null;
}

function useSiteMarkers(
    map: google.maps.Map | null,
    fc: FeatureCollection<SiteFeature> | null,
    onSelect: (id: number) => void,
) {
    const markersRef = useRef<google.maps.Marker[]>([]);

    useEffect(() => {
        if (!map) return;
        markersRef.current.forEach((m) => m.setMap(null));
        markersRef.current = [];

        if (!fc) return;

        for (const feat of fc.features) {
            const [lng, lat] = feat.geometry.coordinates;
            const colour = speedColour(feat.properties.speed_limit_kmh);

            const marker = new google.maps.Marker({
                map,
                position: { lat, lng },
                title: feat.properties.road_name ?? `Site ${feat.properties.id}`,
                icon: circleIcon(colour),
            });
            marker.addListener('click', () => onSelect(feat.properties.id));
            markersRef.current.push(marker);
        }

        return () => {
            markersRef.current.forEach((m) => m.setMap(null));
            markersRef.current = [];
        };
    }, [map, fc, onSelect]);
}

/**
 * SVG data-URL marker icon — coloured circle with a white halo. SymbolPath.CIRCLE
 * was rendering as invisible against some basemaps; this is unambiguous.
 */
function circleIcon(fill: string): google.maps.Icon {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 18 18">
        <circle cx="9" cy="9" r="6" fill="${fill}" stroke="white" stroke-width="2"/>
    </svg>`;
    return {
        url: 'data:image/svg+xml;utf8,' + encodeURIComponent(svg),
        scaledSize: new google.maps.Size(18, 18),
        anchor: new google.maps.Point(9, 9),
    };
}

function MyLocationControl() {
    const map = useMap();
    if (!map) return null;

    const onClick = () => {
        if (!('geolocation' in navigator)) return;
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                map.panTo({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                if ((map.getZoom() ?? 0) < 14) map.setZoom(14);
            },
            () => {},
            { enableHighAccuracy: true, timeout: 8000 },
        );
    };

    return (
        <button
            type="button"
            onClick={onClick}
            aria-label="Centre map on my location"
            title="Centre on my location"
            className="absolute bottom-4 right-4 z-10 flex h-12 w-12 items-center justify-center rounded-full bg-white shadow-lg ring-1 ring-black/10 hover:bg-gray-50 active:bg-gray-100"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                width="22"
                height="22"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden
            >
                <circle cx="12" cy="12" r="3" />
                <path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
            </svg>
        </button>
    );
}

function useZonePolygons(
    map: google.maps.Map | null,
    fc: FeatureCollection<SpeedLimitZoneFeature> | null,
) {
    const polygonsRef = useRef<google.maps.Polygon[]>([]);

    useEffect(() => {
        if (!map) return;
        polygonsRef.current.forEach((p) => p.setMap(null));
        polygonsRef.current = [];

        if (!fc) return;

        for (const feat of fc.features) {
            const colour = speedColour(feat.properties.speed_limit_kmh);
            const ringSets =
                feat.geometry.type === 'Polygon'
                    ? [feat.geometry.coordinates]
                    : feat.geometry.coordinates;

            for (const polygonRings of ringSets) {
                const paths = polygonRings.map((ring) =>
                    ring.map(([lng, lat]) => ({ lat, lng })),
                );
                const polygon = new google.maps.Polygon({
                    paths,
                    strokeColor: colour,
                    strokeOpacity: 0.7,
                    strokeWeight: 1,
                    fillColor: colour,
                    fillOpacity: 0.15,
                    clickable: false,
                    map,
                });
                polygonsRef.current.push(polygon);
            }
        }

        return () => {
            polygonsRef.current.forEach((p) => p.setMap(null));
            polygonsRef.current = [];
        };
    }, [map, fc]);
}

const RCA_STROKE = '#0f766e';
// Cycle through pastel tints so adjacent territorial authorities are visually
// distinguishable. Hashing on the feature id keeps the colour stable across
// re-renders.
const RCA_FILL_PALETTE = [
    '#0f766e', '#1d4ed8', '#7c3aed', '#db2777', '#ea580c',
    '#65a30d', '#0891b2', '#be123c', '#a16207', '#0d9488',
];

function rcaFill(id: number): string {
    return RCA_FILL_PALETTE[Math.abs(id) % RCA_FILL_PALETTE.length];
}

function useRcaPolygons(
    map: google.maps.Map | null,
    fc: FeatureCollection<RcaFeature> | null,
) {
    const polygonsRef = useRef<google.maps.Polygon[]>([]);

    useEffect(() => {
        if (!map) {
            console.log('[roadbase] useRcaPolygons: no map yet');
            return;
        }
        polygonsRef.current.forEach((p) => p.setMap(null));
        polygonsRef.current = [];

        if (!fc) {
            console.log('[roadbase] useRcaPolygons: no fc, polygons cleared');
            return;
        }

        console.log('[roadbase] useRcaPolygons: rendering', fc.features.length, 'features');
        let drew = 0;
        for (const feat of fc.features) {
            const ringSets =
                feat.geometry.type === 'Polygon'
                    ? [feat.geometry.coordinates]
                    : feat.geometry.coordinates;

            const fill = rcaFill(feat.id);

            for (const polygonRings of ringSets) {
                if (!Array.isArray(polygonRings) || polygonRings.length === 0) {
                    console.log('[roadbase] skip empty ringSet for', feat.properties?.name);
                    continue;
                }
                const paths = polygonRings
                    .filter((ring) => Array.isArray(ring) && ring.length >= 3)
                    .map((ring) => ring.map(([lng, lat]) => ({ lat, lng })));
                if (paths.length === 0) {
                    console.log('[roadbase] skip degenerate paths for', feat.properties?.name);
                    continue;
                }

                try {
                    const polygon = new google.maps.Polygon({
                        paths,
                        strokeColor: RCA_STROKE,
                        strokeOpacity: 0.9,
                        strokeWeight: 2,
                        fillColor: fill,
                        fillOpacity: 0.18,
                        clickable: false,
                        map,
                    });
                    polygonsRef.current.push(polygon);
                    drew++;
                } catch (err) {
                    console.warn('[roadbase] polygon ctor failed for', feat.properties?.name, err);
                }
            }
        }
        console.log('[roadbase] useRcaPolygons: drew', drew, 'polygons total');

        return () => {
            polygonsRef.current.forEach((p) => p.setMap(null));
            polygonsRef.current = [];
        };
    }, [map, fc]);
}
