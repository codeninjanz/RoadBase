<?php

use App\Models\DataSource;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Map'))->name('map');

Route::get('/about', function () {
    return Inertia::render('About', [
        'sources' => DataSource::query()
            ->orderBy('name')
            ->get(['key', 'name', 'url', 'update_frequency_hours', 'last_synced_at', 'last_sync_status']),
    ]);
})->name('about');
