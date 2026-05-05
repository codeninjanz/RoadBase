import type { FeatureCollection, SegmentFeature, SiteDetail, SiteFeature } from '@/types';

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

export function fetchSegments(
    b: Bbox,
    z: number,
    kind: 'aadt_line' | 'speed_limit',
    signal?: AbortSignal,
) {
    return getJson<FeatureCollection<SegmentFeature>>(
        `/api/layers/segments?bbox=${bboxParam(b)}&z=${z}&kind=${kind}`,
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
