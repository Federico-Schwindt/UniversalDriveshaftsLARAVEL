<?php

namespace App\Console\Commands;

use App\Services\B2bAccounts;
use Illuminate\Console\Command;

/** Net terms invoices: a reminder a few days before the due date, an overdue notice the day after and weekly after that. */
class B2bTermsReminders extends Command
{
    protected $signature = 'b2b:terms-reminders';
    protected $description = 'E-mail Net terms invoice reminders and overdue notices';

    public function handle(): int
    {
        $this->info(B2bAccounts::remind() . ' reminder(s) sent.');
        return 0;
    }
}
