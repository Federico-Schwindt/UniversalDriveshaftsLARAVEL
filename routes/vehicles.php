<?php

// vehicles routes (owned by the vehicles SEO agent). Loaded from routes/web.php.
// Indexable vehicle landing pages built from the finder data (ds_fitments / ds_parts).

use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/driveshafts', [VehicleController::class, 'hub']);
Route::get('/driveshafts/{make}', [VehicleController::class, 'make'])->where('make', '[a-z0-9-]+');
Route::get('/driveshafts/{make}/{model}', [VehicleController::class, 'model'])->where(['make' => '[a-z0-9-]+', 'model' => '[a-z0-9-]+']);
