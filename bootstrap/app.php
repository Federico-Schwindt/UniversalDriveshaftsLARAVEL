<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['stripe/webhook', 'webhooks/shipstation']);
        $middleware->alias(['admin.section' => \App\Http\Middleware\AdminSection::class,
            'admin' => \App\Http\Middleware\AdminMiddleware::class]);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // the cookie is the visitor's own language choice, not a secret: leave it unencrypted
        $middleware->encryptCookies(except: ['uds_lang']);
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class]);
        // PTI photos cleaned in place get a fresh URL (?v=mtime) so year-long browser caches pick them up
        $middleware->web(append: [\App\Http\Middleware\VersionEditedImages::class]);
        // a password change signs out every other session and remember-me cookie (Auth::logoutOtherDevices)
        $middleware->web(append: [\Illuminate\Session\Middleware\AuthenticateSession::class]);
        // signed-in customers' pages / actions / errors, for the admin chat page (after the response is sent)
        $middleware->web(append: [\App\Http\Middleware\TrackCustomerActivity::class]);
        $middleware->append(\App\Http\Middleware\NoIndexApi::class);
        $middleware->prepend(\App\Http\Middleware\CanonicalPath::class);
        // old URLs that now answer 404 follow the redirects in seo_redirects (admin → SEO)
        $middleware->append(\App\Http\Middleware\SeoRedirects::class);
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
