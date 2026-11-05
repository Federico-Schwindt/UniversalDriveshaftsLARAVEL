<?php

namespace App\Console\Commands;

use App\Support\Seo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Moves the old WordPress site's SEO plumbing into the store:
 *  - redirects from Rank Math, the Redirection plugin and ThinkRank (wp_src snapshot), each target
 *    resolved to its final URL on the new site in one hop (old product slug → /parts/{sku}, …);
 *  - with --legacy, every product, page and category URL the old site published that now answers 404
 *    gets a redirect to the closest new page (same SKU, else its category, else the shop).
 * Rows added in the admin (origin "manual") are never touched.
 */
class SeoImportWp extends Command
{
    protected $signature = 'seo:import-wp {--legacy : also cover every old product/page/category URL that now 404s} {--dry-run}';
    protected $description = 'Import WordPress redirects (Rank Math, Redirection, ThinkRank) and old URLs into seo_redirects';

    /** Old WordPress pages with no twin on the new site: regex on the normalised path => new URL ($1 → resolved category). */
    private const PAGE_MAP = [
        '#^locations(/|$)#' => '/contact',
        '#^checkouts?(/|$)#' => '/cart',
        '#^parts-catalog/(.+)$#' => 'category:$1',
        '#^product-category/customized-#' => '/online-builder',
        '#^product/high-performance-.*driveshaft#' => '/online-builder',   // built-to-order street rod shafts
        '#^product-category/small-parts-subcategories-page#' => '/shop/small-parts',
        '#^product-category/premade-driveshafts/#' => '/premade-driveshafts',
        '#^the-importance-of-good-driveshaft-maintenance#' => '/guides/driveshaft-vibration-and-noise-diagnosis',
        '#^understanding-driveshaft-components#' => '/guides/slip-yoke-vs-weld-yoke-vs-flange-yoke',
    ];

    /** Rank Math sitemap URLs submitted in Search Console → the store's sitemaps. */
    private const SITEMAPS = [
        'sitemap_index.xml' => '/sitemap.xml', 'wp-sitemap.xml' => '/sitemap.xml', 'page-sitemap.xml' => '/sitemap-pages.xml', 'post-sitemap.xml' => '/sitemap-extra.xml',
        'product-sitemap.xml' => '/sitemap-products.xml', 'product-sitemap1.xml' => '/sitemap-products.xml', 'product-sitemap2.xml' => '/sitemap-products.xml',
        'product-sitemap3.xml' => '/sitemap-products.xml', 'product_cat-sitemap.xml' => '/sitemap-categories.xml', 'category-sitemap.xml' => '/sitemap-categories.xml',
        'local-sitemap.xml' => '/sitemap-pages.xml',
    ];

    private array $rules = [];       // old WP rules: norm source => target URL
    private array $slugSku = [];     // local published product slug => sku
    private array $skuLc = [];       // lowercase sku => sku (published locally)
    private array $resolved = [];
    private string $base;

