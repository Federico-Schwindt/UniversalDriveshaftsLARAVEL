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
// delivery date + location → ZIP: background calls with no session at all, so they can never write back a stale session
// over an Add to cart made while they run (the cart vanished that way in testing, 2026-11-11)
Route::get('/api/arrival', [\App\Http\Controllers\CommerceController::class, 'arrival'])->middleware('throttle:lookup')->withoutMiddleware(\App\Http\Controllers\CommerceController::STATELESS);
Route::get('/api/zip-near', [\App\Http\Controllers\CommerceController::class, 'zipNear'])->middleware('throttle:lookup')->withoutMiddleware(\App\Http\Controllers\CommerceController::STATELESS);
