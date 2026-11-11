<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Returns / exchanges / cancellations: the statuses and the moves allowed between them, the request's history
 * (rma_events), and the e-mails to the shop and the customer. Mail failures are recorded on the request and logged,
 * never swallowed (2026-11-10: a request reached nobody and left no trace).
 */
class Rma
{
    /** status => [admin label, badge class] */
    public const STATUSES = [
        'new' => ['New', 'warn'],
        'approved' => ['Approved', 'info'],
        'received' => ['Part received', 'info'],
        'resolved' => ['Resolved', 'ok'],
        'rejected' => ['Rejected', 'plain'],
        'withdrawn' => ['Withdrawn', 'plain'],
    ];

    /** What a request can move to from each status (checked on the server). */
    public const NEXT = [
        'new' => ['approved', 'rejected', 'withdrawn'],
        'approved' => ['received', 'resolved', 'rejected', 'withdrawn'],
        'received' => ['resolved', 'rejected'],
        'resolved' => ['new'],
        'rejected' => ['new'],
        'withdrawn' => ['new'],
    ];

    public const OPEN = ['new', 'approved', 'received'];

    public const RESOLUTIONS = [
        'refund' => 'Refund', 'replacement' => 'Replacement sent', 'store_credit' => 'Store credit',
        'repair' => 'Repaired and returned', 'cancelled' => 'Order cancelled', 'no_charge' => 'Closed, no charge',
    ];

    public static function label(string $status): string
    {
        return self::STATUSES[$status][0] ?? ucfirst($status);
    }

    public static function badge(string $status): string
    {
        return 'badge badge--' . (self::STATUSES[$status][1] ?? 'plain');
    }

    public static function typeLabel(string $type): string
    {
        return ['return' => 'Return', 'exchange' => 'Exchange', 'cancel' => 'Cancellation'][$type] ?? ucfirst($type);
    }

    /** What the customer sees: [short state, one line on what happens next], translated. */
    public static function customerState(object $rma): array
    {
        return match ($rma->status) {
            'new' => [__('Under review'), __('We are reviewing your request and will reply within one business day.')],
            'approved' => $rma->type === 'cancel' ? [__('Approved'), __('Your cancellation is approved.')] : [__('Approved'), __('Ship the part back with :ref written on the box.', ['ref' => $rma->ref])],
            'received' => [__('Part received'), __('We received your part and are checking it.')],
            'resolved' => [__('Resolved'), __(self::RESOLUTIONS[$rma->resolution] ?? 'Closed')],
            'rejected' => [__('Not approved'), __('Questions? Reply to our e-mail or call :phone.', ['phone' => config('uds.pickup.phone')])],
            default => [__('Withdrawn'), ''],
        };
    }

    public static function record(int $rmaId, string $type, string $label, ?string $detail = null, ?int $userId = null): void
    {
        DB::table('rma_events')->insert(['rma_id' => $rmaId, 'user_id' => $userId, 'type' => $type, 'label' => mb_substr($label, 0, 190), 'detail' => $detail, 'created_at' => now()]);
    }

    /** Open requests on an order (newest first), for the order page and the customer's order views. */
    public static function forOrder(object $order)
    {
        return DB::table('rma_requests')->where(fn ($w) => $w->where('order_id', $order->id)->orWhere('order_number', $order->order_number))
            ->orderByDesc('id')->get();
    }

    /** E-mail the shop about a new request, with a link to its admin page. */
    public static function notifyShop(object $rma): bool
    {
        $label = self::typeLabel($rma->type);
        try {
            Mailer::simple(config('uds.email'), "{$label} request — {$rma->ref}", [
                'kicker' => 'Returns', 'title' => "New {$label} request {$rma->ref}",
                'kv' => ['Name' => $rma->name, 'E-mail' => $rma->email, 'Phone' => $rma->phone ?: null, 'Order' => $rma->order_number ?: null,
                    'Product' => $rma->product ?: null, 'Reason' => $rma->reason ?: null, 'Details' => $rma->details ?: null],
                'button' => ['Open the request', rtrim((string) config('app.url'), '/') . '/admin/rma/' . $rma->id], 'signoff' => false,
            ], ['replyTo' => [$rma->email, $rma->name]]);
            self::record($rma->id, 'email', 'Shop notified by e-mail', config('uds.email'));
            return true;
        } catch (\Throwable $e) {
            Log::warning('RMA shop e-mail failed', ['ref' => $rma->ref, 'error' => $e->getMessage()]);
            self::record($rma->id, 'email_failed', 'Shop e-mail failed', mb_substr($e->getMessage(), 0, 500));
            return false;
        }
    }

