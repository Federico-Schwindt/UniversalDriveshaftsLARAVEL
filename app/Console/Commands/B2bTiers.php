<?php

namespace App\Console\Commands;

use App\Services\B2bAccounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recompute every approved B2B account's tier (Bronze / Silver / Gold) from its monthly purchases and
 * e-mail the ones that moved. Runs daily just after midnight Eastern, so on the 1st accounts that fell
 * short drop a tier; upgrades also happen the moment an order is paid (PayLink::markPaid, terms orders).
 */
class B2bTiers extends Command
{
    protected $signature = 'b2b:tiers {--user= : only this user id} {--no-mail : update without e-mailing}';
    protected $description = 'Recompute B2B tiers from monthly purchases and e-mail changes';

    public function handle(): int
    {
        $q = DB::table('users')->where('wholesale_status', 'approved');
        if ($this->option('user')) $q->where('id', (int) $this->option('user'));
        $moved = 0;
        foreach ($q->get(['id', 'email', 'b2b_tier']) as $u) {
            $new = B2bAccounts::evaluate($u->id, !$this->option('no-mail'));
            if ($new !== $u->b2b_tier) { $moved++; $this->line("{$u->email}: " . ($u->b2b_tier ?: '—') . " → {$new}"); }
        }
        $this->info("{$moved} account(s) changed tier.");
        return 0;
    }
}
