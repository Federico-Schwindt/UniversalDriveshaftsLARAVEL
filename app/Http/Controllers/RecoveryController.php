<?php

namespace App\Http\Controllers;

use App\Services\CartService;
use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Abandoned-cart recovery: capture at checkout, restore from the e-mail link, unsubscribe, admin list. */
class RecoveryController extends Controller
{
    public function __construct(private CartService $cart) {}

    /** Upsert the current session's cart snapshot (+ e-mail / phone when the checkout form has them). */
    public function capture(Request $r)
    {
        $email = strtolower(trim((string) $r->input('email', '')));
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190)) $email = '';
        if ($email === '' && Auth::check()) $email = strtolower((string) Auth::user()->email);
        $phone = mb_substr(trim((string) $r->input('phone', '')), 0, 40);
        if ($phone !== '' && strlen(preg_replace('/\D/', '', $phone)) < 7) $phone = '';
        if ($email === '' && $phone === '' && !Auth::check()) {
            return response()->json(['ok' => false, 'reason' => 'no-identity']);
        }

        $items = array_values($this->cart->items());
        $sid = session()->getId();
        $row = DB::table('abandoned_carts')->where('session_id', $sid)->orderByDesc('id')->first();
        $data = [
            'user_id' => Auth::id(), 'items' => json_encode($items), 'subtotal' => $this->cart->subtotal(),
            'last_seen_at' => now(), 'updated_at' => now(),
        ];
        if ($email !== '') $data['email'] = $email;
        if ($phone !== '') $data['phone'] = $phone;
        // an address that once said "stop cart reminders" stays stopped on every later cart
        if ($email !== '' && self::suppressed($email)) $data['unsubscribed_at'] = now();

        if ($row) {
            // a cart that was already converted / restored starts a new reminder cycle
            if ($row->recovered_at || $row->restored_at) {
                $data += ['recovered_at' => null, 'restored_at' => null, 'reminders_sent' => 0, 'last_reminder_at' => null];
            }
            DB::table('abandoned_carts')->where('id', $row->id)->update($data);
            $token = $row->token;
        } else {
            $token = Str::random(40);
            DB::table('abandoned_carts')->insert($data + ['session_id' => $sid, 'token' => $token, 'created_at' => now()]);
        }
        return response()->json(['ok' => true, 'items' => count($items)]);
    }

    /** Rebuild the saved cart in the current session. Prices are re-read server side; gone parts are skipped. */
    public function restore(string $token, CatalogService $catalog, DriveshaftFinder $finder)
    {
        $row = DB::table('abandoned_carts')->where('token', $token)->first();
        abort_unless($row, 404);
        $saved = json_decode($row->items ?: '[]', true) ?: [];
        $restored = []; $skipped = [];
        foreach ($saved as $i) {
            $qty = max(1, min(999, (int) ($i['qty'] ?? 1)));
            try {
                switch ($i['type'] ?? 'product') {
                    case 'product':
                        $p = !empty($i['wp_product_id']) ? $catalog->productById((int) $i['wp_product_id']) : null;
                        if (!$p || $p['price'] === null) { $skipped[] = $i['name'] ?? 'Part'; break; }
                        $this->cart->add(['type' => 'product', 'wp_product_id' => $p['id'], 'name' => $p['name'], 'sku' => $p['sku'],
                            'image' => $p['image'], 'price' => $p['price'], 'qty' => $qty]);
                        $restored[] = ['name' => $p['name'], 'sku' => $p['sku'], 'qty' => $qty, 'price' => $p['price'], 'image' => $p['image']];
                        break;
                    case 'driveshaft':
                        $pn = (string) ($i['meta']['part_number'] ?? $i['sku'] ?? '');
                        $part = $pn !== '' ? $finder->publicPart($pn) : null;
                        if (!$part || !$part['can_buy']) { $skipped[] = $i['name'] ?? $pn; break; }
                        $vehicle = mb_substr((string) ($i['meta']['vehicle'] ?? ''), 0, 160);
                        $this->cart->add([
                            'type' => 'driveshaft', 'wp_product_id' => null,
                            'name' => trim(($part['description'] ?: 'Complete driveshaft') . ' ' . $part['sku']) . ($vehicle ? " · fits $vehicle" : ''),
                            'sku' => $part['sku'], 'image' => $part['image'] ?: '', 'price' => $part['price'], 'qty' => min(20, $qty),
                            'meta' => array_filter(['part_number' => $part['part_number'], 'vehicle' => $vehicle, 'weight_lb' => $part['weight'],
                                'core_price' => $part['core_price'], 'c_brg' => implode(', ', $part['c_brg'])]),
                        ]);
                        $restored[] = ['name' => $i['name'] ?? $part['sku'], 'sku' => $part['sku'], 'qty' => min(20, $qty), 'price' => $part['price'], 'image' => $part['image']];
                        break;
                    case 'custom_build':
                    case 'premade_build':
                        // designer builds: re-price the saved configuration with today's rules
                        $cfg = $i['meta']['config'] ?? null;
                        if (!is_array($cfg)) { $skipped[] = $i['name'] ?? 'Custom driveshaft'; break; }
                        $price = app(\App\Services\DsbPricing::class)->compute($cfg);
                        $meta = $i['meta']; $meta['price'] = $price;
                        $this->cart->add(['type' => $i['type'], 'wp_product_id' => $i['wp_product_id'] ?? null, 'name' => $i['name'],
                            'sku' => $i['sku'] ?? '', 'image' => $i['image'] ?? '', 'price' => $price['final'], 'qty' => $qty, 'meta' => $meta]);
                        $restored[] = ['name' => $i['name'], 'sku' => $i['sku'] ?? '', 'qty' => $qty, 'price' => $price['final'], 'image' => $i['image'] ?? ''];
                        break;
                    default:
                        $skipped[] = $i['name'] ?? 'Item';
                }
            } catch (\Throwable $e) {
                report($e);
                $skipped[] = $i['name'] ?? 'Item';
            }
        }
        DB::table('abandoned_carts')->where('id', $row->id)->update(['restored_at' => $row->restored_at ?: now(), 'updated_at' => now()]);
        session()->flash('status', count($restored) ? 'We put ' . count($restored) . ' item' . (count($restored) === 1 ? '' : 's') . ' back in your cart.' : 'Those parts are no longer available.');
        return view('recovery.restored', ['restored' => $restored, 'skipped' => $skipped, 'count' => $this->cart->count(), 'subtotal' => $this->cart->subtotal()]);
    }

    /** GET from the e-mail footer, POST from the mail client's one-click button (RFC 8058 List-Unsubscribe-Post). */
    public function unsubscribe(Request $r, string $token)
    {
        $row = DB::table('abandoned_carts')->where('token', $token)->first();
        abort_unless($row, 404);
        if ($row->email) self::suppress($row->email);
        DB::table('abandoned_carts')->where(fn ($w) => $row->email ? $w->where('email', $row->email)->orWhere('id', $row->id) : $w->where('id', $row->id))
            ->update(['unsubscribed_at' => now(), 'updated_at' => now()]);
        if ($r->isMethod('post')) return response('Unsubscribed', 200);
        return view('recovery.unsubscribed', ['email' => $row->email]);
    }

    /** Persistent opt-out (marketing_suppressions), kept apart from the cart rows so it survives new carts. */
    public static function suppress(string $email, string $channel = 'cart_reminders'): void
    {
        DB::table('marketing_suppressions')->insertOrIgnore(['email' => strtolower(trim($email)), 'channel' => $channel, 'created_at' => now()]);
    }

    public static function suppressed(string $email, string $channel = 'cart_reminders'): bool
    {
        return DB::table('marketing_suppressions')->where('email', strtolower(trim($email)))->where('channel', $channel)->exists();
    }

    /** /admin/recovery — recent abandoned carts, stock alerts and quote requests. */
    public function admin(Request $r)
    {
        $carts = DB::table('abandoned_carts')->orderByDesc('last_seen_at')->limit(150)->get()->map(function ($c) {
            $c->lines = json_decode($c->items ?: '[]', true) ?: [];
            $c->qty = array_sum(array_map(fn ($i) => (int) ($i['qty'] ?? 1), $c->lines));
            $c->status = self::status($c);
            return $c;
        });
        $alerts = DB::table('stock_alerts')->orderByDesc('id')->limit(100)->get();
        $quotes = DB::table('leads')->where('source', 'quote')->orderByDesc('id')->limit(50)->get()
            ->map(function ($l) { $l->d = json_decode($l->data ?: '{}', true) ?: []; return $l; });
        $stats = [
            'open' => $carts->where('status', 'waiting')->count() + $carts->where('status', 'reminded')->count(),
            'recovered' => DB::table('abandoned_carts')->whereNotNull('recovered_at')->count(),
            'recovered_value' => (float) DB::table('abandoned_carts')->whereNotNull('recovered_at')->sum('subtotal'),
            'alerts_open' => DB::table('stock_alerts')->whereNull('notified_at')->whereNull('expired_at')->count(),
        ];
        return view('admin.recovery', compact('carts', 'alerts', 'quotes', 'stats'));
    }

    /** Human status for a row (also used by recovery:send to decide what to skip). */
    public static function status(object $c): string
    {
        if ($c->recovered_at) return 'recovered';
        if ($c->unsubscribed_at) return 'unsubscribed';
        if (!$c->email) return 'no-email';
        if (empty(json_decode($c->items ?: '[]', true))) return 'empty';
        if ($c->restored_at) return 'restored';
        if ((int) $c->reminders_sent >= 2) return 'done';
        if ((int) $c->reminders_sent > 0) return 'reminded';
        return 'waiting';
    }
}
