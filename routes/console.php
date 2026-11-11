<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
// Inventory automation (requires: * * * * * php artisan schedule:run). Times are Eastern: config/app.php schedule_timezone.
// withoutOverlapping(minutes): the lock expires near the job's interval so a crashed run can't block it for the default 24 h
Schedule::command('inventory:low-stock-email')->dailyAt('07:00');
Schedule::command('inventory:weekly-report')->weeklyOn(1, '07:30'); // Monday morning
// Live catalog overlay (images, prices, stock, names from universaldriveshafts.com) until catalog:detach makes the catalog local for good
Schedule::command('catalog:sync-live')->hourly()->withoutOverlapping(90)->skip(fn () => \App\Support\LiveOrigin::retired());
// pictures the sync brought in are copied to public/uploads/wp so pages never load them from the old WordPress site
Schedule::command('catalog:localize-images')->hourlyAt(40)->withoutOverlapping(50)->skip(fn () => \App\Support\LiveOrigin::retired());
// 404 log (admin → SEO): forget one-off misses after 90 days
Schedule::command('reviews:request')->dailyAt('10:00')->timezone('America/New_York')->withoutOverlapping(30);   // Google review requests (off until enabled in Settings → Reviews)
Schedule::command('seo:indexnow')->dailyAt('05:15')->withoutOverlapping(30);   // changed pages to Bing / ChatGPT search (production only)
Schedule::command('analytics:geocode')->everyThirtyMinutes()->withoutOverlapping(25);   // coordinates of new Google Analytics cities for the dashboard globe
Schedule::command('seo:gsc-sync')->dailyAt('06:30')->withoutOverlapping(60);   // Search Console → Admin → SEO → Performance (no key = no-op)
Schedule::call(fn () => \App\Support\CustomerActivity::prune())->name('customer-activity-prune')->dailyAt('04:20');   // admin chat page trail, 30 days
Schedule::call(fn () => \Illuminate\Support\Facades\DB::table('seo_404s')->where('last_seen_at', '<', now()->subDays(90))->where('hits', '<', 5)->delete())->name('seo-404-prune')->dailyAt('04:10');
// Powertrain Industries: new parts arrive as drafts with PTI photos, stock 4x a day, weight + box measured overnight while nobody orders at PTI
Schedule::command('pti:sync --catalog --create --images --stock')->dailyAt('03:30')->withoutOverlapping(180)
    ->then(fn () => \Illuminate\Support\Facades\Artisan::call('pti:dewatermark', ['--set' => 'parts']));   // new parts go live: clean their photos at once
Schedule::command('pti:sync --stock')->cron('30 9,15,21 * * *')->withoutOverlapping(180);
// boxes fill PTI's real cart: 3 am ET when nobody builds orders there (was 01:30 UTC = 9:30 pm ET); Cache::lock('pti-cart') guards dropships too
Schedule::command('pti:sync --boxes --apply-boxes')->dailyAt('03:00')->withoutOverlapping(180);
Schedule::command('pti:driveshaft-prices')->cron('15 */4 * * *')->withoutOverlapping(230);   // complete driveshafts every 4 h: PTI discounted price + make/model amount; dropped/unpriced shafts hidden, returning ones shown
// new PTI photos (parts from the 03:30 sync, shafts from the 4-hourly discovery) lose the PTI watermark (template subtraction, no AI)
Schedule::command('pti:dewatermark')->cron('20 1-21/4 * * *')->withoutOverlapping(200);
Schedule::command('pti:driveshaft-discover')->cron('45 */4 * * *')->withoutOverlapping(120);   // new shafts PTI added (full catalog scan, ~25 min)
Schedule::command('pti:dropship-tracking')->everyFifteenMinutes()->withoutOverlapping(30);   // tracking of orders dropshipped from PTI (new labels too)
Schedule::command('checkin:push')->everyTenMinutes()->withoutOverlapping(20)->skip(fn () => !\App\Services\CheckinBridge::enabled());   // builder shafts → shop station (retries), and its build progress → the customer
require __DIR__ . '/console-recovery.php';   // schedules owned by the recovery agent
require __DIR__ . '/console-tracking.php';   // schedules owned by the tracking agent
require __DIR__ . '/console-chat.php';       // UDS Assistant chat (media pruning)
Schedule::command('b2b:tiers')->dailyAt('00:10')->timezone('America/New_York')->withoutOverlapping(60);   // B2B tiers: drop on the 1st when last month fell short
Schedule::command('b2b:terms-reminders')->dailyAt('08:30')->timezone('America/New_York')->withoutOverlapping(60);   // Net terms invoices due soon / overdue
Schedule::command('chatbot:knowledge')->dailyAt('04:10')->withoutOverlapping(120);

// Online builder reference prices (supplier Action Machine, public product JSON): weekly, Sunday 04:10 Eastern
Schedule::command('builder:sync-prices')->weeklyOn(0, '04:10')->withoutOverlapping(120);
// the supplier's vehicle tags on flange yokes, for the builder's "which make?" step (App\Support\YokeMakes)
Schedule::command('builder:sync-am-yokes')->weeklyOn(0, '04:40')->withoutOverlapping(30);
