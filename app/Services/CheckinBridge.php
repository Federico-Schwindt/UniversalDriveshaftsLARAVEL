<?php

namespace App\Services;

use App\Support\BuildConfig;
use App\Support\OrderEvents;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Online-builder orders → the shop's work order station (uds-checkin on driveshaftsindustries.com).
 *
 * Owner, 2026-11-09: once an order with a builder shaft is committed (paid by card, or placed on terms / pay at
 * pickup), the build goes to the station as a work order whose job number is the order number. One work order per
 * builder line; an order with two shafts gets UDS-…-1 and UDS-…-2.
 *
 * The station has no API, so the rows are written straight into its Postgres (connection `checkin`,
 * CHECKIN_DATABASE_URL) exactly as its own check-in form writes them: customer, entry, parts line, first status row
 * and the activity log line. Its notifications are never triggered from here, and the customer row carries no e-mail
 * and no SMS consent, so the shop's status changes stay internal: the store already confirms, ships and tracks web
 * orders, and the station's "complete and ready for pickup" text would be wrong for a shaft that ships. follow()
 * reads the job status back and tells the customer in the store's own words.
 */
class CheckinBridge
{
    public const TYPES = ['custom_build', 'premade_build'];
    public const ACTOR = 'Online store';

    public static function enabled(): bool
    {
        return (bool) config('database.connections.checkin.url');
    }

    /** Builder lines of an order (the products the station builds). */
    public static function lines(int $orderId)
    {
        return DB::table('order_items')->where('order_id', $orderId)->whereIn('type', self::TYPES)->orderBy('id')->get();
    }

