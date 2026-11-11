<?php

namespace App\Services;

use App\Support\BuildConfig;
use App\Support\OrderEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Online-builder orders → the shop's work order station (uds-checkin on driveshaftsindustries.com).
 *
 * Owner, 2026-11-09: once an order with a builder shaft is committed (paid by card, or placed on terms / pay at
 * pickup), the build goes to the station as a work order whose job number is the order number. One work order per
 * builder line; an order with two shafts gets UDS-…-1 and UDS-…-2.
 *
 * Owner, 2026-11-11 (option 1): the work orders go through the station's own signed integration API (CheckinApi →
 * uds-checkin src/lib/integration.ts). The store holds a shared secret only, never the station's database credentials,
 * and the station writes the customer, entry, parts, first status row and audit line itself, in one transaction, with
 * its own validation. Web customers stay silent there (no e-mail, no SMS consent, enforced by the station), so the
 * shop's status changes never text them: the store already confirms, ships and tracks web orders, and the station's
 * "complete and ready for pickup" would be wrong for a shaft that ships. follow() reads the job status back and tells
 * the customer in the store's own words.
 *
 * Owner, 2026-11-11: the station is for custom driveshafts only — the online builder's shafts, never component or
 * catalog orders — and each work order carries everything the builder knows about the shaft (the answers, series,
 * tube, ends, yokes and kit with their SKUs as a pick list, the builder's own measurements by name, the speed check,
 * a link that reopens the build with its drawing). sync() then keeps the work order in step with the order.
 */
class CheckinBridge
{
    /** Only online-builder custom shafts go to the station (owner, 2026-11-11: "solo para custom driveshafts"). */
    public const TYPES = ['custom_build'];

    public static function enabled(): bool
    {
        return CheckinApi::configured();
    }

    /** Builder lines of an order (the products the station builds). */
    public static function lines(int $orderId)
    {
        return DB::table('order_items')->where('order_id', $orderId)->whereIn('type', self::TYPES)->orderBy('id')->get();
    }

    /**
     * Called when the order is committed (PayLink::placedNotices). The send runs after the response: the station can
     * take a few seconds to wake up, and the checkout or the Stripe webhook must never wait on it or fail because of
     * it. A send that does not get through is retried by checkin:push.
     */
    public static function queue(object $order): void
    {
        if (!self::enabled() || OrderEvents::imported($order) || self::lines((int) $order->id)->isEmpty()) return;
        if (!OrderEvents::record($order, 'checkin:queued', 'Work order queued for the shop station', null, true)) return;
        $id = (int) $order->id;
        app()->terminating(function () use ($id) { self::deliver($id); });
    }

    /** send() with the outcome on the order timeline; false when the station could not be reached. */
    public static function deliver(int $orderId): bool
    {
        try {
            self::send($orderId);
            return true;
        } catch (\Throwable $e) {
            report($e);
            OrderEvents::record($orderId, 'checkin:failed', 'Work order not delivered to the shop station', Str::limit($e->getMessage(), 180) . ' (retried by checkin:push)');
            return false;
        }
    }

    /**
     * Create the work order(s) for one order. Safe to repeat: the station creates a job number once and answers a
     * repeat with the existing work order. Returns job number => check-in entry id.
     */
    public static function send(int $orderId): array
    {
        $order = DB::table('orders')->find($orderId);
        $lines = $order ? self::lines($orderId) : collect();
        if ($lines->isEmpty()) return [];
        $out = []; $gone = [];
        foreach ($lines->values() as $i => $item) {
            $job = $lines->count() > 1 ? $order->order_number . '-' . ($i + 1) : $order->order_number;
            $payload = self::payload($order, $item, $i + 1, $lines->count());
            $res = CheckinApi::put($job, $payload);
            $out[$job] = (int) $res['id'];
            if (!empty($res['deleted'])) { $gone[] = $job; continue; }   // the shop deleted it on purpose: never recreated
            // already there: its build card is brought up to date (the notes stay the shop's)
            if (empty($res['created'])) CheckinApi::patch($job, ['order' => $order->order_number, 'build' => $payload['entry']['build']]);
        }
        $detail = implode(' · ', array_map(fn ($job, $id) => "Job {$job} = check-in #{$id}" . (in_array($job, $gone, true) ? ' (deleted at the shop)' : ''), array_keys($out), $out));
        OrderEvents::record($order, 'checkin:sent', 'Work order sent to the shop station', $detail, true);
        return $out;
    }

