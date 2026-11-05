<?php

namespace App\Services;

use App\Support\Mailer;
use App\Support\OrderEvents;
use App\Support\PayLink;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Approved B2B accounts (users.wholesale_status = approved):
 *
 *  - Tiers. Bronze on approval; Silver / Gold once a calendar month's purchases (Eastern time) reach the
 *    thresholds. The tier is always max(tier earned last month, tier earned so far this month), so an
 *    upgrade applies the moment the threshold is crossed, holds through the next month, and drops back on
 *    the 1st when the month just ended fell short. The discount is one line on the cart (all merchandise).
 *  - Net terms. Pay by invoice, due N days after the order, up to a credit limit; any overdue invoice
 *    pauses terms until it is paid.
 *
 * Thresholds, percentages, days and the default credit limit live in admin Settings → B2B.
 */
class B2bAccounts
{
    public const TIERS = ['bronze', 'silver', 'gold'];
    public const TZ = 'America/New_York';

    /** @return array<string,array{name:string,pct:float,min:float}> */
    public static function tiers(): array
    {
        return [
            'bronze' => ['name' => __('Bronze'), 'pct' => (float) Settings::get('b2b.bronze_pct', 5), 'min' => 0.0],
            'silver' => ['name' => __('Silver'), 'pct' => (float) Settings::get('b2b.silver_pct', 8), 'min' => (float) Settings::get('b2b.silver_min', 5500)],
            'gold' => ['name' => __('Gold'), 'pct' => (float) Settings::get('b2b.gold_pct', 10), 'min' => (float) Settings::get('b2b.gold_min', 7000)],
        ];
    }

    public static function approved(?object $u): bool
    {
        return $u && ($u->wholesale_status ?? '') === 'approved';
    }

    public static function tierOf(?object $u): ?string
    {
        if (!self::approved($u)) return null;
        return in_array($u->b2b_tier ?? '', self::TIERS, true) ? $u->b2b_tier : 'bronze';
    }

    public static function label(string $tier): string
    {
        $t = self::tiers()[$tier] ?? self::tiers()['bronze'];
        return $t['name'] . ' ' . rtrim(rtrim(number_format($t['pct'], 2), '0'), '.') . '%';
    }

    /** The tier discount on a merchandise subtotal: [tier, pct, amount], or [null, 0, 0] for everyone else. */
    public static function discount(?object $u, float $subtotal): array
    {
        $tier = self::tierOf($u);
        if (!$tier || $subtotal <= 0) return [null, 0.0, 0.0];
        $pct = self::tiers()[$tier]['pct'];
        return [$tier, $pct, round($subtotal * $pct / 100, 2)];
    }

    /* ---------------- monthly spend and tier ---------------- */

    /** Merchandise bought in a calendar month (Eastern): after discounts and refunds, without shipping or tax. Paid orders and orders on terms count. */
    public static function spend(int $userId, Carbon $month): float
    {
        $from = $month->copy()->timezone(self::TZ)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $rows = DB::table('orders')->where('user_id', $userId)
            ->whereBetween('created_at', [$from->copy()->timezone(config('app.timezone')), $to->copy()->timezone(config('app.timezone'))])
            ->whereNotIn('status', ['cancelled', 'failed', 'refunded'])
            ->where(fn ($q) => $q->whereIn('payment_status', ['paid', 'partially_refunded'])->orWhere('payment_method', 'net_terms'))
            ->get(['subtotal', 'discount_total', 'refund_total', 'shipping_total', 'total']);
        $sum = 0.0;
        foreach ($rows as $o) {
            // a refund comes off the merchandise; it never takes an order below zero
            $sum += max(0, (float) $o->subtotal - (float) $o->discount_total - max(0, (float) $o->refund_total));
        }
        return round($sum, 2);
    }

    public static function tierForSpend(float $spend): string
    {
        $t = self::tiers();
        return $spend >= $t['gold']['min'] ? 'gold' : ($spend >= $t['silver']['min'] ? 'silver' : 'bronze');
    }

    private static function rank(?string $tier): int
    {
        return (int) array_search($tier ?: 'bronze', self::TIERS, true);
    }

    /** Where the account stands this month, for the account page. */
    public static function status(object $u): array
    {
        $now = now(self::TZ);
        $mtd = self::spend($u->id, $now);
        $last = self::spend($u->id, $now->copy()->subMonthNoOverflow());
        $tier = self::tierOf($u);
        $tiers = self::tiers();
        $next = $tier === 'bronze' ? 'silver' : ($tier === 'silver' ? 'gold' : null);
        $earnedMtd = self::tierForSpend($mtd);
        return [
            'tier' => $tier, 'tiers' => $tiers, 'mtd' => $mtd, 'last' => $last, 'month' => $now->copy()->locale(app()->getLocale())->isoFormat('MMMM'), 'nextMonth' => $now->copy()->addMonthNoOverflow()->locale(app()->getLocale())->isoFormat('MMMM'),   // month names in the page language
            'next' => $next, 'toNext' => $next ? max(0, $tiers[$next]['min'] - $mtd) : 0,
            // what this month's spend locks in for next month
            'nextMonthTier' => $earnedMtd,
            'toKeep' => $tier && $tier !== 'bronze' ? max(0, $tiers[$tier]['min'] - $mtd) : 0,
        ];
    }

