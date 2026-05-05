export type NzgttmLevel = 'LV' | '1' | '2' | '3';

export interface SiteFeature {
    type: 'Feature';
    id: number;
    geometry: { type: 'Point'; coordinates: [number, number] };
    properties: {
        id: number;
        road_name: string | null;
        aadt: number | null;
        heavy_vehicle_pct: number | null;
        speed_limit_kmh: number | null;
        nzgttm_level: NzgttmLevel | null;
        count_date: string | null;
        source_key: string;
        source_name: string;
    };
}

export interface SegmentFeature {
    type: 'Feature';
    id: number;
    geometry: { type: 'LineString'; coordinates: [number, number][] };
    properties: {
        id: number;
        road_name: string | null;
        aadt: number | null;
        speed_limit_kmh: number | null;
        nzgttm_level: NzgttmLevel | null;
    };
}

export interface FeatureCollection<F> {
    type: 'FeatureCollection';
    features: F[];
}

export interface SiteDetail {
    id: number;
    road_name: string | null;
    region: string | null;
    lat: number;
    lng: number;
    aadt: number | null;
    heavy_vehicle_pct: number | null;
    peak_hour_volume: number | null;
    peak_hour_start: string | null;
    count_date: string | null;
    speed_limit_kmh: number | null;
    nzgttm_level: NzgttmLevel | null;
    synced_at: string | null;
    source: {
        key: string;
        name: string;
        url: string | null;
        last_synced_at: string | null;
    };
}

export interface InertiaSharedProps {
    app: { name: string };
    maps: { browserKey: string | null };
}
