<?php

namespace App\Http\Controllers;

use App\Support\Nzgttm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TmpExportController extends Controller
{
    /** Crash density radius (degrees ~ 1 km at NZ latitudes). */
    private const CRASH_RADIUS_DEG = 0.009;

    /** Crash density window (years back from today). */
    private const CRASH_WINDOW_YEARS = 5;

    public function text(int $site): HttpResponse
    {
        $data = $this->fetch($site);
        if (! $data) {
            return response('Not found', 404);
        }

        $body = $this->plainText($data);
        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="context-'.$site.'.txt"',
        ]);
    }

    public function pdf(int $site): HttpResponse
    {
        $data = $this->fetch($site);
        if (! $data) {
            return response('Not found', 404);
        }

        $pdf = Pdf::loadView('exports.context', [
            'site' => $data,
            'staticMapUrl' => $this->staticMapUrl($data['lat'], $data['lng']),
            'mobileRoadUrl' => $this->mobileRoadUrl($data['lat'], $data['lng']),
            'guide' => [
                'url' => Nzgttm::GUIDE_URL,
                'label' => Nzgttm::GUIDE_LABEL,
            ],
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        return $pdf->download("context-{$site}.pdf");
    }

    private function fetch(int $site): ?array
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT
                cs.id, cs.road_name, cs.region, cs.aadt, cs.heavy_vehicle_pct,
                cs.peak_hour_volume, cs.peak_hour_start, cs.count_date,
                cs.speed_limit_kmh, cs.synced_at,
                ST_Longitude(cs.location) AS lng, ST_Latitude(cs.location) AS lat,
                ds.name AS source_name, ds.url AS source_url
            FROM count_sites cs
            JOIN data_sources ds ON ds.id = cs.data_source_id
            WHERE cs.id = ?
        SQL, [$site]);

        if (! $row) {
            return null;
        }

        $lat = (float) $row->lat;
        $lng = (float) $row->lng;

        return [
            'id' => (int) $row->id,
            'road_name' => $row->road_name,
            'region' => $row->region,
            'lat' => $lat,
            'lng' => $lng,
            'aadt' => $row->aadt !== null ? (int) $row->aadt : null,
            'heavy_vehicle_pct' => $row->heavy_vehicle_pct !== null ? (float) $row->heavy_vehicle_pct : null,
            'peak_hour_volume' => $row->peak_hour_volume !== null ? (int) $row->peak_hour_volume : null,
            'peak_hour_start' => $row->peak_hour_start,
            'count_date' => $row->count_date,
            'speed_limit_kmh' => $row->speed_limit_kmh !== null ? (int) $row->speed_limit_kmh : null,
            'synced_at' => $row->synced_at,
            'source_name' => $row->source_name,
            'source_url' => $row->source_url,
            'rca' => $this->nearestRca($lat, $lng),
            'crashes' => $this->crashSummary($lat, $lng),
            'speed_zones' => $this->nearbySpeedZones($lat, $lng),
        ];
    }

    private function nearestRca(float $lat, float $lng): ?string
    {
        $nslr = DB::selectOne(<<<'SQL'
            SELECT rca
            FROM road_segments
            WHERE kind = 'speed_limit'
              AND rca IS NOT NULL
              AND MBRContains(geom, ST_SRID(POINT(?, ?), 4326))
              AND ST_Contains(geom, ST_SRID(POINT(?, ?), 4326))
            ORDER BY ST_Area(geom) ASC
            LIMIT 1
        SQL, [$lng, $lat, $lng, $lat]);

        if (! empty($nslr?->rca)) {
            return $nslr->rca;
        }

        $ta = DB::selectOne(<<<'SQL'
            SELECT name
            FROM rcas
            WHERE kind = 'territorial_authority'
              AND MBRContains(geom, ST_SRID(POINT(?, ?), 4326))
              AND ST_Contains(geom, ST_SRID(POINT(?, ?), 4326))
            LIMIT 1
        SQL, [$lng, $lat, $lng, $lat]);

        return $ta->name ?? null;
    }

    /**
     * @return array{
     *     count: int,
     *     fatal: int,
     *     serious: int,
     *     minor: int,
     *     non_injury: int,
     *     since_year: int,
     * }
     */
    private function crashSummary(float $lat, float $lng): array
    {
        $minYear = (int) date('Y') - self::CRASH_WINDOW_YEARS;
        $r = self::CRASH_RADIUS_DEG;
        $wkt = sprintf(
            'POLYGON((%F %F, %F %F, %F %F, %F %F, %F %F))',
            $lng - $r, $lat - $r,
            $lng + $r, $lat - $r,
            $lng + $r, $lat + $r,
            $lng - $r, $lat + $r,
            $lng - $r, $lat - $r,
        );

        $rows = DB::select(<<<'SQL'
            SELECT severity, COUNT(*) AS n
            FROM crashes
            WHERE crash_year >= ?
              AND MBRContains(
                    ST_SRID(ST_GeomFromText(?), 4326),
                    location
                  )
            GROUP BY severity
        SQL, [$minYear, $wkt]);

        $counts = ['fatal' => 0, 'serious' => 0, 'minor' => 0, 'non_injury' => 0];
        foreach ($rows as $row) {
            $sev = $row->severity ?? 'non_injury';
            if (isset($counts[$sev])) {
                $counts[$sev] = (int) $row->n;
            }
        }

        return [
            'count' => array_sum($counts),
            'fatal' => $counts['fatal'],
            'serious' => $counts['serious'],
            'minor' => $counts['minor'],
            'non_injury' => $counts['non_injury'],
            'since_year' => $minYear,
        ];
    }

    /**
     * Speed-limit zones whose polygon contains the site, ordered most-specific
     * first (smallest area). Useful for showing speed environment context.
     *
     * @return array<int, array{speed_kmh: ?int, zone_name: ?string, type: ?string}>
     */
    private function nearbySpeedZones(float $lat, float $lng): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT speed_limit_kmh, zone_name, speed_limit_type
            FROM road_segments
            WHERE kind = 'speed_limit'
              AND MBRContains(geom, ST_SRID(POINT(?, ?), 4326))
              AND ST_Contains(geom, ST_SRID(POINT(?, ?), 4326))
            ORDER BY ST_Area(geom) ASC
            LIMIT 5
        SQL, [$lng, $lat, $lng, $lat]);

        return array_map(fn ($r) => [
            'speed_kmh' => $r->speed_limit_kmh !== null ? (int) $r->speed_limit_kmh : null,
            'zone_name' => $r->zone_name,
            'type' => $r->speed_limit_type,
        ], $rows);
    }

    private function plainText(array $d): string
    {
        $sev = $d['crashes'];
        $zones = $d['speed_zones'];
        $zoneSummary = empty($zones)
            ? 'No NSLR speed-limit zone data overlapping this site.'
            : implode("\n  ", array_map(
                fn ($z) => sprintf('%s km/h%s%s',
                    $z['speed_kmh'] ?? '?',
                    $z['type'] ? " ({$z['type']})" : '',
                    $z['zone_name'] ? " — {$z['zone_name']}" : '',
                ),
                $zones,
            ));

        $lines = [
            'NZGTTM Activity & Environment Context',
            'CONTEXT INPUT — NOT A TMP. Use as one input to your own NZGTTM 2023 risk assessment.',
            '',
            'Road: '.($d['road_name'] ?? 'Unknown'),
            'Region: '.($d['region'] ?? 'N/A'),
            'Location (lat, lng): '.number_format($d['lat'], 6).', '.number_format($d['lng'], 6),
            'Open in Mobile Road for RP: https://mobileroad.org/  (paste the coords above)',
            '',
            '— Network users —',
            'AADT: '.($d['aadt'] !== null ? number_format($d['aadt']).' vpd' : 'N/A'),
            'Heavy vehicles: '.($d['heavy_vehicle_pct'] !== null ? $d['heavy_vehicle_pct'].' %' : 'N/A'),
            'Peak hour volume: '.($d['peak_hour_volume'] !== null ? number_format($d['peak_hour_volume']).' vph' : 'N/A'),
            'Peak hour start: '.($d['peak_hour_start'] ?? 'N/A'),
            'Count date: '.($d['count_date'] ?? 'N/A'),
            '',
            '— Speed environment —',
            'Posted speed limit: '.($d['speed_limit_kmh'] !== null ? $d['speed_limit_kmh'].' km/h' : 'N/A'),
            'NSLR zones at this point:',
            '  '.$zoneSummary,
            '',
            '— Safety history —',
            "Crashes within ~1 km, since {$sev['since_year']}: {$sev['count']}",
            "  fatal: {$sev['fatal']}, serious: {$sev['serious']}, minor: {$sev['minor']}, non-injury: {$sev['non_injury']}",
            '',
            '— Road controlling authority —',
            'RCA: '.($d['rca'] ?? 'Unknown — verify with NSLR / council'),
            '',
            '— Source —',
            'Data source: '.$d['source_name'],
            'Source URL: '.($d['source_url'] ?? ''),
            'RoadBase last synced: '.$d['synced_at'],
            '',
            'Reference: New Zealand guide to temporary traffic management (Waka Kotahi, April 2023, CC-BY-4.0).',
        ];
        return implode("\n", $lines);
    }

    private function staticMapUrl(float $lat, float $lng): ?string
    {
        $key = config('services.google.maps_server_key');
        if (! $key) {
            return null;
        }
        return 'https://maps.googleapis.com/maps/api/staticmap?'.http_build_query([
            'center' => "{$lat},{$lng}",
            'zoom' => 15,
            'size' => '600x300',
            'scale' => 2,
            'maptype' => 'roadmap',
            'markers' => "color:red|{$lat},{$lng}",
            'key' => $key,
        ]);
    }

    private function mobileRoadUrl(float $lat, float $lng): string
    {
        // Best-effort deep-link. Mobile Road's exact lat/lon URL contract isn't
        // documented in their wiki, so pair the link with a copyable lat/lon
        // in the export so users can paste manually if the deep-link doesn't
        // recognise the params.
        return 'https://mobileroad.org/?lat='.$lat.'&lon='.$lng;
    }
}
