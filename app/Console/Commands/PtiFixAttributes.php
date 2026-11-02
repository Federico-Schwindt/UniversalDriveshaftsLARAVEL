<?php

namespace App\Console\Commands;

use App\Services\Admin\CatalogWriter;
use App\Services\PtiDropship;
use App\Support\AdminLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Where our product has PTI's part number (or, with --with-u, PTI's number plus a trailing "U") and a measurement
 * disagrees with PTI, take PTI's value — a typo on our side (owner, 2026-10-24).
 * Every change is backed up to storage/app/pti/attr-fix-{time}.json; --restore={file} puts the old values back.
 */
class PtiFixAttributes extends Command
{
    protected $signature = 'pti:fix-attributes {--dry} {--with-u : also parts whose SKU is PTI\'s plus a trailing U} {--restore= : backup file name in storage/app/pti to undo}';
    protected $description = 'Correct our attribute values from PTI where the SKU is identical';

    public function handle(PtiDropship $ds, CatalogWriter $writer): int
    {
        if ($file = $this->option('restore')) return $this->restore($file, $writer);
        $changes = [];
        // owner, 2026-10-24: first same-SKU parts only; then also "PTI number + U" ones (--with-u)
        foreach (DB::table('pti_parts')->whereIn('match', $this->option('with-u') ? ['sku', 'sku_u'] : ['sku'])->whereNotNull('wp_product_id')->get() as $p) {
            $specs = json_decode((string) $p->specs, true) ?: [];
            $v = $ds->verify((int) $p->wp_product_id, (string) $p->pti_type, $specs);
            if ($v['status'] !== 'mismatch') continue;
            $attrs = $writer->product((int) $p->wp_product_id)['attrs'] ?? [];
            foreach (PtiDropship::attrMap((string) $p->pti_type) as $tax => $keys) {
                // our lube (greasable or not) is always right (owner, 2026-10-24): a different lube means a different part, never a typo
                if ($tax === 'pa_lube') continue;
                $label = ucwords(str_replace(['pa_', '-'], ['', ' '], $tax));
                if (!in_array($label, array_column($v['diffs'], 'label'), true)) continue;
                $vals = array_map(fn ($k) => trim((string) ($specs[$k] ?? '')), (array) $keys);
                if (in_array('', $vals, true)) continue;
                // keep our formats: "31/32 X 1.378" for splines, "2.500X0.083" for tube size
                $new = count($vals) > 1 ? implode(str_contains($tax, 'tube') ? 'X' : ' X ', $vals) : $vals[0];
                $changes[] = ['id' => (int) $p->wp_product_id, 'sku' => $p->part_number, 'tax' => $tax, 'old' => $attrs[$tax] ?? [], 'new' => $new];
            }
        }
        $parts = count(array_unique(array_column($changes, 'id')));
        foreach (array_slice($changes, 0, 15) as $c) $this->line("  {$c['sku']}  {$c['tax']}: " . implode(' | ', $c['old']) . " → {$c['new']}");
        if ($this->option('dry')) { $this->info(count($changes) . " value(s) on $parts product(s) would change"); return self::SUCCESS; }

        @mkdir(storage_path('app/pti'), 0775, true);
        $name = 'attr-fix-' . now()->format('Ymd-His') . '.json';
        file_put_contents(storage_path("app/pti/$name"), json_encode(['made' => now()->toDateTimeString(), 'note' => 'Attribute values replaced with PTI values (same SKU). Undo: php artisan pti:fix-attributes --restore=' . $name, 'changes' => $changes], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach ($changes as $c) $writer->syncTerms($c['id'], $c['tax'], [$c['new']], true);
        CatalogWriter::invalidate();
        AdminLog::record('catalog.pti_attrs', count($changes) . " attribute values on $parts products corrected from PTI (backup $name)", 'products', '');
        $this->info(count($changes) . " value(s) on $parts product(s) corrected; backup storage/app/pti/$name");
        return self::SUCCESS;
    }

    private function restore(string $file, CatalogWriter $writer): int
    {
        $path = storage_path('app/pti/' . basename($file));
        if (!is_file($path)) { $this->error("no backup $path"); return self::FAILURE; }
        $data = json_decode(file_get_contents($path), true);
        foreach ($data['changes'] ?? [] as $c) $writer->syncTerms((int) $c['id'], $c['tax'], (array) $c['old'], true);
        CatalogWriter::invalidate();
        $this->info('restored ' . count($data['changes'] ?? []) . ' value(s) from ' . basename($path));
        return self::SUCCESS;
    }
}
