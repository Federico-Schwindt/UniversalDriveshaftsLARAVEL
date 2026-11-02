<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Registers the cleaned PTI part photos (public/img/driveshafts/<part>/<n>.jpg) onto ds_parts.
 * Re-runnable. A file named 0.* (e.g. a photo Modi shot himself) sorts first and becomes the primary.
 */
class SyncDriveshaftPictures extends Command
{
    protected $signature = 'catalog:sync-pictures {--dir=img/driveshafts : path under public/}';
    protected $description = 'Scan the driveshaft photo folders and write main_image + images onto ds_parts';

    public function handle(): int
    {
        $base = public_path(trim($this->option('dir'), '/'));
        if (!is_dir($base)) { $this->error("missing $base"); return 1; }
        $rel = trim($this->option('dir'), '/');
        $withImg = 0; $files = 0; $seen = [];

        foreach (scandir($base) as $pn) {
            if ($pn === '.' || $pn === '..' || !is_dir("$base/$pn")) continue;
            $imgs = array_values(array_filter(scandir("$base/$pn"),
                fn ($f) => preg_match('/\.(jpe?g|png|webp)$/i', $f)));
            natcasesort($imgs); $imgs = array_values($imgs);
            if (!$imgs) continue;
            // the on-disk folder uses "/" replaced by "_"; the real part_number may contain "/"
            $paths = array_map(fn ($f) => "/$rel/$pn/$f", $imgs);
            $seen[$pn] = ['main_image' => $paths[0], 'images' => json_encode($paths), 'picture_count' => count($paths)];
            $files += count($paths);
        }

        // match folder names to ds_parts.part_number (folder "/" was stored as "_")
        $byRaw = DB::table('ds_parts')->pluck('part_number')->mapWithKeys(
            fn ($p) => [str_replace('/', '_', $p) => $p])->all();

        foreach (array_chunk(array_keys($seen), 400) as $chunk) {
            foreach ($chunk as $folder) {
                $pn = $byRaw[$folder] ?? $folder;
                $n = DB::table('ds_parts')->where('part_number', $pn)->update($seen[$folder]);
                if ($n) $withImg++;
            }
        }
        $noImg = DB::table('ds_parts')->whereNull('main_image')->where('is_active', 1)->count();
        $this->info("parts with photos: $withImg  ($files files)");
        $this->warn("active parts still without a photo: $noImg  (they keep the illustration)");
        return 0;
    }
}
