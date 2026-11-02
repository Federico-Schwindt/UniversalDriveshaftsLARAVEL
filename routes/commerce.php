<?php

// commerce feature routes (owned by the commerce agent). Loaded from routes/web.php.

use Illuminate\Support\Facades\Route;

// Buy again (purchase history from this shop + the WooCommerce history) — same auth guard as the other /account pages
Route::middleware('auth')->group(function () {
    Route::get('/account/buy-again', [\App\Http\Controllers\CommerceController::class, 'buyAgain']);
    Route::post('/account/buy-again/reorder', [\App\Http\Controllers\CommerceController::class, 'reorder'])->middleware('throttle:forms');
});

// Product-page shipping estimator: live UPS options for one line to a US ZIP
Route::post('/shop/estimate', [\App\Http\Controllers\CommerceController::class, 'estimate'])->middleware('throttle:lookup');