    /** Job number => order id for the given orders (one job per builder line, as send() numbers them). */
    private static function jobs(iterable $orders): array
    {
        $jobs = [];
        foreach ($orders as $o) {
            $lines = self::lines((int) $o->id);
            foreach ($lines->values() as $i => $l) $jobs[$lines->count() > 1 ? $o->order_number . '-' . ($i + 1) : $o->order_number] = (int) $o->id;
        }
        return $jobs;
    }

    /**
     * The station's progress, told to the customer in the store's words (owner, 2026-11-09). The station's own texts
     * say "your driveshaft is complete and ready for pickup", which is wrong for a web order that ships, so it stays
     * silent (see the class note) and this reads the job status instead: the first job the shop starts (Ongoing) →
     * "we started building your driveshaft"; every job of the order Completed → "it is built and ships soon", or the
     * store's usual "ready for pickup" for a local-pickup order. Each message once per order. Returns [started, built].
     */
    public static function follow(int $days = 60): array
    {
        $orders = DB::table('orders as o')->where('o.status', '!=', 'cancelled')
            ->whereExists(fn ($s) => $s->from('order_events as e')->whereColumn('e.order_id', 'o.id')->where('e.type', 'checkin:sent')->where('e.created_at', '>=', now()->subDays($days)))
            ->whereNotExists(fn ($s) => $s->from('order_events as e')->whereColumn('e.order_id', 'o.id')->where('e.type', 'build:done'))
            ->select('o.*')->get();
        if ($orders->isEmpty()) return [0, 0];
        $jobs = self::jobs($orders);
        $status = array_map(fn ($e) => empty($e['deleted']) ? (string) $e['status'] : null, CheckinApi::statuses(array_keys($jobs)));
        $started = $built = 0;
        foreach ($orders as $o) {
            $mine = array_map(fn ($job) => $status[$job] ?? null, array_keys($jobs, (int) $o->id, true));
            if (in_array(null, $mine, true)) continue;   // a job deleted on the station: the shop handles that order by hand
            if (count(array_filter($mine, fn ($s) => $s === 'Completed')) === count($mine)) { self::built($o); $built++; }
            elseif (array_intersect($mine, ['Ongoing', 'Completed']) && OrderEvents::record($o, 'build:started', 'Build started at the shop station', null, true)) { self::started($o); $started++; }
        }
        return [$started, $built];
    }

    /**
     * Keeps each work order in step with its order after the send (checkin:push, every 10 min): paid later → Paid;
     * cancelled or refunded here → On hold with a DO NOT BUILD line (never deleted: the shop may have started; a
     * shaft already Completed only gets the line); shipped or completed here → Completed. The station applies each
     * change against its current row, writes its status history and audit trail and sends nobody a message; this
     * only asks when the station's answer shows something to change. Returns how many work orders changed.
     */
    public static function sync(int $days = 90): int
    {
        $orders = DB::table('orders as o')
            ->whereExists(fn ($s) => $s->from('order_events as e')->whereColumn('e.order_id', 'o.id')->where('e.type', 'checkin:sent')->where('e.created_at', '>=', now()->subDays($days)))
            ->select('o.*')->get();
        if ($orders->isEmpty()) return 0;
        $jobs = self::jobs($orders);
        $now = CheckinApi::statuses(array_keys($jobs));
        $changed = 0;
        foreach ($orders as $o) {
            $stop = in_array($o->status, ['cancelled', 'refunded'], true) || $o->payment_status === 'refunded';
            $paid = in_array($o->payment_status, ['paid', 'partially_refunded'], true);
            $done = !$stop && ($o->shipped_at || $o->status === 'completed');
            foreach (array_keys($jobs, (int) $o->id, true) as $job) {
                $e = $now[$job] ?? null;
                if (!$e || !empty($e['deleted'])) continue;   // deleted on the station: the shop handles it by hand
                $update = array_filter([
                    'payment' => $paid && !$stop && ($e['payment'] ?? '') !== 'Paid' ? 'Paid' : null,
                    'cancel' => $stop && empty($e['cancelled']) ? ['reason' => $o->status === 'cancelled' ? 'cancelled' : 'refunded'] : null,
                    'complete' => $done && ($e['status'] ?? '') !== 'Completed' && empty($e['cancelled'])
                        ? ['note' => $o->shipped_at ? 'Shipped from the online store' . ($o->tracking_number ? ' (' . trim($o->tracking_carrier . ' ' . $o->tracking_number) . ')' : '') : 'Order completed in the online store']
                        : null,
                ]);
                if (!$update) continue;
                $res = CheckinApi::patch($job, ['order' => $o->order_number] + $update);
                if (empty($res['changed'])) continue;
                OrderEvents::record($o, 'checkin:updated', 'Shop work order updated', "Job {$job}: " . implode(', ', (array) $res['changed']) . ' → ' . ($res['status'] ?? ''));
                $changed++;
            }
        }
        return $changed;
    }