    /** Recompute one account's tier; e-mails the customer when it moves. Returns the new tier (or null if not approved). */
    public static function evaluate(int $userId, bool $notify = true, string $reason = ''): ?string
    {
        $u = DB::table('users')->find($userId);
        if (!self::approved($u)) return null;
        $now = now(self::TZ);
        $last = self::spend($userId, $now->copy()->subMonthNoOverflow());
        $mtd = self::spend($userId, $now);
        $byLast = self::tierForSpend($last);
        $byMtd = self::tierForSpend($mtd);
        $new = self::rank($byMtd) >= self::rank($byLast) ? $byMtd : $byLast;
        $old = $u->b2b_tier;
        if ($old === $new) return $new;
        DB::table('users')->where('id', $userId)->update(['b2b_tier' => $new, 'b2b_tier_at' => now(), 'updated_at' => now()]);
        // a drop mid-month (tier earned this month, then a refund / cancellation lowered the total) is not "last month closed short"
        $midMonth = self::rank($new) < self::rank($old) && $u->b2b_tier_at && Carbon::parse($u->b2b_tier_at, 'UTC')->timezone(self::TZ)->isSameMonth($now);
        $why = $reason ?: ($midMonth ? 'This month fell to $' . number_format($mtd, 2) . ' after a refund or cancellation'
            : ($new === $byMtd && self::rank($byMtd) > self::rank($byLast) ? 'This month reached $' . number_format($mtd, 2) : 'Last month closed at $' . number_format($last, 2)));
        DB::table('b2b_tier_log')->insert(['user_id' => $userId, 'from_tier' => $old, 'to_tier' => $new, 'spend' => self::rank($byMtd) >= self::rank($byLast) ? $mtd : $last, 'reason' => $why, 'created_at' => now()]);
        // the first tier (on approval) is announced by the approval e-mail itself
        if ($notify && $old !== null && !self::dormantImport($u)) {
            try { self::mailChange($u, $old, $new, $mtd, $last, $midMonth); } catch (\Throwable $e) { report($e); }
        }
        return $new;
    }

    /** An account imported from the old store that has not used this site yet: it hears nothing from us until it does. */
    public static function dormantImport(object $u): bool
    {
        return !empty($u->wp_user_id) && empty($u->last_login_at)
            && !DB::table('orders')->where('user_id', $u->id)->where('source', '!=', 'woocommerce')->exists();
    }

    private static function mailChange(object $u, string $old, string $new, float $mtd, float $last, bool $midMonth = false): void
    {
        $t = self::tiers();
        $up = self::rank($new) > self::rank($old);
        $now = now(self::TZ);
        $pct = rtrim(rtrim(number_format($t[$new]['pct'], 2), '0'), '.');
        $name = trim(explode(' ', (string) $u->name)[0] ?: 'there');
        if ($up) {
            $keepMonth = $now->copy()->addMonthNoOverflow()->format('F');
            Mailer::simple($u->email, "You reached {$t[$new]['name']}: {$pct}% off every order", [
                'kicker' => 'Business account', 'title' => "Welcome to {$t[$new]['name']}, {$name}.",
                'intro' => "Your purchases this month reached \$" . number_format($mtd, 2) . ", so your account is now {$t[$new]['name']}. Every order takes {$pct}% off, starting right now.",
                'kv' => ['Your tier' => $t[$new]['name'] . ' · ' . $pct . '% off', 'This month' => '$' . number_format($mtd, 2), 'Keeps it through' => 'the end of ' . $keepMonth],
                'paragraphs' => ["To keep {$t[$new]['name']} after {$keepMonth}, reach \$" . number_format($t[$new]['min'], 0) . " in purchases during {$keepMonth}."],
                'button' => ['See my business account', rtrim(config('app.url'), '/') . '/account/business'],
            ], ['replyTo' => [config('uds.email')]]);
        } else {
            $prev = $now->copy()->subMonthNoOverflow()->format('F');
            $nextUp = $new === 'bronze' ? 'silver' : 'gold';
            Mailer::simple($u->email, "Your business account is now {$t[$new]['name']} ({$pct}% off)", [
                'kicker' => 'Business account', 'title' => "Your account moved to {$t[$new]['name']}.",
                'intro' => $midMonth
                    ? "After a refund or cancellation, your purchases this month come to \$" . number_format($mtd, 2) . ", below the \$" . number_format($t[$old]['min'], 0) . " that keeps {$t[$old]['name']}. Your orders now take {$pct}% off."
                    : "Purchases in {$prev} came to \$" . number_format($last, 2) . ", below the \$" . number_format($t[$old]['min'], 0) . " that keeps {$t[$old]['name']}. Your orders now take {$pct}% off.",
                'kv' => ['Your tier' => $t[$new]['name'] . ' · ' . $pct . '% off'] + ($midMonth ? ['This month' => '$' . number_format($mtd, 2)] : [$prev => '$' . number_format($last, 2)]),
                'paragraphs' => ["Reach \$" . number_format($t[$nextUp]['min'], 0) . " in a month and {$t[$nextUp]['name']} comes back the moment you cross it."],
                'button' => ['See my business account', rtrim(config('app.url'), '/') . '/account/business'],
            ], ['replyTo' => [config('uds.email')]]);
        }
    }