    public function handle(): int
    {
        $this->base = rtrim((string) config('app.url'), '/');
        $wp = DB::connection('wp');
        $src = DB::connection('wp_src');

        foreach ($wp->table('wp_posts as p')->join('wp_postmeta as s', fn ($j) => $j->on('s.post_id', '=', 'p.ID')->where('s.meta_key', '_sku'))
                    ->where('p.post_type', 'product')->where('p.post_status', 'publish')->where('s.meta_value', '!=', '')
                    ->get(['p.post_name', 's.meta_value as sku']) as $r) {
            $this->slugSku[mb_strtolower(rawurldecode($r->post_name))] = $r->sku;
            $this->skuLc[mb_strtolower($r->sku)] = $r->sku;
        }

        // 1. collect the WordPress rules
        $rows = [];
        foreach ($src->table('wp_rank_math_redirections')->where('status', 'active')->get() as $r) {
            foreach ((@unserialize($r->sources) ?: []) as $s) {
                if (($s['comparison'] ?? 'exact') !== 'exact' || trim((string) ($s['pattern'] ?? '')) === '') continue;
                $rows[] = ['source' => $s['pattern'], 'target' => $r->url_to, 'code' => (int) $r->header_code, 'origin' => 'rank_math', 'wp_hits' => (int) $r->hits];
            }
        }
        foreach ($src->table('wp_redirection_items')->where('status', 'enabled')->where('regex', 0)->get() as $r) {
            $rows[] = ['source' => $r->url, 'target' => $r->action_data, 'code' => (int) $r->action_code, 'origin' => 'redirection', 'wp_hits' => (int) $r->last_count];
        }
        foreach ($src->table('wp_thinkrank_redirects')->where('status', 'active')->where('match_type', 'exact')->get() as $r) {
            $rows[] = ['source' => $r->source_url, 'target' => $r->target_url, 'code' => (int) $r->http_code, 'origin' => 'thinkrank', 'wp_hits' => (int) $r->hits];
        }
        foreach ($rows as $r) $this->rules[Seo::norm($r['source'])] = $r['code'] === 410 ? null : $r['target'];
        foreach (self::SITEMAPS as $from => $to) $rows[] = ['source' => $from, 'target' => $to, 'code' => 301, 'origin' => 'legacy', 'wp_hits' => 0];

        // 2. old URLs the WordPress site published (products, pages, categories)
        if ($this->option('legacy')) {
            foreach ($src->table('wp_posts')->where('post_type', 'product')->where('post_status', 'publish')->pluck('post_name', 'ID') as $id => $slug) {
                $rows[] = ['source' => 'product/' . $slug, 'target' => null, 'code' => 301, 'origin' => 'legacy', 'wp_hits' => 0, 'wp_id' => $id];
            }
            $pages = $src->table('wp_posts')->whereIn('post_type', ['page', 'post'])->where('post_status', 'publish')->get(['ID', 'post_name', 'post_parent'])->keyBy('ID');
            foreach ($pages as $p) {
                $path = $p->post_name; $parent = $p->post_parent; $guard = 0;
                while ($parent && isset($pages[$parent]) && $guard++ < 6) { $path = $pages[$parent]->post_name . '/' . $path; $parent = $pages[$parent]->post_parent; }
                $rows[] = ['source' => $path, 'target' => null, 'code' => 301, 'origin' => 'legacy', 'wp_hits' => 0];
            }
            $terms = $src->table('wp_term_taxonomy as tt')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')->where('tt.taxonomy', 'product_cat')
                ->get(['t.term_id', 't.slug', 'tt.parent'])->keyBy('term_id');
            foreach ($terms as $t) {
                $path = $t->slug; $parent = $t->parent; $guard = 0;
                while ($parent && isset($terms[$parent]) && $guard++ < 6) { $path = $terms[$parent]->slug . '/' . $path; $parent = $terms[$parent]->parent; }
                $rows[] = ['source' => 'product-category/' . $path, 'target' => null, 'code' => 301, 'origin' => 'legacy', 'wp_hits' => 0];
            }
        }

        // 3. resolve: skip sources the new site already answers, collapse every target to its final URL
        $manual = DB::table('seo_redirects')->where('origin', 'manual')->pluck('source')->flip();
        $out = []; $stats = ['live' => 0, 'manual' => 0, 'fallback' => 0, 'gone' => 0];
        $bar = $this->output->createProgressBar(count($rows));
        foreach ($rows as $r) {
            $bar->advance();
            $source = Seo::norm($r['source']);
            if ($source === '' || isset($out[$source]) && $r['origin'] === 'legacy') continue;
            if (isset($manual[$source])) { $stats['manual']++; continue; }
            if ($this->answers($source)) { $stats['live']++; continue; }
            $note = null;
            if ($r['code'] === 410) { $target = null; $stats['gone']++; }
            else {
                $target = $this->mapped($source);
                if ($target === null) $target = $r['target'] !== null ? $this->resolve($r['target']) : $this->resolve('/' . $source);
                if ($target === null && $r['target'] !== null && !preg_match('#^https?://(?!(www\.)?universaldriveshafts\.com)#i', $r['target'])) $target = $this->closest(Seo::norm($r['target']), null);
                if ($target === null) $target = $this->closest($source, $r['wp_id'] ?? null);
                if ($target === null) { $target = '/shop'; $note = 'no close match: sent to the shop'; $stats['fallback']++; }
                if (Seo::norm($target) === $source) continue;   // would loop
            }
            $out[$source] = ['source' => $source, 'target' => $target, 'code' => $r['code'] === 410 ? 410 : ($r['code'] === 302 ? 302 : 301),
                             'origin' => $r['origin'], 'note' => $note, 'wp_hits' => max($r['wp_hits'], $out[$source]['wp_hits'] ?? 0)];
        }
        $bar->finish();
        $this->newLine();

        $byOrigin = array_count_values(array_column($out, 'origin'));
        $this->info(count($out) . ' redirects to store ' . json_encode($byOrigin) . '; skipped: ' . $stats['live'] . ' already live on the new site, ' . $stats['manual'] . ' kept from the admin; ' . $stats['gone'] . ' answer 410; ' . $stats['fallback'] . ' sent to /shop.');
        if ($this->option('dry-run')) {
            $show = array_filter($out, fn ($r) => $r['note'] || $r['origin'] !== 'rank_math');
            foreach ($show + array_slice($out, 0, 15) as $r) $this->line("  [{$r['origin']}] /{$r['source']} → " . ($r['target'] ?? '410') . ($r['note'] ? "  ({$r['note']})" : ''));
            return self::SUCCESS;
        }

        DB::transaction(function () use ($out) {
            DB::table('seo_redirects')->whereIn('origin', ['rank_math', 'redirection', 'thinkrank', 'legacy'])->whereNotIn('source', array_keys($out))->delete();
            $now = now();
            foreach (array_chunk(array_values($out), 500) as $chunk) {
                DB::table('seo_redirects')->upsert(array_map(fn ($r) => $r + ['created_at' => $now, 'updated_at' => $now], $chunk), ['source'],
                    ['target', 'code', 'origin', 'note', 'wp_hits', 'updated_at']);
            }
        });
        Seo::flush();
        $this->info('Saved. Manage them in /admin/seo.');
        return self::SUCCESS;
    }

