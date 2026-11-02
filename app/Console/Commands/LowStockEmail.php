<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class LowStockEmail extends Command
{
    protected $signature = 'inventory:low-stock-email {--print : output the email instead of sending}';
    protected $description = 'Daily email of parts at/below their reorder point (and out of stock)';

    public function handle(): int
    {
        $rows = DB::table('inventory')->where('tracked', 1)->get()
            ->map(fn ($x) => (object) ((array) $x + ['st' => \App\Http\Controllers\Admin\InventoryController::status($x)]))
            ->filter(fn ($x) => in_array($x->st, ['out', 'critical', 'low']))
            ->sortBy(fn ($x) => ['out' => 0, 'critical' => 1, 'low' => 2][$x->st])->values();

        if ($rows->isEmpty()) { $this->info('nothing low — no email'); return 0; }

        $line = fn ($x) => sprintf('  %-14s %-40s  stock %-4d  reorder@ %s', $x->sku ?: '-',
            mb_substr((string) $x->name, 0, 40), $x->stock, $x->reorder_at ?? '-');
        $groups = $rows->groupBy('st');
        $body = "Universal Driveshafts — daily stock alert (" . now()->format('D M j') . ")\n\n";
        foreach (['out' => 'OUT OF STOCK', 'critical' => 'AT / BELOW REORDER POINT', 'low' => 'GETTING LOW'] as $k => $h) {
            if (!empty($groups[$k])) {
                $body .= "$h (" . count($groups[$k]) . ")\n" . $groups[$k]->map($line)->implode("\n") . "\n\n";
            }
        }
        $body .= "Manage: " . url('/admin/inventory') . "\n";

        if ($this->option('print')) { $this->line($body); return 0; }
        $to = config('uds.inventory_alert_email', config('mail.from.address'));
        try {
            \App\Support\Mailer::simple($to, '⚠ Stock alert: ' . $rows->count() . ' parts need attention', ['kicker' => 'Inventory', 'title' => $rows->count() . ' parts need attention', 'pre' => $body, 'button' => ['Open inventory', config('app.url') . '/admin/inventory?status=critical'], 'signoff' => false]);
            // record so a future "only-if-changed" mode can suppress repeats
            foreach ($rows as $x) DB::table('inventory_alert_log')->insert(['wp_product_id' => $x->wp_product_id, 'stock' => $x->stock, 'reorder_at' => $x->reorder_at, 'kind' => $x->st, 'created_at' => now()]);
            $this->info("sent to $to: {$rows->count()} items");
        } catch (\Throwable $e) { $this->error('send failed: ' . $e->getMessage()); return 1; }
        return 0;
    }
}
