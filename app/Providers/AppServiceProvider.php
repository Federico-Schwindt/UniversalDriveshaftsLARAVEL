<?php

namespace App\Providers;

use App\Services\CatalogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // the catalog's `wp` connection runs on mysql's session when both point at the same database (one connection a request)
        $this->app->afterResolving('db', fn ($db) => \App\Support\SharedWpConnection::install($db));
    }

    public function boot(): void
    {
        // admin-edited settings override the static config values
        \App\Support\Settings::applyToConfig();
        // before launch staging holds real imported customers: mail only reaches the shop's own addresses
        \Illuminate\Support\Facades\Event::listen(fn (\Illuminate\Mail\Events\MessageSending $e) => \App\Support\MailGuard::filter($e));
        \Illuminate\Support\Facades\View::composer('admin.layout', fn ($v) => $v->with('counts', \App\Http\Controllers\Admin\DashboardController::navCounts()));
        // brute-force and abuse limits (per e-mail+IP for login, per IP for the rest)
        // Spanish strings live in one es.json per area (lang/i18n/<area>/es.json) so the
        // storefront can be translated in pieces without every edit fighting over one file
        foreach (glob(lang_path('i18n/*'), GLOB_ONLYDIR) ?: [] as $dir) app('translator')->addJsonPath($dir);

        // plus an hourly cap per address alone, so rotating IPs can't keep guessing one account's password
        \Illuminate\Support\Facades\RateLimiter::for('login', fn ($r) => [
            \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by(strtolower((string) $r->input('email')) . '|' . $r->ip()),
            \Illuminate\Cache\RateLimiting\Limit::perHour(30)->by('h|' . strtolower(trim((string) $r->input('email')))),
            // and per IP alone, so one address can't walk the customer list a few passwords at a time
            \Illuminate\Cache\RateLimiting\Limit::perMinute(20)->by('ip|' . $r->ip()),
        ]);
        \Illuminate\Support\Facades\RateLimiter::for('register', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($r->ip()));
        \Illuminate\Support\Facades\RateLimiter::for('forms', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(10)->by($r->ip()));
        // read-only lookups (shipping estimate, cart capture) get a roomier bucket than the contact forms
        // the store assistant: per signed-in account, never per IP (a shop's office shares one)
        \Illuminate\Support\Facades\RateLimiter::for('chatbot', fn ($r) => optional($r->user())->is_admin ? \Illuminate\Cache\RateLimiting\Limit::none() : \Illuminate\Cache\RateLimiting\Limit::perMinute((int) config('services.anthropic.per_minute', 8))->by('u' . optional($r->user())->id));
        \Illuminate\Support\Facades\RateLimiter::for('lookup', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($r->ip()));
        // catalog search + finder APIs: each endpoint its own 60/min bucket per IP (the overlay debounces typing)
        \Illuminate\Support\Facades\RateLimiter::for('search', fn ($r) => \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by($r->ip() . '|' . (optional($r->route())->uri() ?? $r->path())));

        // the branded template, in the customer's language (their account's, else the page they asked from).
        // A Mailable returned from toMail is sent as is, so ->locale() also covers the template chrome.
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $email = $notifiable->getEmailForPasswordReset();
            // from APP_URL, never the request's Host header (a spoofed Host would otherwise receive the token)
            $url = rtrim((string) config('app.url'), '/') . route('password.reset', ['token' => $token, 'email' => $email], false);
            $loc = \App\Support\MailLocale::valid(($notifiable->locale ?? null) ?: app()->getLocale());
            $t = fn (string $s, array $rep = []) => __($s, $rep, $loc);
            $subject = $t('Reset your Universal Driveshafts password');
            $intro = $t('Hi :name, someone asked to reset the password for this e-mail address at universaldriveshafts.com. If that was you, use the button below. The link works for 60 minutes.', ['name' => $notifiable->name ?: $t('there')]);
            return (new \App\Mail\Branded($subject, 'emails.generic', [
                'title' => $subject, 'hero' => ['kicker' => $t('Account security'), 'title' => $t('Choose a new password'), 'text' => $intro], 'preheader' => $intro,
                'button' => [$t('Choose a new password'), $url],
                'after' => [$t('If you did not ask for this, ignore this e-mail. Your password stays as it is.')], 'signoff' => false,
            ]))->locale($loc)->to($email, $notifiable->name ?: null);
        });

        // Catalogue totals are shown in the header menu, the home page and the
        // footer. Share them from one cached source so the numbers on the site
        // can never drift from the catalogue itself. once(): '*' runs for every
        // view and component (each icon), and a category page read the cache file
        // 176 times.
        View::composer('*', function ($view) {
            $view->with('udsCatalog', once(fn () => Cache::remember('uds_catalog_totals', 3600, function () {
                try {
                    $groups = app(CatalogService::class)->groups();
                    return [
                        'parts'  => array_sum(array_column($groups, 'count')),
                        'cats'   => array_sum(array_column($groups, 'cat_count')),
                        'groups' => $groups,
                    ];
                } catch (\Throwable $e) {
                    return ['parts' => 0, 'cats' => 0, 'groups' => []];
                }
            })));

        });
    }
}
