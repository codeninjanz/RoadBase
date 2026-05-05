import { APIProvider, Map, useMap } from '@vis.gl/react-google-maps';
import { useEffect, useRef, useState } from 'react';
import type { Bbox } from '@/lib/api';
import { fetchSites, fetchSpeedLimitZones } from '@/lib/api';
import { colourFor } from '@/lib/nzgttm';
import type { FeatureCollection, SiteFeature, SpeedLimitZoneFeature } from '@/types';

interface Props {
    apiKey: string | null;
    onSelect: (siteId: number) => void;
    showSpeedLimits: boolean;
}

const NZ_CENTRE = { lat: -41.0, lng: 174.0 };

export function MapView({ apiKey, onSelect, showSpeedLimits }: Props) {
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
                <BboxLayers onSelect={onSelect} showSpeedLimits={showSpeedLimits} />
            </Map>
        </APIProvider>
    );
}

function BboxLayers({
    onSelect,
    showSpeedLimits,
}: {
    onSelect: (id: number) => void;
    showSpeedLimits: boolean;
}) {
    const map = useMap();
    const [sites, setSites] = useState<FeatureCollection<SiteFeature> | null>(null);
    const [zones, setZones] = useState<FeatureCollection<SpeedLimitZoneFeature> | null>(null);

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

                console.debug('[RoadBase] fetching sites', { bbox, zoom });

                const ac = new AbortController();
                fetchSites(bbox, zoom, ac.signal)
                    .then((fc) => {
                        console.debug('[RoadBase] sites fetched:', fc.features.length);
                        setSites(fc);
                    })
                    .catch((e) => {
                        console.error('[RoadBase] sites fetch failed:', e);
                    });
                if (showSpeedLimits && zoom >= 11) {
                    fetchSpeedLimitZones(bbox, zoom, ac.signal)
                        .then(setZones)
                        .catch(() => {});
                } else {
                    setZones(null);
                }
            }, 250);
        };
        const idle = map.addListener('idle', refresh);
        refresh();
        return () => idle.remove();
    }, [map, showSpeedLimits]);

    useSiteMarkers(map, sites, onSelect);
    useZonePolygons(map, zones);

    return null;
}

function useSiteMarkers(
    map: google.maps.Map | null,
    fc: FeatureCollection<SiteFeature> | null,
    onSelect: (id: number) => void,
) {
    const markersRef = useRef<google.maps.Marker[]>([]);

    useEffect(() => {
        if (!map) {
            console.debug('[RoadBase] useSiteMarkers: map not ready yet');
            return;
        }
        markersRef.current.forEach((m) => m.setMap(null));
        markersRef.current = [];

        if (!fc) {
            console.debug('[RoadBase] useSiteMarkers: no feature collection yet');
            return;
        }

        console.debug(
            `[RoadBase] useSiteMarkers: creating ${fc.features.length} markers`,
        );

        for (const feat of fc.features) {
            const [lng, lat] = feat.geometry.coordinates;
            const colour = colourFor(feat.properties.nzgttm_level);

            const marker = new google.maps.Marker({
                map,
                position: { lat, lng },
                title: feat.properties.road_name ?? `Site ${feat.properties.id}`,
                icon: circleIcon(colour),
            });
            marker.addListener('click', () => onSelect(feat.properties.id));
            markersRef.current.push(marker);
        }

        console.debug(
            `[RoadBase] useSiteMarkers: ${markersRef.current.length} markers attached to map`,
        );

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

/**
 * Colour a speed-limit zone by its posted speed (visualises the speed-management
 * gradient from urban 30s through to motorway 110s).
 */
function speedColour(kmh: number | null): string {
    if (kmh === null) return '#9e9e9e';
    if (kmh <= 30) return '#7e57c2';
    if (kmh <= 50) return '#5c6bc0';
    if (kmh <= 70) return '#26a69a';
    if (kmh <= 80) return '#66bb6a';
    if (kmh <= 100) return '#ffa726';
    return '#ef5350';
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
