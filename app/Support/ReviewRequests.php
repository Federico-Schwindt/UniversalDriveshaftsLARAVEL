<?php

namespace App\Support;

use App\Http\Controllers\RecoveryController;
use Illuminate\Support\Facades\DB;

/**
 * "How did we do?" e-mails asking customers for a Google review (owner, 2026-11-04: local SEO, point 4).
 *
 * One e-mail per delivered order, `reviews.delay_days` after delivery (or after it shipped when no delivery scan came),
 * at most one per customer every 180 days. Never for orders imported from the old store, cancelled / refunded orders,
 * orders with an open return, staff, or anyone who opted out (marketing_suppressions channel "reviews").
 * Everyone eligible gets the same Google link: asking only happy customers ("review gating") breaks Google's rules.
 * Off until the owner turns it on in Admin → Settings → Reviews (and confirms the Google review link).
 */
class ReviewRequests
{
    public const CHANNEL = 'reviews';
    /** The Google listing the old WordPress site pointed at (SEOPress local business, place id). */
    public const DEFAULT_URL = 'https://search.google.com/local/writereview?placeid=ChIJ-7EeFXzHwogR6vOOe0xLJio';
    /** The same listing's reviews (what the rating badge opens). */
    public const PROFILE_URL = 'https://search.google.com/local/reviews?placeid=ChIJ-7EeFXzHwogR6vOOe0xLJio';

    public static function enabled(): bool
    {
        return (bool) Settings::get('reviews.enabled', false) && self::url() !== '';
    }

    public static function url(): string
    {
        return trim((string) Settings::get('reviews.google_url', self::DEFAULT_URL));
    }

    /** Orders due for a review request now. */
    public static function due(int $limit = 50)
    {
        $delay = max(1, (int) Settings::get('reviews.delay_days', 5));
        $staff = DB::table('users')->where('is_admin', 1)->pluck('email')->map(fn ($e) => strtolower($e))->all();
        return DB::table('orders as o')
            ->where(fn ($q) => $q->whereNull('o.source')->orWhere('o.source', '!=', 'woocommerce'))
            ->whereNotIn('o.status', ['cancelled', 'failed', 'refunded'])
            ->whereNotIn('o.payment_status', ['refunded', 'partially_refunded', 'unpaid'])
            // delivered N days ago, or shipped N+7 days ago without a delivery scan; only recent ones (no back-catalogue blast)
            ->where(fn ($q) => $q->whereBetween('o.delivered_at', [now()->subDays($delay + 21), now()->subDays($delay)])
                ->orWhere(fn ($w) => $w->whereNull('o.delivered_at')->whereBetween('o.shipped_at', [now()->subDays($delay + 28), now()->subDays($delay + 7)])))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('order_events as e')->whereColumn('e.order_id', 'o.id')->where('e.type', 'notify:email:review'))
            // one request per customer every 180 days
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('order_events as e')->join('orders as p', 'p.id', '=', 'e.order_id')
                ->whereColumn('p.email', 'o.email')->where('e.type', 'notify:email:review')->where('e.created_at', '>=', now()->subDays(180)))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('rma_requests as r')->whereColumn('r.order_number', 'o.order_number')->whereNotIn('r.status', ['rejected', 'completed']))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('marketing_suppressions as s')->whereRaw('s.email = LOWER(o.email)')->where('s.channel', self::CHANNEL))
            ->when($staff, fn ($q) => $q->whereNotIn(DB::raw('LOWER(o.email)'), $staff))
            ->orderBy('o.id')->limit($limit)->get(['o.*']);
    }

    /** Send the request for one order. The timeline row is written first, so a rerun never mails twice. */
    public static function send(object $order): bool
    {
        if (!OrderEvents::record($order, 'notify:email:review', 'Review request sent', $order->email, true)) return false;
        try {
            [$subject, $content] = self::message($order);
            Mailer::simple($order->email, $subject, $content, ['replyTo' => [config('uds.email')]]);
            return true;
        } catch (\Throwable $e) {
            OrderEvents::forget($order, 'notify:email:review');
            report($e);
            return false;
        }
    }

    /** [subject, Mailer::simple content] — also used by the admin template preview. */
    public static function message(object $order, ?array $items = null): array
    {
        $name = trim((string) ($order->billing_first_name ?: 'there'));
        $items ??= DB::table('order_items')->where('order_id', $order->id ?? 0)->orderBy('id')->limit(3)->get(['name', 'sku', 'qty'])->all();
        $what = $items ? implode(', ', array_map(fn ($i) => trim(($i->sku ?? '') ?: ($i->name ?? '')), array_slice($items, 0, 2))) . (count($items) > 2 ? ' and more' : '') : 'your parts';
        $unsub = self::unsubscribeUrl((string) $order->email);
        return ["How did we do, {$name}?", [
            'kicker' => 'Order ' . $order->order_number,
            'title' => "Thanks for your order, {$name}.",
            'intro' => "Your order {$order->order_number} ({$what}) should be with you by now. Would you take 30 seconds to tell other drivers and shops how it went?",
            'preheader' => '30 seconds on Google helps a small Florida driveshaft shop a lot.',
            'button' => ['Review us on Google', self::url()],
            'paragraphs' => ['A few words about the parts, the shipping or the help you got make a real difference: they help other drivers and shops pick a driveshaft supplier.'],
            'after' => ['Something not right with the order? Reply to this e-mail and it comes straight to the shop. We will sort it out.',
                ['html' => '<span style="font-size:12px;color:#7f91b4">Don\'t want review requests? <a href="' . e($unsub) . '" style="color:#7f91b4">Stop these e-mails</a>.</span>']],
            'signoff' => 'Thank you, the team at Universal Driveshafts · (813) 524-6491',
        ]];
    }

    public static function unsubscribeUrl(string $email): string
    {
        $email = strtolower(trim($email));
        return url('/reviews/unsubscribe/' . self::sig($email) . rtrim(strtr(base64_encode($email), '+/', '-_'), '='));
    }

    /** The e-mail behind a signed opt-out token, null when it does not check out. */
    public static function emailFromToken(string $token): ?string
    {
        $email = strtolower((string) base64_decode(strtr(substr($token, 32), '-_', '+/'), true));
        return filter_var($email, FILTER_VALIDATE_EMAIL) && hash_equals(self::sig($email), substr($token, 0, 32)) ? $email : null;
    }

    public static function optOut(string $email): void
    {
        RecoveryController::suppress($email, self::CHANNEL);
    }

    private static function sig(string $email): string
    {
        return substr(hash_hmac('sha256', 'reviews-unsub|' . $email, (string) config('app.key')), 0, 32);
    }
}