    private static function started(object $o): void
    {
        $url = OrderEvents::statusUrl($o);
        $pickup = OrderEvents::isPickup($o);
        $next = $pickup ? 'We will let you know as soon as it is ready for pickup.' : 'We will let you know when it is finished, and again with the tracking number once it ships.';
        if (OrderEvents::record($o, 'notify:email:build_started', 'Build-started e-mail sent', $o->email, true)) {
            try { \App\Support\Mailer::simple($o->email, "We started building your driveshaft — order {$o->order_number}", ['kicker' => 'In production', 'title' => "Your driveshaft is on the bench, {$o->billing_first_name}", 'intro' => "Our technicians started building the driveshaft for order {$o->order_number}.", 'paragraphs' => [$next], 'button' => ['Order status', $url]], ['replyTo' => [config('uds.email')]]); } catch (\Throwable $e) { report($e); }
        }
        if ($o->sms_opt_in && $o->phone && OrderEvents::record($o, 'notify:sms:build_started', 'Build-started text sent', $o->phone, true)) {
            try { app(SmsService::class)->send($o->phone, "Universal Driveshafts: we started building your driveshaft for order {$o->order_number}. We'll let you know when it's finished. Status: {$url}"); } catch (\Throwable $e) {}
        }
    }

    private static function built(object $o): void
    {
        if (!OrderEvents::record($o, 'build:done', 'Driveshaft built at the shop station', null, true)) return;
        if (OrderEvents::isPickup($o)) { OrderEvents::notifyPickupReady($o); return; }   // same once-keys as the admin's "completed"
        if ($o->shipped_at) return;   // already on its way: the shipped message said it all
        $url = OrderEvents::statusUrl($o);
        if (OrderEvents::record($o, 'notify:email:build_done', 'Build-finished e-mail sent', $o->email, true)) {
            try { \App\Support\Mailer::simple($o->email, "Your driveshaft is built — order {$o->order_number}", ['kicker' => 'Built and inspected', 'title' => "Your driveshaft is finished, {$o->billing_first_name}", 'intro' => "The driveshaft for order {$o->order_number} is built and inspected.", 'paragraphs' => ['It ships soon. We will e-mail you the tracking number as soon as it is on its way.'], 'button' => ['Order status', $url]], ['replyTo' => [config('uds.email')]]); } catch (\Throwable $e) { report($e); }
        }
        if ($o->sms_opt_in && $o->phone && OrderEvents::record($o, 'notify:sms:build_done', 'Build-finished text sent', $o->phone, true)) {
            try { app(SmsService::class)->send($o->phone, "Universal Driveshafts: your driveshaft for order {$o->order_number} is built and inspected. It ships soon; we'll send you the tracking number. Status: {$url}"); } catch (\Throwable $e) {}
        }
    }