    /** Does the new site serve this path itself (anything but a 404/410)? */
    private function answers(string $source): bool
    {
        if (preg_match('#^product/([^/]+)$#', $source, $m)) return isset($this->slugSku[$m[1]]);
        $r = $this->fetch('/' . $source);
        $st = $r['status'];
        if (in_array($st, [301, 302, 307, 308], true) && Seo::redirectFor('/' . $source)) return false;   // one of ours from an earlier import
        if (in_array($st, [301, 302], true) && str_starts_with($source, 'product-category/') && preg_match('#/shop$#', (string) $r['location'])) return false;   // generic catalog fallback: an old rule knows better
        return $st !== 404 && $st !== 410 && $st !== 0;
    }

    /** Final URL on the new site for an old link, or null when it ends in a 404. */
    private function resolve(string $url, int $depth = 0): ?string
    {
        if ($depth > 6) return null;
        $url = trim($url);
        if (preg_match('#^https?://([^/]+)#i', $url, $h)) {
            $host = strtolower(preg_replace('/^www\./', '', $h[1]));
            if (!in_array($host, ['universaldriveshafts.com', parse_url($this->base, PHP_URL_HOST)], true)) return $url;   // external link stays as is
            $url = (string) (parse_url($url, PHP_URL_PATH) ?: '/') . (($q = parse_url($url, PHP_URL_QUERY)) ? '?' . $q : '');
        }
        $norm = Seo::norm($url);
        $key = $norm . '|' . (parse_url($url, PHP_URL_QUERY) ?? '');
        if (array_key_exists($key, $this->resolved)) return $this->resolved[$key];

        if (preg_match('#^product/([^/]+)$#', $norm, $m) && isset($this->slugSku[$m[1]])) return $this->resolved[$key] = '/parts/' . rawurlencode($this->slugSku[$m[1]]);
        if (array_key_exists($norm, $this->rules) && $this->rules[$norm] !== null && Seo::norm($this->rules[$norm]) !== $norm && !$this->answersCached($norm)) {
            return $this->resolved[$key] = $this->resolve($this->rules[$norm], $depth + 1);
        }
        $path = '/' . $norm . (($q = parse_url($url, PHP_URL_QUERY)) ? '?' . $q : '');
        $r = $this->fetch($path);
        if ($r['status'] === 200) return $this->resolved[$key] = ($norm === '' ? '/' : $path);
        if (in_array($r['status'], [301, 302, 307, 308], true) && $r['location']) return $this->resolved[$key] = $this->resolve($r['location'], $depth + 1);
        return $this->resolved[$key] = null;
    }

