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

export type ZoneGeometry =
    | { type: 'Polygon'; coordinates: [number, number][][] }
    | { type: 'MultiPolygon'; coordinates: [number, number][][][] };

export interface SpeedLimitZoneFeature {
    type: 'Feature';
    id: number;
    geometry: ZoneGeometry;
    properties: {
        id: number;
        road_name: string | null;
        zone_name: string | null;
        rca: string | null;
        speed_limit_kmh: number | null;
        speed_limit_type: string | null;
    };
}

export interface RcaFeature {
    type: 'Feature';
    id: number;
    geometry: ZoneGeometry;
    properties: {
        id: number;
        code: string | null;
        name: string;
        kind: 'territorial_authority' | 'state_highway_network';
    };
}

export interface FeatureCollection<F> {
    type: 'FeatureCollection';
    features: F[];
}

export interface ContextBand {
    band: string | null;
    label: string;
}

export interface RoadContext {
    aadt: ContextBand;
    speed: ContextBand;
    heavy: ContextBand;
    crash: ContextBand;
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
    rca: string | null;
    crash_count_5yr_1km: number;
    context: RoadContext;
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
