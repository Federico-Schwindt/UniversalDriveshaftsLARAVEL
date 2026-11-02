<?php

// content routes (owned by the content SEO agent). Loaded from routes/web.php.

use App\Http\Controllers\GuideController;
use Illuminate\Support\Facades\Route;

Route::get('/guides', [GuideController::class, 'index']);
Route::get('/guides/{slug}', [GuideController::class, 'show'])->where('slug', '[a-z0-9-]+');
