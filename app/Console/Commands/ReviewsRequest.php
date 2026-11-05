<?php

namespace App\Console\Commands;

use App\Support\ReviewRequests;
use Illuminate\Console\Command;

/** Daily: ask customers whose order arrived a few days ago for a Google review (App\Support\ReviewRequests). */
class ReviewsRequest extends Command
{
    protected $signature = 'reviews:request {--dry : list who would get one, send nothing} {--limit=50}';
    protected $description = 'E-mail Google review requests for recently delivered orders';

    public function handle(): int
    {
        if (!ReviewRequests::enabled() && !$this->option('dry')) { $this->line('Review requests are off (Admin → Settings → Reviews).'); return self::SUCCESS; }
        $due = ReviewRequests::due((int) $this->option('limit'));
        $sent = 0;
        foreach ($due as $o) {
            if ($this->option('dry')) { $this->line("would ask {$o->email} (order {$o->order_number})"); continue; }
            if (ReviewRequests::send($o)) $sent++;
        }
        $this->info($this->option('dry') ? $due->count() . ' due.' : "$sent review request(s) sent.");
        return self::SUCCESS;
    }
}
