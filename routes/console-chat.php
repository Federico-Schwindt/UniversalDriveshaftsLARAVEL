<?php

// schedules for the UDS Assistant chat. Loaded from routes/console.php.

use Illuminate\Support\Facades\Schedule;

// privacy: voice recordings, spoken answers and photos from chats are deleted after 90 days (the text stays)
Schedule::command('chat:prune-media')->dailyAt('04:40')->withoutOverlapping();
