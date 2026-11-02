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
// Live catalog overlay (images, prices, stock, names from universaldriveshafts.com): the WP copy is a snapshot
Schedule::command('catalog:sync-live')->hourly()->withoutOverlapping(90);
// Powertrain Industries: new parts arrive as drafts with PTI photos, stock 4x a day, weight + box measured overnight while nobody orders at PTI
Schedule::command('pti:sync --catalog --create --images --stock')->dailyAt('03:30')->withoutOverlapping(180);
Schedule::command('pti:sync --stock')->cron('30 9,15,21 * * *')->withoutOverlapping(180);
// boxes fill PTI's real cart: 3 am ET when nobody builds orders there (was 01:30 UTC = 9:30 pm ET); Cache::lock('pti-cart') guards dropships too
Schedule::command('pti:sync --boxes --apply-boxes')->dailyAt('03:00')->withoutOverlapping(180);
Schedule::command('pti:driveshaft-prices')->cron('15 */4 * * *')->withoutOverlapping(230);   // complete driveshafts every 4 h: PTI discounted price + make/model amount; dropped/unpriced shafts hidden, returning ones shown
Schedule::command('pti:driveshaft-discover')->cron('45 */4 * * *')->withoutOverlapping(120);   // new shafts PTI added (full catalog scan, ~25 min)
Schedule::command('pti:dropship-tracking')->hourlyAt(20)->withoutOverlapping(90);   // tracking of orders dropshipped from PTI
require __DIR__ . '/console-recovery.php';   // schedules owned by the recovery agent
require __DIR__ . '/console-tracking.php';   // schedules owned by the tracking agent
require __DIR__ . '/console-chat.php';       // UDS Assistant chat (media pruning)
Schedule::command('b2b:tiers')->dailyAt('00:10')->timezone('America/New_York')->withoutOverlapping(60);   // B2B tiers: drop on the 1st when last month fell short
Schedule::command('b2b:terms-reminders')->dailyAt('08:30')->timezone('America/New_York')->withoutOverlapping(60);   // Net terms invoices due soon / overdue
Schedule::command('chatbot:knowledge')->dailyAt('04:10')->withoutOverlapping(120);
