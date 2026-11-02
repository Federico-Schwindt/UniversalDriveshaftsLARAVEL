<?php

namespace App\Http\Controllers;

use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use App\Services\RecoveryMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Back-in-stock subscriptions (PDP + finder) and "get a quote" requests for unpriced driveshafts. */
class AlertsController extends Controller
{
    public function __construct(private CatalogService $catalog, private DriveshaftFinder $finder, private RecoveryMailer $mailer) {}

    public function subscribe(Request $r)
    {
        if ($r->filled('website')) return response()->json(['ok' => true, 'message' => 'Thanks, you are on the list.']);   // honeypot
        $v = validator($r->all(), [
            'email' => 'required|email|max:190',
            'kind' => 'required|in:product,driveshaft',
            'ref' => 'required|string|max:80',
            'phone' => 'nullable|string|max:40',
            'vehicle' => 'nullable|string|max:160',
            'note' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) return response()->json(['ok' => false, 'message' => $v->errors()->first()], 422);
        $d = $v->validated();
        $email = strtolower(trim($d['email']));

        // resolve the part server side: the label and the "already in stock" answer never come from the client
        if ($d['kind'] === 'product') {
            $p = ctype_digit((string) $d['ref']) ? $this->catalog->productById((int) $d['ref']) : null;
            if (!$p) return response()->json(['ok' => false, 'message' => 'We could not find that part. Call (813) 524-6491 and we will check it for you.'], 422);
            $label = $p['name']; $ref = (string) $p['id']; $url = '/parts/' . rawurlencode($p['sku']);
            $inStock = self::productAvailable($p);
        } else {
            $p = $this->finder->publicPart((string) $d['ref']);
            if (!$p) return response()->json(['ok' => false, 'message' => 'We could not find that driveshaft. Call (813) 524-6491 and we will check it for you.'], 422);
            $label = trim(($p['description'] ?: 'Complete driveshaft') . ' ' . $p['sku']); $ref = $p['part_number']; $url = $p['url'];
            $inStock = (bool) $p['can_buy'];
        }
        if ($inStock) {
            return response()->json(['ok' => true, 'in_stock' => true, 'url' => $url, 'message' => 'Good news: it is available right now. Add it to your cart before it goes again.']);
        }
        DB::table('stock_alerts')->updateOrInsert(
            ['email' => $email, 'kind' => $d['kind'], 'ref' => $ref],
            ['label' => mb_substr($label, 0, 255), 'phone' => $d['phone'] ?? null, 'vehicle' => $d['vehicle'] ?? null, 'note' => $d['note'] ?? null,
             'notified_at' => null, 'updated_at' => now(), 'created_at' => now()]
        );
        return response()->json(['ok' => true, 'message' => "Done. We will e-mail $email the moment it is back in stock. One message, no newsletter."]);
    }

    /**
     * Can this catalog part be bought now? The live row (catalog_live: our shelf OR PTI on hand) decides when there is one —
     * the WP _stock count is a frozen snapshot and is 0 for parts only PTI stocks. Same answer for subscribe and alerts:check.
     */
    public static function productAvailable(array $p, ?array $live = null): bool
    {
        $live ??= CatalogService::live((int) ($p['id'] ?? 0));
        if ($live !== null) return (bool) $live['in_stock'];
        return ($p['stock_status'] ?? 'instock') === 'instock' && ($p['stock'] ?? null) !== 0;
    }

    public function quote(Request $r)
    {
        if ($r->filled('website')) return response()->json(['ok' => true, 'message' => 'Thanks, we will be in touch.']);   // honeypot
        $v = validator($r->all(), [
            'email' => 'required|email|max:190',
            'part_number' => 'required|string|max:80',
            'phone' => 'nullable|string|max:40',
            'vehicle' => 'nullable|string|max:160',
            'note' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) return response()->json(['ok' => false, 'message' => $v->errors()->first()], 422);
        $d = $v->validated();
        $part = $this->finder->publicPart($d['part_number']);
        $q = [
            'email' => strtolower(trim($d['email'])), 'phone' => trim((string) ($d['phone'] ?? '')) ?: null,
            'vehicle' => trim((string) ($d['vehicle'] ?? '')) ?: null, 'note' => trim((string) ($d['note'] ?? '')) ?: null,
            'part_number' => $part['part_number'] ?? DriveshaftFinder::pn($d['part_number']),
            'sku' => $part['sku'] ?? DriveshaftFinder::sku($d['part_number']),
            'description' => $part['description'] ?? null,
            'fits' => $part ? implode('; ', array_slice($part['fits'] ?? [], 0, 6)) : null,
            'url' => $part ? url($part['url']) : null,
            'ip' => $r->ip(),
        ];
        try {
            DB::table('leads')->insert([
                'source' => 'quote', 'name' => null, 'email' => $q['email'], 'phone' => $q['phone'],
                'message' => 'Quote request: ' . $q['sku'] . ($q['vehicle'] ? ' for ' . $q['vehicle'] : '') . ($q['note'] ? ' — ' . $q['note'] : ''),
                'data' => json_encode($q), 'status' => 'new', 'ip' => $q['ip'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) { report($e); }
        $sent = $this->mailer->quote($q);
        return response()->json(['ok' => true, 'sent' => $sent,
            'message' => 'Got it. A driveline tech will price ' . $q['sku'] . ' and reply to ' . $q['email'] . ', usually within one business day.']);
    }
}
