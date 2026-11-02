<?php

// schedules for the recovery feature. Loaded from routes/console.php.

use Illuminate\Support\Facades\Schedule;

Schedule::command('recovery:send')->everyFifteenMinutes()->withoutOverlapping(30);   // cart reminders at 1 h and 24 h
Schedule::command('alerts:check')->hourly()->withoutOverlapping(90);                 // back-in-stock notices
