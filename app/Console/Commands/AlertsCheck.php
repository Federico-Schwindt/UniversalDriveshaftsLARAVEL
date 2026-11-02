<?php

namespace App\Console\Commands;

use App\Services\CatalogService;
use App\Services\DriveshaftFinder;
use App\Services\RecoveryMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Hourly: e-mail every open back-in-stock subscription whose part is purchasable again; subscriptions older than 180 days expire. */
class AlertsCheck extends Command
{
    protected $signature = 'alerts:check {--dry : report without mailing}';
    protected $description = 'Notify back-in-stock subscribers whose part is available again';

    public function handle(CatalogService $catalog, DriveshaftFinder $finder, RecoveryMailer $mailer): int
    {
        $old = DB::table('stock_alerts')->whereNull('notified_at')->whereNull('expired_at')->where('created_at', '<', now()->subDays(180));
        $expired = $this->option('dry') ? $old->count() : $old->update(['expired_at' => now(), 'updated_at' => now()]);
        if ($expired) $this->line("expired: $expired (older than 180 days)");
        // every open alert, in chunks (a fixed limit used to starve everything after the oldest 300)
        $cache = []; $sent = 0; $checked = 0;
        DB::table('stock_alerts')->whereNull('notified_at')->whereNull('expired_at')->chunkById(500, function ($rows) use (&$cache, &$sent, &$checked, $catalog, $finder, $mailer) {
            foreach ($rows as $a) {
                $checked++;
                $key = $a->kind . ':' . $a->ref;
                if (!array_key_exists($key, $cache)) {
                    try {
                        if ($a->kind === 'product') {
                            $p = ctype_digit((string) $a->ref) ? $catalog->productById((int) $a->ref) : null;
                            $in = $p && \App\Http\Controllers\AlertsController::productAvailable($p);
                            $cache[$key] = $p ? ['in' => $in, 'name' => $p['name'], 'sku' => $p['sku'], 'price' => $p['price'], 'image' => $p['image'],
                                'url' => url('/parts/' . rawurlencode($p['sku'])), 'stock' => ($p['stock'] ?? 0) > 0 ? $p['stock'] : null] : null;   // 0 = PTI-only stock: no count to quote
                        } else {
                            $p = $finder->publicPart((string) $a->ref);
                            $cache[$key] = $p ? ['in' => (bool) $p['can_buy'], 'name' => trim(($p['description'] ?: 'Complete driveshaft') . ' ' . $p['sku']), 'sku' => $p['sku'],
                                'price' => $p['price'], 'image' => $p['image'], 'url' => url($p['url']), 'stock' => null] : null;
                        }
                    } catch (\Throwable $e) { report($e); $cache[$key] = null; }
                }
                $info = $cache[$key];
                if ($info === null) { $this->line("#{$a->id} $key: part not found, left open"); continue; }
                if (!$info['in']) continue;
                if ($this->option('dry')) { $this->line("#{$a->id} {$a->email}: would notify ($key)"); continue; }
                if ($mailer->backInStock($a, $info)) {
                    DB::table('stock_alerts')->where('id', $a->id)->update(['notified_at' => now(), 'updated_at' => now()]);
                    $this->info("#{$a->id} {$a->email}: notified ($key)"); $sent++;
                } else {
                    $this->warn("#{$a->id} {$a->email}: mail failed");
                }
            }
        });
        $this->info("done: $sent notified, $checked open checked");
        return 0;
    }
}
