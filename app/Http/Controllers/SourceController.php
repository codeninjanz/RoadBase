<?php

namespace App\Http\Controllers;

use App\Models\DataSource;
use Illuminate\Http\JsonResponse;

class SourceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            DataSource::query()
                ->orderBy('name')
                ->get(['key', 'name', 'url', 'update_frequency_hours', 'last_synced_at', 'last_sync_status'])
        );
    }
}