    private function mapped(string $source): ?string
    {
        foreach (self::PAGE_MAP as $rx => $to) {
            if (!preg_match($rx, $source, $m)) continue;
            if (str_starts_with($to, 'category:')) return $this->resolve('/product-category/' . basename(str_replace('$1', $m[1] ?? '', substr($to, 9))));
            return $to;
        }
        return null;
    }

    private array $answerCache = [];
    private function answersCached(string $norm): bool
    {
        return $this->answerCache[$norm] ??= $this->answers($norm);
    }

    /** A product that is gone: same SKU under another slug, else its category; for pages: null. */
    private function closest(string $source, ?int $wpId): ?string
    {
        if (!preg_match('#^product/([^/]+)$#', $source, $m)) return null;
        $tokens = explode('-', preg_replace('/^(uds|spicer|oem|neapco|dana)-/', '', $m[1]));
        for ($n = count($tokens); $n >= 1; $n--) {
            $cand = implode('-', array_slice($tokens, 0, $n));
            foreach ([$cand, $cand . 'u', preg_replace('/u$/', '', $cand)] as $c) {
                if (isset($this->skuLc[$c])) return '/parts/' . rawurlencode($this->skuLc[$c]);
            }
        }
        $src = DB::connection('wp_src');
        if (!$wpId) $wpId = $src->table('wp_posts')->where('post_type', 'product')->where('post_name', $m[1])->value('ID');
        if (!$wpId) return null;
        $primary = $src->table('wp_postmeta')->where('post_id', $wpId)->whereIn('meta_key', ['rank_math_primary_product_cat', '_yoast_wpseo_primary_product_cat'])->value('meta_value');
        $cats = $src->table('wp_term_relationships as r')->join('wp_term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'r.term_taxonomy_id')->join('wp_terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('r.object_id', $wpId)->where('tt.taxonomy', 'product_cat')->orderByDesc('tt.parent')->get(['t.term_id', 't.slug']);
        if ($primary) $cats = $cats->sortByDesc(fn ($c) => (int) ($c->term_id == $primary));
        foreach ($cats as $c) {
            $t = $this->resolve('/product-category/' . $c->slug);
            if ($t && $t !== '/shop') return $t;
        }
        return null;
    }

    /** GET a path on this site without following redirects. */
    private function fetch(string $path): array
    {
        try {
            $r = Http::withoutRedirecting()->timeout(30)->withHeaders(['User-Agent' => 'UDS seo import'])->get($this->base . $path);
            return ['status' => $r->status(), 'location' => $r->header('Location') ?: null];
        } catch (\Throwable $e) {
            return ['status' => 0, 'location' => null];
        }
    }
}