    /** What the station gets for one builder line (it validates and stores it, see uds-checkin src/lib/integration.ts). */
    private static function payload(object $o, object $item, int $k, int $n): array
    {
        $meta = json_decode((string) $item->meta, true) ?: [];
        $c = (array) ($meta['config'] ?? []);
        $two = ($c['pieces'] ?? 'one') === 'two';
        $ship = $o->ship_to_different && $o->shipping_address_1;
        $p = $ship ? 'shipping_' : 'billing_';
        return [
            'order' => (string) $o->order_number,
            // the station keeps web customers silent whatever is sent; no e-mail goes over
            'customer' => [
                'firstName' => trim((string) $o->billing_first_name) ?: 'Web', 'lastName' => trim((string) $o->billing_last_name) ?: 'customer',
                'phone' => substr(preg_replace('/\D/', '', (string) $o->phone), -10), 'companyName' => Str::limit(trim((string) $o->billing_company), 120, ''),
                'street' => (string) $o->{$p . 'address_1'}, 'address2' => (string) $o->{$p . 'address_2'}, 'city' => (string) $o->{$p . 'city'},
                'state' => (string) $o->{$p . 'state'}, 'zip' => (string) $o->{$p . 'postcode'}, 'country' => self::country((string) $o->{$p . 'country'}),
            ],
            'entry' => [
                'notes' => self::summary($o, $item, $c, $k, $n),
                'laborRequest' => 'Custom Made Driveshaft',
                'driveshaftPieces' => $two ? '2 Pieces' : '1 Piece',
                'driveshaftMaterial' => ucfirst((string) ($c['material'] ?? '')),
                'designerType' => $two ? 'Two Piece' : 'One Piece',
                'measurementA' => self::inches($c['a1'] ?? ''),
                'measurementB' => self::inches($c['b1'] ?? ''),
                'measurementC' => self::inches($c['c1'] ?? ''),
                'total' => round((float) ($item->line_total ?? $item->price * $item->qty), 2),
                'payment' => in_array($o->payment_status, ['paid', 'partially_refunded'], true) ? 'Paid' : 'Unpaid',
                // the builder's drawing with the customer's dimensions, shown on the work order as its product image
                'productImage' => (string) (\App\Support\BuildDrawing::url($c) ?? ''),
                // everything else is the "Online build" card, grouped for the bench (owner, 2026-11-11: "necesito todo más organizado");
                // no flat "custom" list any more (and an empty PHP array would go out as [] where the station wants an object)
                'build' => self::build($o, $item, $c, $k, $n),
            ],
            'parts' => self::parts($item, $c, (array) ($meta['price'] ?? [])),
        ];
    }

    /**
     * The work order's notes: a few lines to read at a glance (what, ends, length, yokes, the customer's words). The full
     * build is the "Online build" card; the notes stay short so the shop can add its own.
     */
    private static function summary(object $o, object $item, array $c, int $k, int $n): string
    {
        $rows = [];
        foreach (BuildConfig::rows($c) as $r) $rows[$r['label']] = $r['value'];
        $shaft = implode(' · ', array_filter([
            $rows['Series'] ?? null,
            trim(($rows['Tube material'] ?? '') . (isset($rows['Tube OD']) ? ' ' . $rows['Tube OD'] . ' tube' : ''), ' ') ?: null,
            $rows['Component grade'] ?? null,
        ]));
        $ends = trim(($rows['Front end'] ?? '') . ' → ' . ($rows['Rear end'] ?? ''), ' →');
        $len = implode(' · ', array_filter([isset($rows['Measurement A1']) ? 'A1 ' . $rows['Measurement A1'] : null, isset($rows['Measurement C1']) ? 'C1 ' . $rows['Measurement C1'] : null]));
        $lines = [
            'ONLINE ORDER ' . $o->order_number . ($n > 1 ? " (shaft {$k} of {$n})" : '') . ' — custom driveshaft from the online builder. Every detail is in the Online build card.',
            $shaft, $ends, $len,
        ];
        foreach (['Front yoke', 'Rear yoke', 'Rear u-joint hardware'] as $l) if (!empty($rows[$l])) $lines[] = "{$l}: {$rows[$l]}";
        if (!empty($rows['Customer notes'])) $lines[] = 'Customer notes: ' . $rows['Customer notes'];
        if (trim((string) $o->customer_note) !== '') $lines[] = 'Order note: ' . trim((string) $o->customer_note);
        return implode("\n", array_filter($lines, fn ($l) => trim((string) $l) !== ''));
    }

