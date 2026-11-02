<?php

// series routes (owned by the series SEO agent). Loaded from routes/web.php.

use App\Http\Controllers\SeriesController;
use Illuminate\Support\Facades\Route;

Route::get('/series', [SeriesController::class, 'index']);
Route::get('/series/{series}', [SeriesController::class, 'show'])->where('series', '[A-Za-z0-9.\-]+');