    /* ---------------- Net terms ---------------- */

    public static function termsDays(): int
    {
        return max(1, (int) Settings::get('b2b.terms_days', 20));
    }

    public static function creditLimit(object $u): float
    {
        return (float) ($u->credit_limit ?? Settings::get('b2b.credit_limit', 10000));
    }

    /** Unpaid invoices on terms. */
    public static function openInvoices(int $userId)
    {
        return DB::table('orders')->where('user_id', $userId)->where('payment_method', 'net_terms')->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'failed', 'refunded'])->orderBy('terms_due_at')->get();
    }

    /** [available, open balance, overdue count, limit, reason if not available] for an order of $amount. */
    public static function terms(?object $u, float $amount = 0): array
    {
        if (!self::approved($u)) return [false, 0, 0, 0, 'not_approved'];
        $open = self::openInvoices($u->id);
        $balance = round($open->sum(fn ($o) => PayLink::balance($o)), 2);
        $today = now(self::TZ)->toDateString();
        $overdue = $open->filter(fn ($o) => $o->terms_due_at && $o->terms_due_at < $today)->count();
        $limit = self::creditLimit($u);
        $reason = !($u->net_terms ?? true) ? 'off' : ($overdue ? 'overdue' : ($balance + $amount > $limit ? 'limit' : null));
        return [$reason === null, $balance, $overdue, $limit, $reason];
    }

    /** E-mail the invoice reminders: a few days before the due date, the day after, then weekly. Run daily. */
    public static function remind(): int
    {
        $sent = 0;
        $today = now(self::TZ)->startOfDay();
        $before = (int) Settings::get('b2b.remind_days', 3);
        $orders = DB::table('orders')->where('payment_method', 'net_terms')->where('payment_status', 'unpaid')
            ->whereNotIn('status', ['cancelled', 'failed', 'refunded'])->whereNotNull('terms_due_at')->where('source', '!=', 'woocommerce')->get();
        foreach ($orders as $o) {
            $due = Carbon::parse($o->terms_due_at, self::TZ)->startOfDay();
            $days = (int) $today->diffInDays($due, false);   // > 0: still to come
            $key = null;
            // windows, not single days, so a send that failed (or a missed run) is retried the next day under the same key
            if ($days <= $before && $days >= min(1, $before)) $key = 'terms:reminder';
            elseif ($days < 0) $key = 'terms:overdue:' . (-$days < 7 ? 1 : intdiv(-$days, 7) * 7);
            if (!$key || !OrderEvents::record($o, $key, $days >= 0 ? 'Invoice reminder sent' : 'Overdue notice sent', $o->email, true)) continue;
            // the row is written first so two runs can't both send; a failed send removes it so tomorrow's run retries
            try { self::mailReminder($o, $days); $sent++; } catch (\Throwable $e) { report($e); OrderEvents::forget($o, $key); }
        }
        return $sent;
    }

    private static function mailReminder(object $o, int $days): void
    {
        $bal = '$' . number_format(PayLink::balance($o), 2);
        $due = Carbon::parse($o->terms_due_at)->format('l, F j');
        $late = $days < 0;
        Mailer::simple($o->email, $late ? "Overdue: invoice {$o->order_number} ({$bal})" : "Invoice {$o->order_number} is due {$due}", [
            'kicker' => 'Net ' . self::termsDays() . ' invoice', 'title' => $late ? "Invoice {$o->order_number} is past due." : "A reminder: {$bal} due {$due}.",
            'intro' => $late ? "It was due {$due}. New orders on terms are paused until it is paid." : "Your invoice for order {$o->order_number} is due in {$days} days.",
            'kv' => ['Invoice' => $o->order_number, 'PO number' => $o->po_number ?: null, 'Balance' => $bal, 'Due' => $due],
            'button' => ['Pay invoice', PayLink::url($o)],
            'after' => ['Paying by check or ACH? Reply to this e-mail and we will send the details. Questions: (813) 524-6491.'],
        ], ['replyTo' => [config('uds.email')]]);
    }
}
