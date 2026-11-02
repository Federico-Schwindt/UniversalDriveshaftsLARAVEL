<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Privacy: chat recordings, spoken answers and photos are kept for DAYS days, then deleted from disk and the
 * row's audio / photos emptied. The messages themselves (the text) stay — never delete chat_messages rows here.
 */
class ChatPruneMedia extends Command
{
    public const DAYS = 90;

    protected $signature = 'chat:prune-media {--days=90 : Keep media this many days} {--dry-run : Only count}';
    protected $description = 'Delete UDS Assistant voice recordings and photos older than 90 days';

    public function handle(): int
    {
        $days = max(30, (int) $this->option('days'));   // never shorter than a month by typo
        $dry = (bool) $this->option('dry-run');
        $cut = now()->subDays($days);
        $disk = Storage::disk('local');
        $files = 0; $rows = 0;

        DB::table('chat_messages')->where('created_at', '<', $cut)->where(fn ($q) => $q->whereNotNull('audio')->orWhereNotNull('photos'))
            ->select(['id', 'audio', 'photos'])->chunkById(500, function ($chunk) use ($disk, $dry, &$files, &$rows) {
                foreach ($chunk as $m) {
                    $paths = array_filter(array_merge([$m->audio], json_decode((string) $m->photos, true) ?: []));
                    foreach ($paths as $p) {
                        // only our own chat folders, whatever a row says
                        if (!preg_match('#^(chat|chat-audio)/\d+/[A-Za-z0-9]+\.[a-z0-9]+$#', (string) $p)) continue;
                        if ($disk->exists($p)) { $files++; if (!$dry) $disk->delete($p); }
                    }
                    $rows++;
                    if (!$dry) DB::table('chat_messages')->where('id', $m->id)->update(['audio' => null, 'photos' => null]);
                }
            });

        // files no row points at any more (an upload whose message never got stored, a replaced clip)
        foreach (['chat', 'chat-audio'] as $dir) {
            foreach ($disk->allFiles($dir) as $p) {
                if ($disk->lastModified($p) >= $cut->getTimestamp()) continue;
                $files++;
                if (!$dry) $disk->delete($p);
            }
            if (!$dry) foreach ($disk->directories($dir) as $sub) if (!$disk->allFiles($sub)) $disk->deleteDirectory($sub);
        }

        $this->info(($dry ? '[dry run] ' : '') . "Chat media older than {$days} days: {$files} file(s) deleted, {$rows} message(s) emptied.");
        return self::SUCCESS;
    }
}
