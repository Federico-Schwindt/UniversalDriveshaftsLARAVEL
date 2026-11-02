<?php

// schedules for the tracking feature. Loaded from routes/console.php.

use Illuminate\Support\Facades\Schedule;

// Refresh carrier status for shipped-but-undelivered orders (last 30 days) from EasyPost.
Schedule::command('orders:track')->everyThirtyMinutes()->withoutOverlapping(30);
