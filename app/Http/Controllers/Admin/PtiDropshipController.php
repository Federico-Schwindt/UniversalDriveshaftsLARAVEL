<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PtiDropship;
use App\Support\AdminLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** "Dropship from PTI" on the order page: prepare (loads PTI's cart, orders nothing), place, cancel, tracking. */
class PtiDropshipController extends Controller
{
    public function __construct(private PtiDropship $dropship) {}

    public function prepare(Request $r, int $id)
    {
        $order = DB::table('orders')->find($id); abort_unless($order, 404);
        $d = $r->validate([
            'qty' => 'required|array', 'qty.*' => 'nullable|integer|min:0|max:999',
            'company' => 'required|string|max:60', 'street' => 'required|string|max:120', 'unit' => 'nullable|string|max:60',
            'city' => 'required|string|max:60', 'st' => 'required|string|max:40', 'zip' => 'required|string|max:20',
            'country' => 'required|string|max:3', 'phone' => 'required|string|max:30',
        ]);
        $pick = array_filter($d['qty'], fn ($q) => (int) $q > 0);
        $ship = array_intersect_key($d, array_flip(['company', 'street', 'unit', 'city', 'st', 'zip', 'country', 'phone'])) + ['unit' => ''];
        $ship['unit'] = (string) ($ship['unit'] ?? '');
        $ship['phone'] = preg_replace('/\D/', '', $ship['phone']);
        try {
            $res = $this->dropship->prepare($order, $pick, $ship, $r->boolean('residential'), (int) $r->user()->id, array_filter((array) $r->input('override', [])));
        } catch (\Throwable $e) {
            report($e);
            $res = ['ok' => false, 'error' => 'PTI did not answer: ' . $e->getMessage()];
        }
        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    public function place(Request $r, int $dropship)
    {
        $d = $r->validate([
            'shipping_method' => 'required|string|max:40', 'shipping_name' => 'required|string|max:120', 'shipping_cost' => 'required|numeric|min:0',
            'store' => 'required|in:' . implode(',', array_keys(PtiDropship::STORES)), 'card' => 'required|string|max:20', 'confirm' => 'accepted',
        ]);
        // one click, one order: a double-submit must not reach PTI twice
        $lock = \Illuminate\Support\Facades\Cache::lock("pti-place:$dropship", 60);
        if (!$lock->get()) return response()->json(['ok' => false, 'error' => 'This dropship is already being placed — wait a moment and refresh.'], 409);
        try {
            $res = $this->dropship->place($dropship, $d['shipping_method'], $d['shipping_name'], (float) $d['shipping_cost'], $d['store'], $d['card'], $r->boolean('signature'));
        } catch (\Throwable $e) {
            report($e);
            $res = ['ok' => false, 'error' => 'PTI did not answer — check c2.powertrainindustries.com before trying again, the order may or may not have gone through.'];
        } finally { $lock->release(); }
        if ($res['ok']) {
            $row = DB::table('pti_dropships')->find($dropship);
            $order = DB::table('orders')->find($row->order_id);
            AdminLog::record('order.pti_dropship', 'Dropshipped ' . $order->order_number . ' from PTI — PTI order #' . $res['pti_order_id'], 'orders', (string) $order->id);
        }
        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    public function cancel(int $dropship)
    {
        $res = $this->dropship->cancel($dropship);
        return response()->json($res, $res['ok'] ? 200 : 422);
    }

    public function tracking(int $dropship)
    {
        $row = DB::table('pti_dropships')->find($dropship); abort_unless($row, 404);
        try { $tn = $this->dropship->syncTracking($row); } catch (\Throwable $e) { report($e); return back()->with('admin_err', 'PTI did not answer: ' . $e->getMessage()); }
        return back()->with('admin_ok', $tn ? "PTI tracking $tn added to the order." : 'No tracking from PTI yet.');
    }
}
