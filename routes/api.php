<?php

use App\Http\Controllers\LayerController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SourceController;
use App\Http\Controllers\TmpExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/layers/sites', [LayerController::class, 'sites']);
    Route::get('/layers/segments', [LayerController::class, 'segments']);
    Route::get('/layers/crashes', [LayerController::class, 'crashes']);

    Route::get('/sites/{site}', [SiteController::class, 'show']);
    Route::get('/sites/{site}/tmp.txt', [TmpExportController::class, 'text']);
    Route::get('/sites/{site}/tmp.pdf', [TmpExportController::class, 'pdf']);

    Route::get('/sources', [SourceController::class, 'index']);
    Route::get('/search', [SearchController::class, 'index']);
});
