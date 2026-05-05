<?php

namespace App\Http\Controllers;

use App\Support\Nzgttm;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class TmpExportController extends Controller
{
    public function text(int $site): HttpResponse
    {
        $data = $this->fetch($site);
        if (! $data) {
            return response('Not found', 404);
        }

        $body = $this->plainText($data);
        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="tmp-summary-'.$site.'.txt"',
        ]);
    }

    public function pdf(int $site): HttpResponse
    {
        $data = $this->fetch($site);
        if (! $data) {
            return response('Not found', 404);
        }

        $pdf = Pdf::loadView('tmp.summary', [
            'site' => $data,
            'levelDescription' => $data['nzgttm_level'] ? Nzgttm::description($data['nzgttm_level']) : null,
            'staticMapUrl' => $this->staticMapUrl($data['lat'], $data['lng']),
            'generatedAt' => now()->toDayDateTimeString(),
        ]);

        return $pdf->download("tmp-summary-{$site}.pdf");
    }

    private function fetch(int $site): ?array
    {
        $row = DB::selectOne(<<<'SQL'
            SELECT
                cs.id, cs.road_name, cs.region, cs.aadt, cs.heavy_vehicle_pct,
                cs.peak_hour_volume, cs.peak_hour_start, cs.count_date,
                cs.speed_limit_kmh, cs.nzgttm_level, cs.synced_at,
                ST_X(cs.location) AS lng, ST_Y(cs.location) AS lat,
                ds.name AS source_name, ds.url AS source_url
            FROM count_sites cs
            JOIN data_sources ds ON ds.id = cs.data_source_id
            WHERE cs.id = ?
        SQL, [$site]);

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'road_name' => $row->road_name,
            'region' => $row->region,
            'lat' => (float) $row->lat,
            'lng' => (float) $row->lng,
            'aadt' => $row->aadt !== null ? (int) $row->aadt : null,
            'heavy_vehicle_pct' => $row->heavy_vehicle_pct !== null ? (float) $row->heavy_vehicle_pct : null,
            'peak_hour_volume' => $row->peak_hour_volume !== null ? (int) $row->peak_hour_volume : null,
            'peak_hour_start' => $row->peak_hour_start,
            'count_date' => $row->count_date,
            'speed_limit_kmh' => $row->speed_limit_kmh !== null ? (int) $row->speed_limit_kmh : null,
            'nzgttm_level' => $row->nzgttm_level,
            'synced_at' => $row->synced_at,
            'source_name' => $row->source_name,
            'source_url' => $row->source_url,
        ];
    }

    private function plainText(array $d): string
    {
        $lines = [
            'Traffic Volume Summary — for inclusion in TMP under NZGTTM',
            '',
            'Road: '.($d['road_name'] ?? 'Unknown'),
            'Location: '.number_format($d['lat'], 6).', '.number_format($d['lng'], 6),
            'AADT: '.($d['aadt'] !== null ? number_format($d['aadt']).' vpd' : 'N/A'),
            'NZGTTM Road Level: '.($d['nzgttm_level'] ?? 'N/A'),
            'Speed limit: '.($d['speed_limit_kmh'] !== null ? $d['speed_limit_kmh'].' km/h' : 'N/A'),
            'Heavy vehicles: '.($d['heavy_vehicle_pct'] !== null ? $d['heavy_vehicle_pct'].' %' : 'N/A'),
            'Peak hour volume: '.($d['peak_hour_volume'] !== null ? number_format($d['peak_hour_volume']).' vph' : 'N/A'),
            'Peak hour start: '.($d['peak_hour_start'] ?? 'N/A'),
            'Count date: '.($d['count_date'] ?? 'N/A'),
            '',
            'Source: '.$d['source_name'],
            'Source URL: '.($d['source_url'] ?? ''),
            'RoadBase last synced: '.$d['synced_at'],
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
}
