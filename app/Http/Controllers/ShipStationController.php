<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\OrderMailer;
use App\Services\ProductService;
use App\Support\OrderEvents;
use App\Support\OrderTracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ShipStation "Custom Store" (owner, 2026-11-14: "necesito que los pedidos se exporten a shipstation igualmente, nos
 * quedó balance y los queremos usar antes de usar easypost"). ShipStation calls one URL with HTTP Basic auth:
 * - action=export: the orders changed between start_date and end_date (UTC, MM/dd/yyyy HH:mm), as its XML. Only
 *   orders placed on this site since the launch that ship (no store pickup), once paid or on Net terms; an order
 *   already sent keeps being exported so ShipStation also learns when it shipped or was cancelled.
 * - action=shipnotify: a label was bought in ShipStation; the order is marked shipped with that tracking and the
 *   store sends the customer its usual shipped e-mail (once per order, OrderTracking::markShipped).
 * Statuses the owner maps in ShipStation: paid → Awaiting Shipment, shipped → Shipped, cancelled → Cancelled,
 * on_hold → On Hold. Credentials: SHIPSTATION_STORE_USER / SHIPSTATION_STORE_PASSWORD (off while either is empty).
 */
class ShipStationController extends Controller
{
    private const PER_PAGE = 50;

    public function handle(Request $r)
    {
        $user = (string) config('services.shipstation.store_user');
        $pass = (string) config('services.shipstation.store_password');
        if ($user === '' || $pass === '') return response('ShipStation store not configured', 503);
        // Basic auth (what ShipStation sends); the SS-UserName / SS-Password query form of older Custom Store setups too
        $gotUser = (string) ($r->getUser() ?? $r->query('SS-UserName', ''));
        $gotPass = (string) ($r->getPassword() ?? $r->query('SS-Password', ''));
        if (!hash_equals($user, $gotUser) || !hash_equals($pass, $gotPass)) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="ShipStation"']);
        }
        return match ((string) $r->query('action')) {
            'export' => $this->export($r),
            'shipnotify' => $this->shipnotify($r),
            default => response('Unknown action', 400),
        };
    }

    /** ShipStation's dates: UTC, "MM/dd/yyyy HH:mm". */
    private static function date(?string $s): ?\Carbon\Carbon
    {
        foreach (['m/d/Y H:i', 'm/d/Y H:i:s', 'm/d/Y'] as $f) {
            try { return \Carbon\Carbon::createFromFormat($f, trim((string) $s), 'UTC'); } catch (\Throwable $e) {}
        }
        return null;
    }

    /** The orders ShipStation should know about (see the class note). */
    public static function exportable()
    {
        return DB::table('orders')
            ->where(fn ($w) => $w->whereNull('source')->orWhere('source', '!=', 'woocommerce'))   // old-store orders were in ShipStation already
            ->where('created_at', '>=', (string) config('services.shipstation.since', '2026-11-13 21:13:00'))
            ->where(fn ($w) => $w->whereNull('shipping_method')->orWhere('shipping_method', '!=', CartService::PICKUP_ID))
            ->where(fn ($w) => $w->where('payment_status', 'paid')->orWhere('payment_method', 'net_terms')->orWhereNotNull('shipstation_pushed_at'));
    }

    /** Our status in the words the owner maps in ShipStation. */
    public static function status(object $o): string
    {
        if (in_array($o->status, ['cancelled', 'refunded', 'failed'], true)) return 'cancelled';
        if ($o->shipped_at || $o->status === 'completed') return 'shipped';
        if ($o->status === 'on-hold') return 'on_hold';
        return 'paid';
    }

    private function export(Request $r)
    {
        $from = self::date($r->query('start_date'));
        $to = self::date($r->query('end_date'));
        if (!$from || !$to) return response('start_date and end_date are required (MM/dd/yyyy HH:mm, UTC)', 400);
        $q = self::exportable()->whereBetween('updated_at', [$from->format('Y-m-d H:i:00'), $to->format('Y-m-d H:i:59')]);
        $total = (clone $q)->count();
        $page = max(1, (int) $r->query('page', 1));
        $orders = $q->orderBy('id')->forPage($page, self::PER_PAGE)->get();

        $xml = new \DOMDocument('1.0', 'utf-8');
        $root = $xml->appendChild($xml->createElement('Orders'));
        $root->setAttribute('pages', (string) max(1, (int) ceil($total / self::PER_PAGE)));
        $add = function (\DOMElement $parent, string $name, $value, bool $cdata = true) use ($xml) {
            $el = $parent->appendChild($xml->createElement($name));
            $value = (string) ($value ?? '');
            $el->appendChild($cdata ? $xml->createCDATASection($value) : $xml->createTextNode($value));
            return $el;
        };
        $products = app(ProductService::class);
        foreach ($orders as $o) {
            $e = $root->appendChild($xml->createElement('Order'));
            $add($e, 'OrderID', $o->id, false);
            $add($e, 'OrderNumber', $o->order_number);
            $add($e, 'OrderDate', \Carbon\Carbon::parse($o->created_at, 'UTC')->format('m/d/Y H:i'), false);
            $add($e, 'OrderStatus', self::status($o));
            $add($e, 'LastModified', \Carbon\Carbon::parse($o->updated_at, 'UTC')->format('m/d/Y H:i'), false);
            $add($e, 'ShippingMethod', html_entity_decode((string) ($o->shipping_label ?: $o->shipping_method), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $add($e, 'PaymentMethod', $o->payment_method === 'net_terms' ? 'Net terms' : ($o->payment_method ?: ''));
            $add($e, 'CurrencyCode', 'USD', false);
            $add($e, 'OrderTotal', number_format((float) $o->total, 2, '.', ''), false);
            $add($e, 'TaxAmount', number_format((float) $o->tax_total, 2, '.', ''), false);
            $add($e, 'ShippingAmount', number_format((float) $o->shipping_total, 2, '.', ''), false);
            $add($e, 'CustomerNotes', trim(($o->customer_note ?? '') . ($o->ship_instructions ? "\nDelivery: " . $o->ship_instructions : '')));
            $add($e, 'InternalNotes', implode(' · ', array_filter([
                $o->po_number ? 'PO ' . $o->po_number : null,
                $o->signature_required ? 'Signature required' : null,
                $o->payment_method === 'net_terms' ? 'Net terms invoice' : null,
            ])));
            $add($e, 'CustomField1', $o->po_number);
            $add($e, 'CustomField2', $o->coupon_code);

            $c = $e->appendChild($xml->createElement('Customer'));
            $add($c, 'CustomerCode', $o->email);
            $bill = $c->appendChild($xml->createElement('BillTo'));
            $add($bill, 'Name', trim($o->billing_first_name . ' ' . $o->billing_last_name));
            $add($bill, 'Company', $o->billing_company);
            $add($bill, 'Phone', $o->phone);
            $add($bill, 'Email', $o->email);
            $p = $o->ship_to_different ? 'shipping_' : 'billing_';
            $ship = $c->appendChild($xml->createElement('ShipTo'));
            $add($ship, 'Name', trim($o->{$p . 'first_name'} . ' ' . $o->{$p . 'last_name'}));
            $add($ship, 'Company', $o->{$p . 'company'});
            $add($ship, 'Address1', $o->{$p . 'address_1'});
            $add($ship, 'Address2', $o->{$p . 'address_2'});
            $add($ship, 'City', $o->{$p . 'city'});
            $add($ship, 'State', $o->{$p . 'state'});
            $add($ship, 'PostalCode', $o->{$p . 'postcode'});
            $add($ship, 'Country', $o->{$p . 'country'} ?: 'US');
            $add($ship, 'Phone', $o->phone);

            $items = $e->appendChild($xml->createElement('Items'));
            foreach (OrderMailer::items(DB::table('order_items')->where('order_id', $o->id)->orderBy('id')->get()) as $it) {
                $i = $items->appendChild($xml->createElement('Item'));
                $add($i, 'LineItemID', $it->id, false);
                $add($i, 'SKU', $it->sku);
                $add($i, 'Name', $it->name);
                if (!empty($it->image)) $add($i, 'ImageUrl', str_starts_with($it->image, 'http') ? $it->image : url($it->image));
                $lb = $it->wp_product_id ? $products->weight((int) $it->wp_product_id) : 0;
                if ($lb > 0) { $add($i, 'Weight', round($lb, 2), false); $add($i, 'WeightUnits', 'Pounds', false); }
                $add($i, 'Quantity', (int) $it->qty, false);
                $add($i, 'UnitPrice', number_format((float) $it->price, 2, '.', ''), false);
                if (($it->type ?? 'product') !== 'product') $add($i, 'Adjustment', 'true', false);
            }
            if ((float) $o->discount_total > 0) {
                $i = $items->appendChild($xml->createElement('Item'));
                $add($i, 'SKU', '');
                $add($i, 'Name', 'Discount' . ($o->coupon_code ? ' (' . $o->coupon_code . ')' : ''));
                $add($i, 'Quantity', 1, false);
                $add($i, 'UnitPrice', number_format(-(float) $o->discount_total, 2, '.', ''), false);
                $add($i, 'Adjustment', 'true', false);
            }
        }
        // the first export of an order is noted, so it keeps being exported when it ships or is cancelled
        $new = $orders->whereNull('shipstation_pushed_at')->pluck('id')->all();
        if ($new) DB::table('orders')->whereIn('id', $new)->update(['shipstation_pushed_at' => now()]);   // updated_at untouched: no export loop
        return response($xml->saveXML(), 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'no-store, private']);
    }

    /** "UPS", "ups_walleted", "stamps_com", "FedEx"... → the carrier names the store's tracking links know. */
    public static function carrier(string $c): string
    {
        $l = strtolower($c);
        if (str_contains($l, 'usps') || str_contains($l, 'stamps') || str_contains($l, 'endicia')) return 'USPS';
        if (str_contains($l, 'ups')) return 'UPS';
        if (str_contains($l, 'fedex')) return 'FedEx';
        if (str_contains($l, 'dhl')) return 'DHL';
        return trim($c) ?: 'UPS';
    }

    private function shipnotify(Request $r)
    {
        $body = (string) $r->getContent();
        $xml = null;
        if ($body !== '') {
            $prev = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA) ?: null;
            libxml_use_internal_errors($prev);
        }
        $number = trim((string) ($r->query('order_number') ?: ($xml->OrderNumber ?? '')));
        $tracking = trim((string) ($r->query('tracking_number') ?: ($xml->TrackingNumber ?? '')));
        $carrier = self::carrier((string) ($r->query('carrier') ?: ($xml->Carrier ?? '')));
        $service = trim((string) ($r->query('service') ?: ($xml->Service ?? ''))) ?: null;
        $order = $number !== '' ? DB::table('orders')->where('order_number', $number)->first() : null;
        if (!$order && $xml && isset($xml->OrderID)) $order = DB::table('orders')->find((int) $xml->OrderID);
        if (!$order) return response('Order not found', 404);
        if ($tracking === '') return response('No tracking number', 400);
        OrderEvents::record($order, 'shipstation:label', 'Label bought in ShipStation', trim("{$carrier} " . ($service ?? '') . " · {$tracking}"));
        OrderTracking::markShipped($order, $tracking, $carrier, $service, true);
        return response('OK', 200);
    }
}