    /**
     * Called when the order is committed (PayLink::placedNotices). The send runs after the response: the station's
     * database can take a few seconds to wake up, and the checkout or the Stripe webhook must never wait on it or
     * fail because of it. A send that does not get through is retried by checkin:push.
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
     * Create the work order(s) for one order. Safe to repeat: a job number already on the station is reused, never
     * created twice. Returns job number => check-in entry id.
     */
    public static function send(int $orderId): array
    {
        $order = DB::table('orders')->find($orderId);
        $lines = $order ? self::lines($orderId) : collect();
        if ($lines->isEmpty()) return [];
        $ck = DB::connection('checkin');
        $out = [];
        foreach ($lines->values() as $i => $item) {
            $job = $lines->count() > 1 ? $order->order_number . '-' . ($i + 1) : $order->order_number;
            $have = $ck->table('entries')->where('job_number', $job)->whereNull('deleted_at')->value('id');
            $out[$job] = (int) ($have ?: $ck->transaction(fn () => self::create($ck, $order, $item, $job, $i + 1, $lines->count())));
        }
        $detail = implode(' · ', array_map(fn ($job, $id) => "Job {$job} = check-in #{$id}", array_keys($out), $out));
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
        $status = DB::connection('checkin')->table('entries')->whereIn('job_number', array_keys($jobs))->whereNull('deleted_at')->pluck('job_status', 'job_number')->all();
        $started = $built = 0;
        foreach ($orders as $o) {
            $mine = array_map(fn ($job) => $status[$job] ?? null, array_keys($jobs, (int) $o->id, true));
            if (in_array(null, $mine, true)) continue;   // a job deleted on the station: the shop handles that order by hand
            if (count(array_filter($mine, fn ($s) => $s === 'Completed')) === count($mine)) { self::built($o); $built++; }
            elseif (array_intersect($mine, ['Ongoing', 'Completed']) && OrderEvents::record($o, 'build:started', 'Build started at the shop station', null, true)) { self::started($o); $started++; }
        }
        return [$started, $built];
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

    private static function create(Connection $ck, object $o, object $item, string $job, int $k, int $n): int
    {
        $meta = json_decode((string) $item->meta, true) ?: [];
        $c = (array) ($meta['config'] ?? []);
        $premade = $item->type === 'premade_build';
        $two = ($c['pieces'] ?? 'one') === 'two';
        $lineTotal = round((float) ($item->line_total ?? $item->price * $item->qty), 2);
        $name = trim($o->billing_first_name . ' ' . $o->billing_last_name);
        $customerId = self::customer($ck, $o);

        $entryId = $ck->table('entries')->insertGetId([
            'location_id' => $ck->table('locations')->orderBy('id')->value('id'),   // the station's own default
            'customer_id' => $customerId,
            'notes' => self::sheet($o, $item, $c, $k, $n),
            'labor_request' => $premade ? 'New Driveshaft' : 'Custom Made Driveshaft',
            'driveshaft_pieces' => $two ? '2 Pieces' : '1 Piece',
            'driveshaft_material' => $premade ? '' : ucfirst((string) ($c['material'] ?? '')),
            'designer_type' => $two ? 'Two Piece' : 'One Piece',
            'measurement_a' => self::inches($premade ? ($c['premade_a'] ?? '') : ($c['a1'] ?? '')),
            'measurement_b' => self::inches($premade ? ($c['premade_b'] ?? '') : ($c['b1'] ?? '')),
            'measurement_c' => $premade ? '' : self::inches($c['c1'] ?? ''),
            'total' => number_format($lineTotal, 2, '.', ''),
            'job_number' => $job,
            'job_status' => 'New',
            'payment' => in_array($o->payment_status, ['paid', 'partially_refunded'], true) ? 'Paid' : 'Unpaid',
            'custom' => json_encode(self::info($o, $item, $k, $n), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by_name' => self::ACTOR,
        ]);
        $ck->table('parts')->insert([
            'entry_id' => $entryId, 'sku' => (string) ($item->sku ?? ''), 'name' => (string) $item->name,
            'qty' => max(1, (int) $item->qty), 'price' => number_format((float) $item->price, 2, '.', ''),
        ]);
        $ck->table('status_history')->insert(['entry_id' => $entryId, 'from_status' => '', 'to_status' => 'New', 'changed_by' => self::ACTOR]);
        $ck->table('activity_log')->insert([
            'action' => 'entry.create', 'entity' => 'entry', 'entity_id' => $entryId,
            'summary' => "New check-in #{$entryId} — {$name} (Job {$job}) · online order",
            'details' => json_encode(['source' => 'online store', 'order' => $o->order_number, 'jobStatus' => 'New', 'createdBy' => self::ACTOR]),
            'actor' => self::ACTOR, 'ip' => '',
        ]);
        return (int) $entryId;
    }

    /**
     * The station's customer row, kept silent (no e-mail, no SMS consent). A returning web customer reuses their row;
     * a walk-in customer's row is never borrowed, or the shop's status changes could text them about this shaft.
     */
    private static function customer(Connection $ck, object $o): int
    {
        $ship = $o->ship_to_different && $o->shipping_address_1;
        $p = $ship ? 'shipping_' : 'billing_';
        $row = [
            'first_name' => trim((string) $o->billing_first_name), 'last_name' => trim((string) $o->billing_last_name),
            'phone' => substr(preg_replace('/\D/', '', (string) $o->phone), -10),
            'company_name' => trim((string) $o->billing_company),
            'street' => (string) $o->{$p . 'address_1'}, 'address2' => (string) $o->{$p . 'address_2'},
            'city' => (string) $o->{$p . 'city'}, 'state' => (string) $o->{$p . 'state'}, 'zip' => (string) $o->{$p . 'postcode'},
            'country' => self::country((string) $o->{$p . 'country'}),
        ];
        $id = $ck->table('customers')->where('phone', $row['phone'])->where('first_name', $row['first_name'])->where('last_name', $row['last_name'])
            ->where('email', '')->where('sms_consent', false)->value('id');
        if ($id) {
            $ck->table('customers')->where('id', $id)->update($row + ['updated_at' => now('UTC')->toDateTimeString()]);
            return (int) $id;
        }
        return (int) $ck->table('customers')->insertGetId($row + ['email' => '', 'sms_consent' => false]);
    }

    /** The build sheet in the work order's "Notes" box: what to build, line by line. */
    private static function sheet(object $o, object $item, array $c, int $k, int $n): string
    {
        $head = 'ONLINE ORDER ' . $o->order_number . ($n > 1 ? " (shaft {$k} of {$n})" : '');
        $line = $item->name . '  ×' . (int) $item->qty . '  ($' . number_format((float) $item->price, 2) . ' each)';
        $rows = array_map(fn ($r) => $r['label'] . ': ' . $r['value'], BuildConfig::rows($c));
        return implode("\n", array_filter([$head, $line, '', ...$rows, $o->customer_note ? "\nOrder note: " . trim($o->customer_note) : null], fn ($v) => $v !== null));
    }

    /** "Additional information" on the work order: where the job came from and where it goes. */
    private static function info(object $o, object $item, int $k, int $n): array
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
        return array_filter([
            'Online order' => $o->order_number . ($n > 1 ? " · shaft {$k} of {$n}" : ''),
            'Ordered' => \Carbon\Carbon::parse($o->created_at, 'UTC')->timezone('America/New_York')->format('M j, Y g:i A') . ' EST',
            'Quantity' => (string) (int) $item->qty,
            'Customer e-mail' => (string) $o->email,
            'Payment' => $pay,
            'Delivery' => $pickup ? 'Local pickup' : (string) ($o->shipping_label ?: 'Ship'),
            'Ship to' => $pickup ? null : $address,
            'Order total' => '$' . number_format((float) $o->total, 2),
        ], fn ($v) => $v !== null && $v !== '');
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
