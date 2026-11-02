<?php

namespace App\Console\Commands;

use App\Http\Controllers\RecoveryController;
use App\Services\RecoveryMailer;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Abandoned-cart reminders. Runs every 15 minutes:
 *  1. rows whose customer has since placed an order are marked recovered (never mailed),
 *  2. reminder 1 goes out >= 1 h after the cart was last seen, reminder 2 >= 24 h.
 * Nothing older than 14 days is ever mailed, so a scheduler outage cannot cause a blast. Addresses in
 * marketing_suppressions (cart_reminders) are never mailed. The reminder text message is off: checkout
 * collects no marketing-SMS consent (TCPA); uds.cart_reminder_sms only exists for when it does.
 */
class RecoverySend extends Command
{
    protected $signature = 'recovery:send {--dry : list what would be sent without mailing} {--force-id= : send the next reminder for one row now (testing)}';
    protected $description = 'Send abandoned-cart reminder e-mails (1 h and 24 h after the cart was last seen)';

    public function handle(RecoveryMailer $mailer, SmsService $sms): int
    {
        $recovered = $this->markRecovered();
        if ($recovered) $this->info("marked recovered: $recovered");

        $q = DB::table('abandoned_carts')->whereNotNull('email')->whereNull('recovered_at')->whereNull('unsubscribed_at')
            ->where('reminders_sent', '<', 2)->where('last_seen_at', '>=', now()->subDays(14))
            ->whereNotExists(fn ($s) => $s->from('marketing_suppressions as ms')->whereColumn('ms.email', 'abandoned_carts.email')->where('ms.channel', 'cart_reminders'));
        if ($id = $this->option('force-id')) {
            $q = DB::table('abandoned_carts')->where('id', (int) $id)->whereNotNull('email')
                ->whereNotExists(fn ($s) => $s->from('marketing_suppressions as ms')->whereColumn('ms.email', 'abandoned_carts.email')->where('ms.channel', 'cart_reminders'));
        } else {
            $q->where(function ($w) {
                $w->where(fn ($a) => $a->where('reminders_sent', 0)->where('last_seen_at', '<=', now()->subHour()))
                  ->orWhere(fn ($b) => $b->where('reminders_sent', 1)->where('last_seen_at', '<=', now()->subDay())->where('last_reminder_at', '<=', now()->subHours(6)));
            });
        }
        $rows = $q->orderBy('last_seen_at')->limit(200)->get();
        $sent = 0;
        foreach ($rows as $row) {
            $status = RecoveryController::status($row);
            if (!in_array($status, ['waiting', 'reminded'], true) && !$id) { $this->line("#{$row->id} {$row->email}: skip ($status)"); continue; }
            // a newer cart from the same customer supersedes this one
            $newer = DB::table('abandoned_carts')->where('email', $row->email)->where('id', '!=', $row->id)->where('last_seen_at', '>', $row->last_seen_at)->exists();
            if ($newer && !$id) { $this->line("#{$row->id} {$row->email}: skip (superseded)"); continue; }
            if (!RecoveryMailer::describeItems(json_decode($row->items ?: '[]', true) ?: [])) {
                // every saved part has since disappeared from the catalog: nothing to remind about
                DB::table('abandoned_carts')->where('id', $row->id)->update(['reminders_sent' => 2, 'updated_at' => now()]);
                $this->line("#{$row->id} {$row->email}: skip (no live parts left), closed"); continue;
            }
            $n = (int) $row->reminders_sent + 1;
            if ($this->option('dry')) { $this->line("#{$row->id} {$row->email}: would send reminder $n (\${$row->subtotal})"); continue; }
            if (!$mailer->cartReminder($row, $n)) { $this->warn("#{$row->id} {$row->email}: mail failed"); continue; }
            if ($n === 2 && $row->phone && config('uds.cart_reminder_sms') && $sms->enabled()) {
                $sms->send($row->phone, 'Universal Driveshafts: your cart ($' . number_format((float) $row->subtotal, 2) . ') is saved and ready to ship from Florida. Finish here: ' . url('/cart/restore/' . $row->token) . ' or call (813) 524-6491. Reply STOP to opt out.');
            }
            DB::table('abandoned_carts')->where('id', $row->id)->update(['reminders_sent' => $n, 'last_reminder_at' => now(), 'updated_at' => now()]);
            $this->info("#{$row->id} {$row->email}: reminder $n sent");
            $sent++;
        }
        $this->info("done: $sent sent, " . $rows->count() . ' considered');
        return 0;
    }

    /** An order from the same e-mail (or account) placed after the cart was last seen = recovered. Only carts still mailable (15 days) are looked at. */
    private function markRecovered(): int
    {
        $n = 0;
        $rows = DB::table('abandoned_carts')->whereNull('recovered_at')->where('last_seen_at', '>=', now()->subDays(15))
            ->where(fn ($w) => $w->whereNotNull('email')->orWhereNotNull('user_id'))->get(['id', 'email', 'user_id', 'last_seen_at']);
        foreach ($rows as $r) {
            $o = DB::table('orders')->where('created_at', '>=', $r->last_seen_at)
                ->where(function ($w) use ($r) {
                    // plain comparison uses the orders.email index (the column collation is case-insensitive; capture stores lowercase)
                    if ($r->email) $w->where('email', $r->email);
                    if ($r->user_id) $w->orWhere('user_id', $r->user_id);
                })->orderBy('created_at')->first(['created_at']);
            if ($o) { DB::table('abandoned_carts')->where('id', $r->id)->update(['recovered_at' => $o->created_at, 'updated_at' => now()]); $n++; }
        }
        return $n;
    }
}
