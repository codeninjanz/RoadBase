<?php

namespace App\Sync;

use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pages through an ArcGIS REST FeatureServer/MapServer layer query endpoint
 * and yields raw feature dicts.
 */
class ArcgisFeatureClient
{
    public function __construct(
        public readonly string $featureUrl,
        public readonly int $pageSize = 1000,
        public readonly string $where = '1=1',
        public readonly string $outFields = '*',
        public readonly int $outSR = 4326,
    ) {}

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function features(): Generator
    {
        $offset = 0;

        while (true) {
            $response = Http::timeout(60)
                ->retry(3, 1500, throw: false)
                ->get(rtrim($this->featureUrl, '/').'/query', [
                    'where' => $this->where,
                    'outFields' => $this->outFields,
                    'outSR' => $this->outSR,
                    'f' => 'json',
                    'resultOffset' => $offset,
                    'resultRecordCount' => $this->pageSize,
                    'returnGeometry' => 'true',
                ]);

            if ($response->failed()) {
                throw new RuntimeException(
                    "ArcGIS query failed at offset {$offset}: HTTP {$response->status()}"
                );
            }

            $body = $response->json();

            if (isset($body['error'])) {
                throw new RuntimeException(
                    'ArcGIS error: '.($body['error']['message'] ?? json_encode($body['error']))
                );
            }

            $features = $body['features'] ?? [];

            if (empty($features)) {
                return;
            }

            foreach ($features as $feature) {
                yield $feature;
            }

            if (count($features) < $this->pageSize && empty($body['exceededTransferLimit'])) {
                return;
            }

            $offset += count($features);
        }
    }
}
