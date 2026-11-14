<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

/**
 * Branded HTML mail for the recovery features: abandoned-cart reminders, back-in-stock
 * notices and "get a quote" requests. Every method swallows transport errors (sendmail
 * may be down on staging) and reports them, so a scheduler run never dies on one address.
 */
class RecoveryMailer
{
    /** Cart reminder #1 (1 h) or #2 (24 h). Returns true when the message was handed to the mailer. */
    public function cartReminder(object $row, int $n): bool
    {
        $items = self::describeItems(json_decode($row->items ?: '[]', true) ?: []);
        if (!$items) return false;
        $name = $this->firstName($row);
        $data = [
            'n' => $n, 'name' => $name, 'items' => $items, 'count' => array_sum(array_column($items, 'qty')),
            'subtotal' => (float) $row->subtotal,
            'restoreUrl' => url('/cart/restore/' . $row->token),
            'unsubUrl' => url('/cart/recovery/unsubscribe/' . $row->token),
            'title' => 'Your Universal Driveshafts cart',
            'preheader' => $n === 1 ? 'Your parts are saved. Pick up where you left off.' : 'Your cart is still here, ready to ship from Florida.',
        ];
        $subject = $n === 1
            ? 'You left ' . ($data['count'] === 1 ? 'a part' : $data['count'] . ' parts') . ' in your cart'
            : 'Still need ' . ($items[0]['sku'] ?: 'those parts') . '? Your cart is ready to ship';
        // marketing mail: one-click unsubscribe headers (Gmail/Yahoo bulk-sender rules, RFC 8058)
        return $this->send('emails.recovery.cart', $data, $row->email, $subject . ' — Universal Driveshafts', null, self::unsubscribeHeaders($data['unsubUrl']));
    }

    /** "Back in stock" notice. $part: name, sku, price, url, image, stock, vehicle. */
    public function backInStock(object $alert, array $part): bool
    {
        $part += ['name' => $alert->label, 'sku' => '', 'price' => null, 'url' => url('/'), 'image' => null, 'stock' => null, 'vehicle' => $alert->vehicle];
        if ($part['image']) $part['image'] = url(media_url($part['image'], 160));
        $data = ['part' => $part, 'title' => 'Back in stock', 'preheader' => $part['name'] . ' is available again in Florida.'];
        return $this->send('emails.recovery.back-in-stock', $data, $alert->email, 'Back in stock: ' . ($part['sku'] ?: $part['name']) . ' — Universal Driveshafts');
    }

    /** Quote request: notice to the shop (reply-to the customer) + auto-reply to the customer. */
    public function quote(array $q): array
    {
        $to = config('uds.email');   // sales leads go to the shared inbox, not the inventory-alerts address
        $q += ['title' => 'Quote request', 'preheader' => ''];
        $shop = $this->send('emails.recovery.quote-shop', ['q' => $q, 'title' => 'Quote request ' . $q['sku'], 'preheader' => $q['vehicle'] ?: ''], $to,
            'Quote request: ' . $q['sku'] . ($q['vehicle'] ? ' for ' . $q['vehicle'] : ''), $q['email']);
        // the form is anonymous: at most 3 acknowledgements a day to one address, so it can't be used to mail-bomb a stranger
        $cust = !\Illuminate\Support\Facades\RateLimiter::attempt('quote-ack:' . sha1(strtolower($q['email'])), 3, fn () => true, 86400) ? false : $this->send('emails.recovery.quote-customer', ['q' => $q, 'title' => 'We have your quote request', 'preheader' => 'A driveline tech will price ' . $q['sku'] . ' and get back to you.'], $q['email'],
            'Your quote request for ' . $q['sku'] . ' — Universal Driveshafts');
        return ['shop' => $shop, 'customer' => $cust];
    }

    /** Cart line items with absolute image URLs, line totals and a short availability note. */
    public static function describeItems(array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            if (empty($i['name'])) continue;
            $qty = max(1, (int) ($i['qty'] ?? 1));
            $avail = null;
            if (($i['type'] ?? '') === 'product') {
                // the live overlay knows stock; a product it never heard of is checked against the catalog and dropped when gone
                $id = (int) ($i['wp_product_id'] ?? 0);
                $lv = $id ? CatalogService::live($id) : null;
                if ($lv === null) { try { if (!$id || !app(CatalogService::class)->productById($id)) continue; } catch (\Throwable $e) {} }
                if ($lv === null || $lv['in_stock']) $avail = 'In stock, ships from Florida';
            } elseif (($i['type'] ?? '') === 'driveshaft') {
                try { $pp = app(DriveshaftFinder::class)->publicPart((string) ($i['meta']['part_number'] ?? $i['sku'] ?? '')); if (!$pp) continue; } catch (\Throwable $e) {}
                $avail = 'Assembled and balanced in Florida';
            } elseif (in_array($i['type'] ?? '', ['custom_build', 'premade_build'], true)) {
                $avail = 'Built to your measurements in Florida';
            }
            $img = (string) ($i['image'] ?? '');
            $out[] = [
                'name' => (string) $i['name'], 'sku' => (string) ($i['sku'] ?? ''), 'qty' => $qty,
                'price' => (float) ($i['price'] ?? 0), 'line' => round((float) ($i['price'] ?? 0) * $qty, 2),
                'image' => $img !== '' ? url(media_url($img, 160)) : null, 'avail' => $avail,
            ];
        }
        return $out;
    }

    private function firstName(object $row): string
    {
        if (!empty($row->user_id)) {
            $u = \Illuminate\Support\Facades\DB::table('users')->where('id', $row->user_id)->value('name');
            if ($u) return explode(' ', trim($u))[0];
        }
        return 'there';
    }

    /** List-Unsubscribe + List-Unsubscribe-Post for any marketing message. */
    public static function unsubscribeHeaders(string $url): array
    {
        return ['List-Unsubscribe' => '<' . $url . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'];
    }

    private function send(string $view, array $data, string $to, string $subject, ?string $replyTo = null, array $headers = []): bool
    {
        // the customer's language (MailLocale); mail to the shop stays English
        $loc = \App\Support\MailLocale::forRecipient($to);
        if ($loc !== 'en') {
            $subject = \App\Support\MailLocale::tr($subject, $loc);
            $data = \App\Support\MailLocale::data($data, $loc);
        }
        $prev = app()->getLocale();
        try {
            app()->setLocale($loc);
            Mail::send($view, $data, function ($m) use ($to, $subject, $replyTo, $headers) {
                $m->to($to)->subject($subject);
                if ($replyTo) $m->replyTo($replyTo);
                foreach ($headers as $k => $v) $m->getSymfonyMessage()->getHeaders()->addTextHeader($k, $v);
            });
            return true;
        } catch (\Throwable $e) {
            report($e);
            return false;
        } finally {
            app()->setLocale($prev);
        }
    }
}