    /** The customer's confirmation when they file the request. */
    public static function confirmToCustomer(object $rma): bool
    {
        $label = __(['return' => 'Return request', 'exchange' => 'Exchange request', 'cancel' => 'Cancellation request'][$rma->type] ?? 'Return request');
        try {
            Mailer::simple($rma->email, __(':label received — :ref', ['label' => $label, 'ref' => $rma->ref]), [
                'kicker' => $label, 'title' => __('We got your request, :name.', ['name' => $rma->name]),
                'intro' => __('Reference :ref. Our team reviews every request personally and will get back to you shortly — usually within one business day.', ['ref' => $rma->ref]),
                'kv' => [__('Reference') => $rma->ref, __('Order') => $rma->order_number ?: null, __('Product') => $rma->product ?: null, __('Reason') => $rma->reason ? __($rma->reason) : null],
                'after' => [__('Please do not ship anything back until you receive the RMA instructions.')],
            ], ['replyTo' => [config('uds.email')]]);
            self::record($rma->id, 'email', 'Confirmation e-mailed to the customer', $rma->email);
            return true;
        } catch (\Throwable $e) {
            Log::warning('RMA customer e-mail failed', ['ref' => $rma->ref, 'error' => $e->getMessage()]);
            self::record($rma->id, 'email_failed', 'Customer confirmation failed', mb_substr($e->getMessage(), 0, 500));
            return false;
        }
    }

    /**
     * Tell the customer where their request stands after an admin decision. $message is the admin's own words
     * (shown as written); the rest is standard per status. Sent in the order's language when the order has one.
     */
    public static function notifyCustomer(object $rma, string $status, ?string $message, ?int $userId = null): bool
    {
        $locale = $rma->order_id ? DB::table('orders')->where('id', $rma->order_id)->value('locale') : null;
        $prev = app()->getLocale();
        if ($locale && in_array($locale, ['en', 'es', 'fr', 'zh', 'ja'], true)) app()->setLocale($locale);
        try {
            $ref = $rma->ref; $isCancel = $rma->type === 'cancel';
            [$subject, $title, $intro, $after] = match ($status) {
                'approved' => $isCancel
                    ? [__('Cancellation approved — :ref', ['ref' => $ref]), __('Your cancellation is approved'), __('We have stopped your order. Any refund due goes back to your original payment method.'), []]
                    : [__('Your request is approved — :ref', ['ref' => $ref]), __('Your request is approved'), __('Ship the part back to us with the reference :ref written on the box.', ['ref' => $ref]),
                        [__('Return address: Universal Driveshafts, :address', ['address' => config('uds.pickup.address')]), __('Send us the tracking number when it ships so we can watch for it.')]],
                'received' => [__('We received your part — :ref', ['ref' => $ref]), __('Your part arrived'), __('We have your part and are checking it now. We will write again as soon as it is done.'), []],
                'resolved' => [__('Your request is resolved — :ref', ['ref' => $ref]), __('All done'), self::resolutionText($rma), []],
                'rejected' => [__('About your request — :ref', ['ref' => $ref]), __('We could not approve this request'), __('Questions? Reply to this e-mail or call :phone.', ['phone' => config('uds.pickup.phone')]), []],
                default => [__('Update on your request — :ref', ['ref' => $ref]), __('Update on your request'), '', []],
            };
            $paras = array_values(array_filter([trim((string) $message)]));
            Mailer::simple($rma->email, $subject, ['kicker' => __(self::typeLabel($rma->type)), 'title' => $title, 'intro' => $intro ?: null,
                'paragraphs' => $paras, 'kv' => [__('Reference') => $ref, __('Order') => $rma->order_number ?: null], 'after' => $after],
                ['replyTo' => [config('uds.email')], 'locale' => app()->getLocale()]);
            self::record($rma->id, 'email', 'Customer e-mailed: ' . self::label($status), trim((string) $message) ?: null, $userId);
            return true;
        } catch (\Throwable $e) {
            Log::warning('RMA customer e-mail failed', ['ref' => $rma->ref, 'error' => $e->getMessage()]);
            self::record($rma->id, 'email_failed', 'Customer e-mail failed', mb_substr($e->getMessage(), 0, 500), $userId);
            return false;
        } finally {
            app()->setLocale($prev);
        }
    }

    private static function resolutionText(object $rma): string
    {
        $amt = $rma->refund_amount !== null ? '$' . number_format((float) $rma->refund_amount, 2) : null;
        return match ($rma->resolution) {
            'refund' => $amt ? __('A refund of :amount is on its way to your original payment method. Banks usually show it within 5 to 10 business days.', ['amount' => $amt]) : __('Your refund is on its way to your original payment method. Banks usually show it within 5 to 10 business days.'),
            'replacement' => __('Your replacement part is on its way. You will get the tracking number by e-mail.'),
            'store_credit' => $amt ? __('We added :amount of store credit to your account.', ['amount' => $amt]) : __('We added store credit to your account.'),
            'repair' => __('Your part is repaired and on its way back to you.'),
            'cancelled' => __('Your order is cancelled.'),
            default => __('Your request is closed. Thank you for your patience.'),
        };
    }
}
