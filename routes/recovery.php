<?php

// recovery feature routes (owned by the recovery agent). Loaded from routes/web.php.

use App\Http\Controllers\AlertsController;
use App\Http\Controllers\RecoveryController;
use Illuminate\Support\Facades\Route;

// abandoned-cart recovery
Route::post('/cart/recovery/capture', [RecoveryController::class, 'capture'])->middleware('throttle:lookup');
Route::get('/cart/restore/{token}', [RecoveryController::class, 'restore'])->where('token', '[A-Za-z0-9]{20,64}');
// GET = footer link, POST = one-click unsubscribe from the mail client (List-Unsubscribe-Post): a mail client can't send a CSRF token; the 40-char token is the credential
Route::match(['get', 'post'], '/cart/recovery/unsubscribe/{token}', [RecoveryController::class, 'unsubscribe'])->where('token', '[A-Za-z0-9]{20,64}')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

// Google review request opt-out (signed token, App\Support\ReviewRequests::unsubscribeUrl)
Route::get('/reviews/unsubscribe/{token}', function (string $token) {
    $email = \App\Support\ReviewRequests::emailFromToken($token);
    abort_unless($email, 404);
    \App\Support\ReviewRequests::optOut($email);
    return response(\Illuminate\Support\Facades\Blade::render(<<<'BLADE'
@extends('layouts.app')
@section('title', __('Review requests stopped | Universal Driveshafts'))
@section('robots', 'noindex,nofollow')
@section('content')
<section class="section"><div class="wrap rcv-page"><div class="aempty"><h1 style="font-size:1.4rem">{{ __('Done. No more review requests.') }}</h1><p>{{ __('We will not ask :email for reviews again. Order and shipping e-mails still arrive as usual.', ['email' => $email]) }}</p><a class="btn btn-primary" href="/">{{ __('Back to the store') }}</a></div></div></section>
@endsection
BLADE, ['email' => $email]));
})->where('token', '[A-Za-z0-9_-]{40,400}')->middleware('throttle:forms');

// newsletter opt-out from the welcome e-mail (signed token, see NewsletterController::unsubscribeUrl)
Route::match(['get', 'post'], '/newsletter/unsubscribe/{token}', [\App\Http\Controllers\NewsletterController::class, 'unsubscribe'])->where('token', '[a-f0-9]{32}[A-Za-z0-9_-]{4,300}')
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)->middleware('throttle:forms');

// back-in-stock + quote capture
Route::post('/alerts/subscribe', [AlertsController::class, 'subscribe'])->middleware('throttle:forms');
Route::post('/alerts/quote', [AlertsController::class, 'quote'])->middleware('throttle:forms');