    /**
     * The "Online build" card on the work order: the drawing, the figures that matter at a glance, then the build grouped
     * the way the bench reads it — application, shaft, ends and yokes, measurements, the customer's notes, the order.
     */
    private static function build(object $o, object $item, array $c, int $k, int $n): array
    {
        $answers = array_map(fn ($qa) => (string) (is_array($qa) ? ($qa['q'] ?? '') : ''), (array) ($c['answers'] ?? []));
        $shaft = ['Configuration', 'Duty rating', 'Series', 'Tube material', 'Tube OD', 'Component grade'];
        // in this order, whatever order the build sheet lists them in
        $measure = ['Measurement A1' => 'A1 · overall length', 'Measurement C1' => 'C1 · u-joint center to center', 'Measurement B1' => 'B1', 'Overall length' => 'Overall length', 'Critical speed check' => 'Critical speed'];
        $groups = ['Application' => [], 'Shaft' => [], 'Ends and yokes' => [], 'Measurements' => [], 'Customer notes' => []];
        $byLabel = [];
        foreach (BuildConfig::rows($c) as $r) {
            [$l, $v] = [$r['label'], $r['value']];
            $byLabel[$l] = $v;
            if ($l === 'Build') continue;
            if (isset($measure[$l])) continue;
            $g = match (true) {
                in_array($l, $answers, true), in_array($l, ['Vehicle', 'Shaft'], true) => 'Application',
                in_array($l, $shaft, true) => 'Shaft',
                $l === 'Customer notes' => 'Customer notes',
                default => 'Ends and yokes',
            };
            $groups[$g][] = [$l, $v];
        }
        foreach ($measure as $l => $label) if (isset($byLabel[$l])) $groups['Measurements'][] = [$label, $byLabel[$l]];
        if (trim((string) $o->customer_note) !== '') $groups['Customer notes'][] = ['Order note', trim((string) $o->customer_note)];
        $groups['Order'] = self::orderRows($o, $item, $k, $n);
        $sections = [];
        foreach ($groups as $title => $rows) if ($rows) $sections[] = ['title' => $title, 'rows' => array_map(fn ($r) => [Str::limit($r[0], 80, ''), Str::limit($r[1], 600)], array_slice($rows, 0, 30))];

        $two = ($c['pieces'] ?? 'one') === 'two';
        $highlights = array_values(array_filter([
            isset($byLabel['Measurement A1']) ? ['label' => 'A1', 'value' => $byLabel['Measurement A1']] : null,
            isset($byLabel['Measurement C1']) ? ['label' => 'C1', 'value' => $byLabel['Measurement C1']] : null,
            !empty($c['series_key']) ? ['label' => 'Series', 'value' => (string) $c['series_key'] . (!empty($c['series_variant']) ? ' (' . $c['series_variant'] . ')' : '')] : null,
            !empty($c['material']) ? ['label' => 'Tube', 'value' => trim(ucfirst((string) $c['material']) . ' ' . ($byLabel['Tube OD'] ?? ''))] : null,
            ['label' => 'Type', 'value' => $two ? '2 pieces' : '1 piece'],
            ['label' => 'Qty', 'value' => (string) max(1, (int) $item->qty)],
        ]));
        return [
            'title' => Str::limit((string) $item->name, 200, ''),
            'subtitle' => 'Order ' . $o->order_number . ($n > 1 ? " · shaft {$k} of {$n}" : '') . ' · $' . number_format((float) $item->price, 2) . ((int) $item->qty > 1 ? ' each' : ''),
            'highlights' => array_map(fn ($h) => ['label' => Str::limit($h['label'], 40, ''), 'value' => Str::limit($h['value'], 80, '')], array_slice($highlights, 0, 8)),
            'sections' => $sections,
            'links' => ['build' => (string) (self::buildUrl($c) ?? ''), 'drawing' => (string) (\App\Support\BuildDrawing::url($c) ?? '')],
            'note' => 'A1 and C1 are shaft measurements from the online builder: A1 = overall length, from the flange face or the end of the slip yoke (pushed fully in) to the far end; C1 = u-joint center to u-joint center. They are not the A/B/C vehicle measurements of the check-in form.',
        ];
    }

