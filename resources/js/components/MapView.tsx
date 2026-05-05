import { APIProvider, Map, useMap } from '@vis.gl/react-google-maps';
import { useEffect, useRef, useState } from 'react';
import type { Bbox } from '@/lib/api';
import { fetchSegments, fetchSites } from '@/lib/api';
import { colourFor } from '@/lib/nzgttm';
import type { FeatureCollection, NzgttmLevel, SegmentFeature, SiteFeature } from '@/types';

interface Props {
    apiKey: string | null;
    onSelect: (siteId: number) => void;
    showSpeedLimits: boolean;
    showAadtLines: boolean;
}

const NZ_CENTRE = { lat: -41.0, lng: 174.0 };

export function MapView({ apiKey, onSelect, showSpeedLimits, showAadtLines }: Props) {
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
                mapId="roadbase"
                defaultCenter={NZ_CENTRE}
                defaultZoom={6}
                gestureHandling="greedy"
                disableDefaultUI={false}
                style={{ width: '100%', height: '100%' }}
            >
                <BboxLayers
                    onSelect={onSelect}
                    showSpeedLimits={showSpeedLimits}
                    showAadtLines={showAadtLines}
                />
            </Map>
        </APIProvider>
    );
}

function BboxLayers({
    onSelect,
    showSpeedLimits,
    showAadtLines,
}: {
    onSelect: (id: number) => void;
    showSpeedLimits: boolean;
    showAadtLines: boolean;
}) {
    const map = useMap();
    const [sites, setSites] = useState<FeatureCollection<SiteFeature> | null>(null);
    const [aadtLines, setAadtLines] = useState<FeatureCollection<SegmentFeature> | null>(null);
    const [speedLimits, setSpeedLimits] = useState<FeatureCollection<SegmentFeature> | null>(null);

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
                if (showAadtLines) {
                    fetchSegments(bbox, zoom, 'aadt_line', ac.signal)
                        .then(setAadtLines)
                        .catch(() => {});
                } else {
                    setAadtLines(null);
                }
                if (showSpeedLimits) {
                    fetchSegments(bbox, zoom, 'speed_limit', ac.signal)
                        .then(setSpeedLimits)
                        .catch(() => {});
                } else {
                    setSpeedLimits(null);
                }
            }, 250);
        };
        const idle = map.addListener('idle', refresh);
        refresh();
        return () => idle.remove();
    }, [map, showSpeedLimits, showAadtLines]);

    useSiteMarkers(map, sites, onSelect);
    usePolylines(map, aadtLines, (level) => colourFor(level), 4);
    usePolylines(map, speedLimits, () => '#7e57c2', 2);

    return null;
}

function useSiteMarkers(
    map: google.maps.Map | null,
    fc: FeatureCollection<SiteFeature> | null,
    onSelect: (id: number) => void,
) {
    const markersRef = useRef<google.maps.marker.AdvancedMarkerElement[]>([]);

    useEffect(() => {
        if (!map) return;
        markersRef.current.forEach((m) => (m.map = null));
        markersRef.current = [];

        if (!fc) return;

        const lib = google.maps.marker;
        if (!lib?.AdvancedMarkerElement) return;

        for (const feat of fc.features) {
            const [lng, lat] = feat.geometry.coordinates;
            const colour = colourFor(feat.properties.nzgttm_level);
            const dot = document.createElement('div');
            dot.style.width = '14px';
            dot.style.height = '14px';
            dot.style.borderRadius = '50%';
            dot.style.background = colour;
            dot.style.border = '2px solid white';
            dot.style.boxShadow = '0 0 0 1px rgba(0,0,0,0.3)';
            dot.style.cursor = 'pointer';

            const marker = new lib.AdvancedMarkerElement({
                map,
                position: { lat, lng },
                content: dot,
            });
            marker.addListener('gmp-click', () => onSelect(feat.properties.id));
            markersRef.current.push(marker);
        }

        return () => {
            markersRef.current.forEach((m) => (m.map = null));
            markersRef.current = [];
        };
    }, [map, fc, onSelect]);
}

function usePolylines(
    map: google.maps.Map | null,
    fc: FeatureCollection<SegmentFeature> | null,
    colourPicker: (level: NzgttmLevel | null) => string,
    weight: number,
) {
    const linesRef = useRef<google.maps.Polyline[]>([]);

    useEffect(() => {
        if (!map) return;
        linesRef.current.forEach((l) => l.setMap(null));
        linesRef.current = [];

        if (!fc) return;

        for (const feat of fc.features) {
            const path = feat.geometry.coordinates.map(([lng, lat]) => ({ lat, lng }));
            const line = new google.maps.Polyline({
                path,
                strokeColor: colourPicker(feat.properties.nzgttm_level),
                strokeOpacity: 0.8,
                strokeWeight: weight,
                map,
            });
            linesRef.current.push(line);
        }

        return () => {
            linesRef.current.forEach((l) => l.setMap(null));
            linesRef.current = [];
        };
    }, [map, fc, colourPicker, weight]);
}
