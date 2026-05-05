import type { FeatureCollection, SiteDetail, SiteFeature, SpeedLimitZoneFeature } from '@/types';

export type Bbox = [number, number, number, number];

export function bboxParam(b: Bbox): string {
    return b.join(',');
}

async function getJson<T>(url: string, signal?: AbortSignal): Promise<T> {
    const r = await fetch(url, { headers: { Accept: 'application/json' }, signal });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    return r.json() as Promise<T>;
}

export function fetchSites(b: Bbox, z: number, signal?: AbortSignal) {
    return getJson<FeatureCollection<SiteFeature>>(
        `/api/layers/sites?bbox=${bboxParam(b)}&z=${z}`,
        signal,
    );
}

export function fetchSpeedLimitZones(b: Bbox, z: number, signal?: AbortSignal) {
    return getJson<FeatureCollection<SpeedLimitZoneFeature>>(
        `/api/layers/segments?bbox=${bboxParam(b)}&z=${z}&kind=speed_limit`,
        signal,
    );
}

export function fetchCrashes(b: Bbox, z: number, signal?: AbortSignal) {
    return getJson<FeatureCollection<SiteFeature>>(
        `/api/layers/crashes?bbox=${bboxParam(b)}&z=${z}`,
        signal,
    );
}

export function fetchSite(id: number, signal?: AbortSignal) {
    return getJson<SiteDetail>(`/api/sites/${id}`, signal);
}