    /** The order side of the card: where the job came from, how it is paid, where it goes. [[label, value], …] */
    private static function orderRows(object $o, object $item, int $k, int $n): array
    {
        $pickup = OrderEvents::isPickup($o);
        $ship = $o->ship_to_different && $o->shipping_address_1;
        $p = $ship ? 'shipping_' : 'billing_';
        $address = implode(', ', array_filter([
            trim($o->{$p . 'first_name'} . ' ' . $o->{$p . 'last_name'}), $o->{$p . 'company'}, $o->{$p . 'address_1'}, $o->{$p . 'address_2'},
            trim($o->{$p . 'city'} . ', ' . $o->{$p . 'state'} . ' ' . $o->{$p . 'postcode'}, ', '), $o->{$p . 'country'} !== 'US' ? $o->{$p . 'country'} : null,
        ]));
        $pay = match (true) {
            $o->payment_method === 'net_terms' => 'Net terms invoice' . ($o->po_number ? ' · PO ' . $o->po_number : ''),
            $o->payment_method === 'pay_at_pickup' => 'Pays at pickup',
            in_array($o->payment_status, ['paid', 'partially_refunded'], true) => 'Paid online',
            default => 'Not paid yet',
        };
        $others = DB::table('order_items')->where('order_id', $o->id)->whereNotIn('type', self::TYPES)->sum('qty');
        $rows = [
            ['Online order', $o->order_number . ($n > 1 ? " · shaft {$k} of {$n}" : '')],
            ['Ordered', \Carbon\Carbon::parse($o->created_at, 'UTC')->timezone('America/New_York')->format('M j, Y g:i A') . ' EST'],
            ['Quantity', (string) (int) $item->qty],
            ['Shaft price', '$' . number_format((float) $item->price, 2) . ((int) $item->qty > 1 ? ' each' : '')],
            ['Payment', $pay],
            // a pickup customer pays the whole order at the counter; other parts on a web order are the store's to pack
            ['Collect at pickup', $o->payment_method === 'pay_at_pickup' && $o->payment_status !== 'paid' ? '$' . number_format((float) $o->total, 2) . ' (whole order)' : ''],
            ['Delivery', $pickup ? 'Local pickup' : (string) ($o->shipping_label ?: 'Ship')],
            ['Ship to', $pickup ? '' : $address],
            ['Customer e-mail', (string) $o->email],
            ['Customer phone', (string) $o->phone],
            ['Other items on the order', $others > 0 ? (int) $others . ' catalog part(s), packed by the store' : ''],
        ];
        return array_values(array_filter($rows, fn ($r) => trim((string) $r[1]) !== ''));
    }

    /**
     * The work order's parts: the shaft, then each yoke and kit the customer chose (SKU, so the shop can pull it), priced
     * from the builder's own breakdown so the lines add up to the shaft price. A yoke the customer sends in is listed at $0.
     */
    private static function parts(object $item, array $c, array $price): array
    {
        $qty = max(1, (int) $item->qty);
        $cat = app(DsbCatalog::class);
        $line = fn (string $sku, string $name, float $each) => ['sku' => Str::limit($sku, 80, ''), 'name' => Str::limit($name, 250, ''), 'qty' => $qty, 'price' => round($each, 2)];
        $extra = [];
        foreach (['front_yoke' => ['Front yoke', 'front_addon'], 'rear_yoke' => ['Rear yoke', 'rear_addon'], 'hardware' => ['U-joint kit', 'hardware_addon']] as $key => [$label, $addon]) {
            $y = (array) ($c[$key] ?? []); $kind = (string) ($y['kind'] ?? 'none'); $pid = (int) ($y['product_id'] ?? 0);
            if ($kind === 'none' || $kind === '') continue;
            $each = round((float) ($price[$addon] ?? 0), 2);
            $extra[] = match (true) {
                $pid > 0 => $line($cat->productSku($pid), $label . ': ' . ($cat->productName($pid) ?? ('#' . $pid)), $each),
                $kind === 'own' => $line('', $label . ': customer\'s own (sent in or brought to the shop)', 0),
                $kind === 'used' => $line('', $label . ': good used yoke from our shop', $each),
                default => $line('', $label . ': ' . $kind, $each),
            };
        }
        $base = round((float) $item->price - array_sum(array_map(fn ($p) => (float) $p['price'], $extra)), 2);
        return [$line((string) ($item->sku ?? ''), (string) $item->name, max(0, $base)), ...$extra];
    }

