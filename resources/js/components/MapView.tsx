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

        const refresh = () => {
            if (debounceRef.current) window.clearTimeout(debounceRef.current);
            debounceRef.current = window.setTimeout(() => {
                const bounds = map.getBounds();
                const zoom = map.getZoom() ?? 6;
                if (!bounds) return;
                const sw = bounds.getSouthWest();
                const ne = bounds.getNorthEast();
                const bbox: Bbox = [sw.lng(), sw.lat(), ne.lng(), ne.lat()];

                const ac = new AbortController();
                fetchSites(bbox, zoom, ac.signal)
                    .then(setSites)
                    .catch(() => {});
                if (showSpeedLimits && zoom >= 11) {
                    fetchSpeedLimitZones(bbox, zoom, ac.signal)
                        .then(setZones)
                        .catch(() => {});
                } else {
                    setZones(null);
                }
                if (showRcas) {
                    fetchRcas(bbox, zoom, ac.signal)
                        .then(setRcas)
                        .catch(() => {});
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
                    ring.map((p) => vertexToLatLng(p as [number, number])),
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

/**
 * MySQL ST_AsGeoJSON on a geographic SRS column emits coords in the SRS
 * axis order (lat,lng for EPSG:4326) rather than the RFC 7946 lng,lat that
 * google.maps.Polygon expects. We can't reliably force the order from the
 * server side, so detect per-vertex: NZ latitudes are always in
 * [-47, -34] and longitudes in [165, 180] (plus a small Chathams sliver
 * past the antimeridian). |first| > 90 means the first slot is longitude
 * and the input is RFC-correct; otherwise the first slot is latitude and
 * we swap.
 */
function vertexToLatLng(pair: [number, number]): { lat: number; lng: number } {
    const [a, b] = pair;
    return Math.abs(a) > 90 ? { lng: a, lat: b } : { lat: a, lng: b };
}

function useRcaPolygons(
    map: google.maps.Map | null,
    fc: FeatureCollection<RcaFeature> | null,
) {
    const polygonsRef = useRef<google.maps.Polygon[]>([]);
    const labelsRef = useRef<google.maps.Marker[]>([]);

    useEffect(() => {
        if (!map) return;
        polygonsRef.current.forEach((p) => p.setMap(null));
        polygonsRef.current = [];
        labelsRef.current.forEach((m) => m.setMap(null));
        labelsRef.current = [];

        if (!fc) return;

        for (const feat of fc.features) {
            const ringSets =
                feat.geometry.type === 'Polygon'
                    ? [feat.geometry.coordinates]
                    : feat.geometry.coordinates;

            const fill = rcaFill(feat.id);
            // Track largest ring (by vertex count) so the label can sit on
            // the main landmass rather than an offshore island sliver.
            let largestRing: { lat: number; lng: number }[] = [];

            for (const polygonRings of ringSets) {
                if (!Array.isArray(polygonRings) || polygonRings.length === 0) continue;
                const paths = polygonRings
                    .filter((ring) => Array.isArray(ring) && ring.length >= 3)
                    .map((ring) => ring.map((p) => vertexToLatLng(p as [number, number])));
                if (paths.length === 0) continue;

                if (paths[0].length > largestRing.length) {
                    largestRing = paths[0];
                }

                const polygon = new google.maps.Polygon({
                    paths,
                    strokeColor: RCA_STROKE,
                    strokeOpacity: 0.85,
                    strokeWeight: 1.5,
                    fillColor: fill,
                    fillOpacity: 0.15,
                    clickable: false,
                    map,
                });
                polygonsRef.current.push(polygon);
            }

            if (largestRing.length > 0 && feat.properties?.name) {
                const c = ringCentroid(largestRing);
                const labelMarker = new google.maps.Marker({
                    position: c,
                    map,
                    clickable: false,
                    icon: {
                        // Invisible 1×1 anchor — the marker is just a label host.
                        path: 'M 0,0 0,0',
                        strokeOpacity: 0,
                        scale: 0,
                    },
                    label: {
                        text: feat.properties.name,
                        color: '#0f172a',
                        fontSize: '11px',
                        fontWeight: '600',
                    },
                });
                labelsRef.current.push(labelMarker);
            }
        }

        return () => {
            polygonsRef.current.forEach((p) => p.setMap(null));
            polygonsRef.current = [];
            labelsRef.current.forEach((m) => m.setMap(null));
            labelsRef.current = [];
        };
    }, [map, fc]);
}

function ringCentroid(ring: { lat: number; lng: number }[]): { lat: number; lng: number } {
    let sumLat = 0;
    let sumLng = 0;
    for (const p of ring) {
        sumLat += p.lat;
        sumLng += p.lng;
    }
    return { lat: sumLat / ring.length, lng: sumLng / ring.length };
}
