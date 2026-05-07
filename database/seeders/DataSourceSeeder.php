<?php

namespace Database\Seeders;

use App\Models\DataSource;
use Illuminate\Database\Seeder;

class DataSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'key' => 'nzta_tms',
                'name' => 'NZTA TMS Daily Traffic Counts',
                'url' => 'https://opendata-nzta.opendata.arcgis.com/datasets/NZTA::tms-daily-traffic-counts-api',
                'update_frequency_hours' => 24,
            ],
            [
                'key' => 'nzta_aadt',
                'name' => 'NZTA State Highway AADT',
                'url' => 'https://opendata-nzta.opendata.arcgis.com',
                'update_frequency_hours' => 24 * 7,
            ],
            [
                'key' => 'nslr',
                'name' => 'National Speed Limit Register',
                'url' => 'https://services.arcgis.com/CXBb7LAjgIIdcsPt/arcgis/rest/services/SpeedLimitZoneFull__View/FeatureServer',
                'update_frequency_hours' => 24,
            ],
            [
                'key' => 'cas',
                'name' => 'NZTA Crash Analysis System',
                'url' => 'https://opendata-nzta.opendata.arcgis.com/datasets/NZTA::crash-analysis-system-cas-data-1',
                'update_frequency_hours' => 24 * 30,
            ],
            [
                'key' => 'stats_nz_ta',
                'name' => 'Stats NZ Territorial Authority (current vintage)',
                'url' => 'https://services2.arcgis.com/vKb0s8tBIA3bdocZ/arcgis/rest/services/Territorial_Authority_2026/FeatureServer/0',
                'update_frequency_hours' => 24 * 90,
            ],
            [
                'key' => 'nz_roads_centrelines',
                'name' => 'NZ Roads Centrelines (NZTA, all RCAs)',
                'url' => 'https://nzta.opendata.arcgis.com/',
                'update_frequency_hours' => 24 * 30,
            ],
        ];

        foreach ($sources as $source) {
            DataSource::updateOrCreate(['key' => $source['key']], $source);
        }

        // Council / district-council traffic-count sources are registered from
        // config/council_sources.php so the registry stays in one place. The
        // sync no-ops for any RCA whose FeatureServer URL isn't set in .env,
        // but the DataSource row still appears on /about as a known feed.
        foreach (config('council_sources', []) as $key => $cfg) {
            DataSource::updateOrCreate(
                ['key' => $key],
                [
                    'key' => $key,
                    'name' => $cfg['name'] ?? $key,
                    'url' => $cfg['url'] ?? null,
                    'update_frequency_hours' => 24 * 30,
                ],
            );
        }
    }
}