    /** A link that reopens this build in the online builder, on its review page with the drawing and every dimension. */
    private static function buildUrl(array $c): ?string
    {
        if (empty($c['series_key'])) return null;
        $y = fn ($k) => (array) ($c[$k] ?? []);
        $q = array_filter([
            'dsb_fam' => $c['family'] ?? '', 'dsb_series' => $c['series_key'] ?? '', 'dsb_var' => $c['series_variant'] ?? '', 'dsb_material' => $c['material'] ?? '',
            'dsb_front' => $c['front_app'] ?? '', 'dsb_rear' => $c['rear_app'] ?? '',
            'dsb_fy' => in_array($y('front_yoke')['kind'] ?? '', ['new', 'used', 'own'], true) ? $y('front_yoke')['kind'] : '', 'dsb_fyid' => (int) ($y('front_yoke')['product_id'] ?? 0) ?: '',
            'dsb_fmod' => $y('front_yoke')['model'] ?? '',
            'dsb_ry' => in_array($y('rear_yoke')['kind'] ?? '', ['new', 'used', 'own'], true) ? $y('rear_yoke')['kind'] : '', 'dsb_ryid' => (int) ($y('rear_yoke')['product_id'] ?? 0) ?: '',
            'dsb_hw' => in_array($y('hardware')['kind'] ?? '', ['strap', 'ubolt', 'own'], true) ? $y('hardware')['kind'] : '', 'dsb_hwid' => (int) ($y('hardware')['product_id'] ?? 0) ?: '',
            'dsb_a1' => (float) ($c['a1'] ?? 0) > 0 ? (string) (float) $c['a1'] : '', 'dsb_c1' => (float) ($c['c1'] ?? 0) > 0 ? (string) (float) $c['c1'] : '',
            'dsb_tier' => ($c['price_tier'] ?? '') === 'new' ? 'new' : '', 'dsb_seen' => 'so', 'dsb_step' => 'review',
            'dsb_mph' => (float) ($c['speed']['mph'] ?? 0) > 0 ? $c['speed']['mph'] : '', 'dsb_ratio' => (float) ($c['speed']['ratio'] ?? 0) > 0 ? $c['speed']['ratio'] : '',
            'dsb_tire' => (float) ($c['speed']['tire'] ?? 0) > 0 ? $c['speed']['tire'] : '',
        ], fn ($v) => $v !== '' && $v !== null);
        // the supplier options the price came from (as the builder's own link carries them: dsb_o0=name=value)
        $i = 0;
        foreach ((array) ($c['am_options'] ?? []) as $name => $value) if ($i < 6 && (string) $value !== '') $q['dsb_o' . $i++] = $name . '=' . $value;
        return rtrim((string) config('app.url'), '/') . '/online-builder?' . http_build_query($q);
    }

    private static function inches($v): string
    {
        if (is_numeric($v)) return (float) $v > 0 ? rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.') . '"' : '';
        $v = trim((string) $v);
        return $v === '' || preg_match('/["\']|in\b/i', $v) ? $v : $v . '"';
    }

    private static function country(string $code): string
    {
        return match (strtoupper($code)) { '', 'US' => 'United States', 'CA' => 'Canada', 'MX' => 'Mexico', 'PR' => 'Puerto Rico', default => $code };
    }
}
