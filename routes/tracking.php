<?php

// tracking feature routes (owned by the tracking agent). Loaded from routes/web.php.

use Illuminate\Support\Facades\Route;

// Customer order status timeline. ?e=<checkout e-mail> (or a signed URL, or the
// signed-in owner) is required: the order number alone must not expose an address.
Route::get('/orders/{order_number}/status', [\App\Http\Controllers\OrderStatusController::class, 'show'])
    ->where('order_number', '[A-Za-z0-9\-]+')
    ->middleware('throttle:60,1')
    ->name('orders.status');

// the customer's invoice PDF, same access rule as the status page
Route::get('/orders/{order_number}/invoice', [\App\Http\Controllers\OrderStatusController::class, 'invoice'])
    ->where('order_number', '[A-Za-z0-9\-]+')
    ->middleware('throttle:20,1')
    ->name('orders.invoice');
