<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Hits each council FeatureServer URL with a 1-record query and reports
 * what it found — feature count, geometry type, and a few key field names.
 * Lets you sanity-check pasted URLs before kicking off a full sync.
 */
class CouncilsProbeCommand extends Command
{
    protected $signature = 'councils:probe {key? : Optional council key to probe (defaults to all configured)}';

    protected $description = 'Probe council traffic-count FeatureServer URLs and report which ones return data.';

    public function handle(): int
    {
        $key = $this->argument('key');
        /** @var array<string, array<string, mixed>> $registry */
        $registry = (array) config('council_sources', []);

        if ($key !== null) {
            if (! isset($registry[$key])) {
                $this->error("Unknown council key: {$key}");
                return self::FAILURE;
            }
            $registry = [$key => $registry[$key]];
        }

        $configured = 0;
        $blank = 0;
        $ok = 0;
        $errored = 0;

        foreach ($registry as $k => $cfg) {
            $url = $cfg['url'] ?? null;
            $name = $cfg['name'] ?? $k;

            if (! $url) {
                $blank++;
                if ($key !== null) {
                    $this->line("  <fg=gray>[blank]</> {$k} — {$name}");
                }
                continue;
            }

            $configured++;
            $this->line("Probing <fg=cyan>{$k}</>: {$url}");

            try {
                $response = Http::timeout(20)
                    ->retry(2, 500, throw: false)
                    ->get(rtrim($url, '/').'/query', [
                        'where' => $cfg['where'] ?? '1=1',
                        'outFields' => '*',
                        'outSR' => 4326,
                        'f' => 'geojson',
                        'resultRecordCount' => 1,
                        'returnGeometry' => 'true',
                    ]);

                if ($response->failed()) {
                    $this->error("  HTTP {$response->status()} — {$response->reason()}");
                    $errored++;
                    continue;
                }

                $body = $response->json();
                if (isset($body['error'])) {
                    $msg = $body['error']['message'] ?? json_encode($body['error']);
                    $this->error("  ArcGIS error: {$msg}");
                    $errored++;
                    continue;
                }

                $features = $body['features'] ?? [];
                if (empty($features)) {
                    $this->warn('  Reachable but returned 0 features (maybe filtered out by WHERE).');
                    $ok++;
                    continue;
                }

                $first = $features[0];
                $geomType = $first['geometry']['type'] ?? 'no-geometry';
                $props = $first['properties'] ?? [];
                $resolved = $this->resolveAliases($cfg['fields'] ?? [], $props);

                $this->info("  OK — geometry={$geomType}, ".count($props).' props');
                if ($resolved) {
                    foreach ($resolved as $col => [$alias, $value]) {
                        $shown = is_scalar($value) ? (string) $value : json_encode($value);
                        $this->line("    <fg=green>{$col}</>  via <fg=yellow>{$alias}</>  = {$shown}");
                    }
                } else {
                    $this->warn('  No mapped fields resolved — check config/council_sources.php aliases.');
                }

                // When debugging a single source, dump every raw property so
                // you can read off the upstream field names and add the
                // missing ones to the alias list. Skipped in bulk mode to
                // keep the all-councils run readable.
                if ($key !== null) {
                    $this->newLine();
                    $this->line('  <fg=cyan>All upstream fields (sample row):</>');
                    foreach ($props as $name => $value) {
                        $shown = $value === null ? '<null>'
                            : (is_scalar($value) ? (string) $value : json_encode($value));
                        if (mb_strlen($shown) > 80) {
                            $shown = mb_substr($shown, 0, 77).'…';
                        }
                        $this->line(sprintf('    %-32s = %s', $name, $shown));
                    }
                }
                $ok++;
            } catch (\Throwable $e) {
                $this->error('  Exception: '.$e->getMessage());
                $errored++;
            }
        }

        $this->newLine();
        $this->line(sprintf(
            "Summary: <fg=green>%d ok</>, <fg=red>%d errored</>, <fg=gray>%d blank</> (of %d registered).",
            $ok, $errored, $blank, $configured + $blank,
        ));

        return $errored > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * For each column → alias-list mapping, return the first alias whose
     * value resolves to non-null, plus the value itself.
     *
     * @param  array<string, list<string>>  $fields
     * @param  array<string, mixed>  $props
     * @return array<string, array{0:string, 1:mixed}>
     */
    private function resolveAliases(array $fields, array $props): array
    {
        $out = [];
        foreach ($fields as $col => $aliases) {
            foreach ((array) $aliases as $alias) {
                if (array_key_exists($alias, $props) && $props[$alias] !== null && $props[$alias] !== '') {
                    $out[$col] = [$alias, $props[$alias]];
                    break;
                }
            }
        }
        return $out;
    }
}
