<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class WeeklyReport extends Command
{
    protected $signature = 'inventory:weekly-report {--days=30} {--print}';
    protected $description = 'Weekly report: top sellers, low stock, and the watch list (high demand + low stock)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $sold = self::topSellers($days);

        $inv = DB::table('inventory')->where('tracked', 1)->get()
            ->map(fn ($x) => (object) ((array) $x + ['st' => \App\Http\Controllers\Admin\InventoryController::status($x)]));
        $low = $inv->filter(fn ($x) => in_array($x->st, ['out', 'critical']))->sortBy('stock')->values();

        // watch list: parts selling this period AND now low in stock (same SKU, or the SKU inside the sold line's name)
        $watch = $low->filter(function ($x) use ($sold) {
            if (!$x->sku) return false;
            foreach ($sold as $s) if (strcasecmp((string) $s->sku, $x->sku) === 0 || stripos($s->name, $x->sku) !== false) return true;
            return false;
        })->values();

        $b = "Universal Driveshafts — weekly report (" . now()->subDays($days)->format('M j') . " – " . now()->format('M j') . ")\n\n";
        $b .= "TOP SELLERS (last $days days)\n";
        $b .= $sold->map(fn ($s, $i) => sprintf('  %2d. %-46s %4d units / %d orders', $i + 1, mb_substr($s->name, 0, 46), $s->units, $s->orders))->implode("\n") . "\n\n";
        $b .= "⚠ LOW / OUT OF STOCK (" . $low->count() . ")\n";
        $b .= ($low->take(20)->map(fn ($x) => sprintf('  %-14s %-40s stock %-4d reorder@ %s', $x->sku ?: '-', mb_substr((string) $x->name, 0, 40), $x->stock, $x->reorder_at ?? '-'))->implode("\n") ?: '  (all good)') . "\n\n";
        $b .= "🔴 WATCH NOW — selling AND low (" . $watch->count() . ")\n";
        $b .= ($watch->map(fn ($x) => sprintf('  %-14s %-40s stock %d', $x->sku ?: '-', mb_substr((string) $x->name, 0, 40), $x->stock))->implode("\n") ?: '  (none)') . "\n\n";
        $b .= "Manage: " . url('/admin/inventory') . "\n";

        if ($this->option('print')) { $this->line($b); return 0; }
        $to = config('uds.inventory_alert_email', config('mail.from.address'));
        try { \App\Support\Mailer::simple($to, '📊 UDS weekly report — ' . now()->format('M j'), ['kicker' => 'Weekly report', 'title' => 'Week ending ' . now()->format('M j'), 'pre' => $b, 'button' => ['Open reports', config('app.url') . '/admin/reports'], 'signoff' => false]); $this->info("sent to $to"); }
        catch (\Throwable $e) { $this->error('send failed: ' . $e->getMessage()); return 1; }
        return 0;
    }

    /**
     * Best sellers from our own orders (the WooCommerce tables are a frozen snapshot since the switch):
     * paid or partly refunded, not cancelled / refunded / failed, placed in the last $days days; one row per product (or SKU / name).
     */
    public static function topSellers(int $days, int $limit = 15)
    {
        return DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereIn('o.payment_status', ['paid', 'partially_refunded'])
            ->whereNotIn('o.status', ['cancelled', 'refunded', 'failed'])
            ->where('o.created_at', '>=', now()->subDays($days))
            ->selectRaw('MAX(oi.name) name, MAX(oi.sku) sku, SUM(oi.qty) units, COUNT(DISTINCT oi.order_id) orders')
            ->groupByRaw('COALESCE(CAST(oi.wp_product_id AS CHAR), oi.sku, oi.name)')
            ->orderByDesc('units')->limit($limit)->get()
            ->map(function ($r) { $r->units = (int) $r->units; $r->orders = (int) $r->orders; return $r; });
    }
}
