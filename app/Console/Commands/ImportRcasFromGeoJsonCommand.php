<?php

namespace App\Console\Commands;

use App\Models\DataSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot bulk import of Stats NZ Territorial Authority polygons from a
 * single static GeoJSON file. Faster than the paginated ArcGIS REST sync
 * because it bypasses server-side reprojection retries and downloads one
 * cached file.
 *
 * Get the file from any of these (whichever your network reaches first):
 *   curl -L -o ta.geojson "https://hub.arcgis.com/datasets/82b6052534854a8abe3870a08d497d3f_0.geojson"
 *   curl -L -o ta.geojson "https://opendata.arcgis.com/api/v3/datasets/82b6052534854a8abe3870a08d497d3f_0/downloads/data?format=geojson&spatialRefId=4326"
 *
 * Then: php artisan rcas:import-geojson ta.geojson
 */
class ImportRcasFromGeoJsonCommand extends Command
{
    protected $signature = 'rcas:import-geojson {file : Path to TA polygons GeoJSON file}';

    protected $description = 'Bulk-load RCA polygons from a Stats NZ TA GeoJSON file.';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        $source = DataSource::where('key', 'stats_nz_ta')->first();
        if (! $source) {
            $this->error('DataSource stats_nz_ta missing — run db:seed first.');
            return self::FAILURE;
        }

        $raw = file_get_contents($path);
        $fc = json_decode($raw, true);
        if (! is_array($fc) || ($fc['type'] ?? '') !== 'FeatureCollection') {
            $this->error('Not a GeoJSON FeatureCollection.');
            return self::FAILURE;
        }

        $features = $fc['features'] ?? [];
        $this->info("Loading {$source->name}: ".count($features).' features.');

        $upserted = 0;
        $failed = 0;
        $bar = $this->output->createProgressBar(count($features));

        foreach ($features as $feature) {
            try {
                $props = $feature['properties'] ?? [];
                $geom = $feature['geometry'] ?? null;

                if (! $geom || ! in_array($geom['type'] ?? '', ['Polygon', 'MultiPolygon'], true)) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $code = $this->firstString($props, [
                    'TA2026_V1_00', 'TA2025_V1_00', 'TA2023_V1_00', 'TA_CODE', 'code',
                ]);
                $name = $this->firstString($props, [
                    'TA2026_V1_00_NAME', 'TA2025_V1_00_NAME', 'TA2023_V1_00_NAME', 'TA_NAME', 'name',
                ]);
                $externalId = (string) ($code ?? $props['OBJECTID'] ?? '');

                if ($externalId === '' || $name === null) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                if (($geom['type'] ?? '') === 'Polygon') {
                    $geom = ['type' => 'MultiPolygon', 'coordinates' => [$geom['coordinates']]];
                }

                DB::statement(
                    'INSERT INTO rcas (data_source_id, external_id, code, name, kind, raw_payload, synced_at, created_at, updated_at, geom)
                     VALUES (?, ?, ?, ?, "territorial_authority", ?, NOW(), NOW(), NOW(), ST_GeomFromGeoJSON(?, 1, 4326))
                     ON DUPLICATE KEY UPDATE
                         code = VALUES(code),
                         name = VALUES(name),
                         raw_payload = VALUES(raw_payload),
                         synced_at = NOW(),
                         updated_at = NOW(),
                         geom = VALUES(geom)',
                    [
                        $source->id, $externalId, $code, $name,
                        json_encode($props, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        json_encode($geom),
                    ],
                );
                $upserted++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("Feature failed: {$e->getMessage()}");
                $failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Upserted: {$upserted}, failed: {$failed}");

        $source->update([
            'last_synced_at' => now(),
            'last_sync_status' => $failed === 0 ? 'success' : 'partial',
        ]);

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $props @param array<int, string> $keys */
    private function firstString(array $props, array $keys): ?string
    {
        foreach ($keys as $k) {
            $v = $props[$k] ?? null;
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }
        return null;
    }
}
