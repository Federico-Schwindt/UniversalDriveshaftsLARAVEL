<?php

namespace App\Console\Commands;

use App\Http\Controllers\SeoController;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * IndexNow: tells Bing (the index behind ChatGPT search and Copilot), Yandex, Seznam and Naver which pages changed,
 * so new parts and price changes are crawled in hours instead of weeks. Google does not use IndexNow (it reads the
 * sitemap). The key is public by design: a text file at the site root (public/{key}.txt, a static file: nginx serves
 * .txt itself). Only the production site submits; anywhere else the command just lists what it would send.
 */
class SeoIndexNow extends Command
{
    protected $signature = 'seo:indexnow {--all : every URL in the sitemaps (launch day)} {--days=2 : pages modified in the last N days} {--dry : list only}';
    protected $description = 'Submit new and changed URLs to IndexNow (Bing, ChatGPT search, Copilot)';

    public function handle(SeoController $seo): int
    {
        $key = self::key();
        $urls = $seo->urls($this->option('all') ? null : now()->subDays(max(1, (int) $this->option('days'))));
        $host = (string) parse_url((string) config('app.url'), PHP_URL_HOST);
        $live = app()->environment('production') && $host === 'universaldriveshafts.com';
        if (!$urls) { $this->info('Nothing changed.'); return self::SUCCESS; }
        if ($this->option('dry') || !$live) {
            $this->line(count($urls) . ' URL(s)' . ($live ? '' : ' (not production: nothing sent)') . ', e.g. ' . implode(', ', array_slice($urls, 0, 3)));
            return self::SUCCESS;
        }
        $sent = 0;
        foreach (array_chunk($urls, 10000) as $chunk) {
            $r = Http::timeout(30)->acceptJson()->post('https://api.indexnow.org/indexnow', [
                'host' => $host, 'key' => $key, 'keyLocation' => 'https://' . $host . '/' . $key . '.txt', 'urlList' => $chunk,
            ]);
            // 200 = received, 202 = received, key check pending
            if (!in_array($r->status(), [200, 202], true)) { $this->error('IndexNow answered HTTP ' . $r->status() . ': ' . mb_substr($r->body(), 0, 200)); return self::FAILURE; }
            $sent += count($chunk);
        }
        Settings::set('seo.indexnow_last', ['at' => now()->toIso8601String(), 'urls' => $sent]);
        $this->info("IndexNow: $sent URL(s) submitted.");
        return self::SUCCESS;
    }

    /** The site's IndexNow key, created once, with its verification file in public/. */
    public static function key(): string
    {
        $key = (string) Settings::get('seo.indexnow_key', '');
        if (!preg_match('/^[a-f0-9]{32}$/', $key)) { $key = bin2hex(random_bytes(16)); Settings::set('seo.indexnow_key', $key); }
        $file = public_path($key . '.txt');
        if (!is_file($file)) file_put_contents($file, $key);
        return $key;
    }
}
