<?php

// Google Merchant Center feeds (see FeedController). Loaded from routes/web.php.
use Illuminate\Support\Facades\Route;

Route::get('/feeds/google-merchant.xml', [\App\Http\Controllers\FeedController::class, 'products']);
Route::get('/feeds/google-merchant-driveshafts.xml', [\App\Http\Controllers\FeedController::class, 'driveshafts']);
