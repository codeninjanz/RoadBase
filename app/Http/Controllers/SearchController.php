<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim($request->string('q')->toString());

        if ($q === '') {
            return response()->json(['sites' => [], 'places' => []]);
        }

        $sites = DB::select(<<<'SQL'
            SELECT id, road_name, ST_Longitude(location) AS lng, ST_Latitude(location) AS lat
            FROM count_sites
            WHERE road_name LIKE ?
            ORDER BY road_name ASC
            LIMIT 20
        SQL, ['%'.$q.'%']);

        $places = $this->autocompletePlaces($q);

        return response()->json([
            'sites' => array_map(fn ($r) => [
                'id' => (int) $r->id,
                'road_name' => $r->road_name,
                'lat' => (float) $r->lat,
                'lng' => (float) $r->lng,
            ], $sites),
            'places' => $places,
        ]);
    }

    /** @return array<int, array{description:string, place_id:string}> */
    private function autocompletePlaces(string $q): array
    {
        $key = config('services.google.places_key');
        if (! $key) {
            return [];
        }

        $resp = Http::timeout(5)->get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input' => $q,
            'key' => $key,
            'components' => 'country:nz',
        ]);

        if ($resp->failed()) {
            return [];
        }

        return array_map(
            fn ($p) => ['description' => $p['description'], 'place_id' => $p['place_id']],
            $resp->json('predictions') ?? []
        );
    }
}
