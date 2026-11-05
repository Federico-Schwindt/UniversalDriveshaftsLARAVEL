<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminLog;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin → SEO: the store's own redirects (imported from Rank Math / Redirection / ThinkRank on the old
 * WordPress site, plus manual ones), the log of URLs that answered 404, per-URL title/description overrides, and
 * Performance: Google Search Console clicks, impressions and average position, month by month, per query and page.
 */
class SeoController extends Controller
{
    public const TABS = ['performance' => 'Performance', 'redirects' => 'Redirects', 'notfound' => '404 log', 'pages' => 'Page titles'];
    public const ROBOTS = ['index' => 'index,follow', 'noindex' => 'noindex,follow', 'noindex_nofollow' => 'noindex,nofollow'];
    public const ORIGINS = ['rank_math' => 'Rank Math', 'redirection' => 'Redirection', 'thinkrank' => 'ThinkRank', 'legacy' => 'Old URL', 'manual' => 'Added here'];

    public function index(Request $r)
    {
        $tab = array_key_exists($r->query('tab'), self::TABS) ? $r->query('tab') : 'performance';
        $q = trim((string) $r->query('q', ''));
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], mb_strtolower(ltrim($q, '/'))) . '%';

        $counts = [
            'redirects' => DB::table('seo_redirects')->count(),
            'notfound' => DB::table('seo_404s')->count(),
            'pages' => DB::table('seo_pages')->count(),
            'performance' => null,
        ];
        if ($tab === 'performance') return view('admin.seo.index', compact('tab', 'q', 'counts') + $this->performance($r));
        if ($tab === 'redirects') {
            $origin = array_key_exists($r->query('origin'), self::ORIGINS) ? $r->query('origin') : null;
            $rows = DB::table('seo_redirects')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('source', 'like', $like)->orWhere('target', 'like', $like)))
                ->when($origin, fn ($w) => $w->where('origin', $origin))
                ->orderByDesc('hits')->orderByDesc('wp_hits')->orderBy('source')->paginate(50)->withQueryString();
            $byOrigin = DB::table('seo_redirects')->select('origin', DB::raw('count(*) n'))->groupBy('origin')->pluck('n', 'origin');
            return view('admin.seo.index', compact('tab', 'q', 'rows', 'counts', 'origin', 'byOrigin'));
        }
        if ($tab === 'notfound') {
            $rows = DB::table('seo_404s')->when($q !== '', fn ($w) => $w->where('path', 'like', $like))
                ->orderByDesc('hits')->orderByDesc('last_seen_at')->paginate(50)->withQueryString();
            return view('admin.seo.index', compact('tab', 'q', 'rows', 'counts'));
        }
        $rows = DB::table('seo_pages')->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('path', 'like', $like)->orWhere('title', 'like', '%' . $q . '%')))
            ->orderBy('path')->paginate(50)->withQueryString();
        $edit = $r->query('edit') ? DB::table('seo_pages')->find((int) $r->query('edit')) : null;
        return view('admin.seo.index', compact('tab', 'q', 'rows', 'counts', 'edit'));
    }

    /** Search Console figures for the Performance tab. Position is impression-weighted, as Search Console averages it. */
    private function performance(Request $r): array
    {
        $gsc = ['configured' => \App\Support\SearchConsole::configured(), 'account' => \App\Support\SearchConsole::account(), 'site' => \App\Support\SearchConsole::site(),
            'status' => \App\Support\Settings::get('seo.gsc_status')];
        $last = DB::table('seo_gsc_daily')->max('date');
        if (!$last) return ['gsc' => $gsc, 'kp' => null];
        $end = \Carbon\Carbon::parse($last);
        $sum = fn ($a, $b) => DB::table('seo_gsc_daily')->whereBetween('date', [$a, $b])
            ->selectRaw('COALESCE(SUM(clicks),0) c, COALESCE(SUM(impressions),0) i, SUM(position * impressions) / NULLIF(SUM(impressions),0) p')->first();
        $cur = $sum($end->copy()->subDays(27)->toDateString(), $end->toDateString());
        $prev = $sum($end->copy()->subDays(55)->toDateString(), $end->copy()->subDays(28)->toDateString());
        $kp = ['end' => $end, 'clicks' => (int) $cur->c, 'clicks_prev' => (int) $prev->c, 'impr' => (int) $cur->i, 'impr_prev' => (int) $prev->i,
            'ctr' => $cur->i ? $cur->c / $cur->i * 100 : 0, 'ctr_prev' => $prev->i ? $prev->c / $prev->i * 100 : 0, 'pos' => $cur->p !== null ? (float) $cur->p : null, 'pos_prev' => $prev->p !== null ? (float) $prev->p : null];

        $months = DB::table('seo_gsc_daily')->selectRaw("DATE_FORMAT(date, '%Y-%m-01') m, SUM(clicks) c, SUM(impressions) i, SUM(position * impressions) / NULLIF(SUM(impressions),0) p, COUNT(*) d")
            ->groupBy('m')->orderBy('m')->get();
        $chartClicks = ['type' => 'bar', 'height' => 220, 'labels' => $months->map(fn ($x) => \Carbon\Carbon::parse($x->m)->format('M y'))->all(), 'maxLabels' => 8,
            'series' => [['name' => 'Clicks', 'data' => $months->map(fn ($x) => (int) $x->c)->all()]]];
        $chartPos = ['type' => 'line', 'height' => 200, 'labels' => $chartClicks['labels'], 'maxLabels' => 8, 'series' => [['name' => 'Average position', 'data' => $months->map(fn ($x) => round((float) $x->p, 1))->all()]]];

        // queries and pages: the chosen month (default the last complete one) against the month before
        $avail = DB::table('seo_gsc_rows')->distinct()->orderByDesc('month')->pluck('month')->map(fn ($m) => substr((string) $m, 0, 10))->all();
        $month = in_array($r->query('month'), $avail, true) ? $r->query('month') : ($avail[1] ?? $avail[0] ?? null);
        $lists = [];
        if ($month) {
            $before = \Carbon\Carbon::parse($month)->subMonthNoOverflow()->toDateString();
            foreach (['query', 'page'] as $kind) {
                $was = DB::table('seo_gsc_rows')->where('month', $before)->where('kind', $kind)->pluck('position', 'key_hash');
                $rows = DB::table('seo_gsc_rows')->where('month', $month)->where('kind', $kind)->orderByDesc('clicks')->orderByDesc('impressions')->limit(1000)->get();
                foreach ($rows as $x) $x->was = isset($was[$x->key_hash]) ? (float) $was[$x->key_hash] : null;
                $lists[$kind] = $rows->take(50);
                // movers: enough impressions to mean something, in both months
                $moved = $rows->filter(fn ($x) => $x->was !== null && $x->impressions >= 20);
                $lists[$kind . '_down'] = $moved->sortByDesc(fn ($x) => (float) $x->position - $x->was)->filter(fn ($x) => (float) $x->position - $x->was >= 1)->take(10)->values();
                $lists[$kind . '_up'] = $moved->sortBy(fn ($x) => (float) $x->position - $x->was)->filter(fn ($x) => $x->was - (float) $x->position >= 1)->take(10)->values();
            }
        }
        return compact('gsc', 'kp', 'months', 'chartClicks', 'chartPos', 'avail', 'month', 'lists');
    }

    public function storeRedirect(Request $r)
    {
        $d = $r->validate([
            'source' => 'required|string|max:500',
            'target' => 'nullable|string|max:1000',
            'code' => 'required|in:301,302,410',
            'from_404' => 'nullable|integer',
        ]);
        $source = Seo::norm($d['source']);
        if ($source === '') return back()->with('admin_err', 'The old URL cannot be the home page.')->withInput();
        $target = $d['code'] === '410' ? null : trim((string) $d['target']);
        if ($d['code'] !== '410') {
            if ($target === '') return back()->with('admin_err', 'Enter where the old URL should go.')->withInput();
            if (preg_match('#^https?://(www\.)?(universaldriveshafts\.com|' . preg_quote((string) parse_url(config('app.url'), PHP_URL_HOST), '#') . ')(/|$)#i', $target)) {
                $target = (string) (parse_url($target, PHP_URL_PATH) ?: '/') . (($qs = parse_url($target, PHP_URL_QUERY)) ? '?' . $qs : '');
            }
            if (!preg_match('#^(https?://|/)#i', $target)) $target = '/' . $target;
            if ($target[0] === '/' && Seo::norm($target) === $source) return back()->with('admin_err', 'The redirect would point at itself.')->withInput();
        }
        $now = now();
        DB::table('seo_redirects')->upsert([['source' => $source, 'target' => $target, 'code' => (int) $d['code'], 'origin' => 'manual', 'note' => null, 'created_at' => $now, 'updated_at' => $now]],
            ['source'], ['target', 'code', 'origin', 'note', 'updated_at']);
        if (!empty($d['from_404'])) DB::table('seo_404s')->where('id', $d['from_404'])->delete();
        Seo::flush();
        AdminLog::record('seo.redirect', 'Redirect /' . $source . ' → ' . ($target ?? '410 Gone'), 'seo_redirect', $source);
        return back()->with('admin_ok', 'Saved: /' . $source . ' → ' . ($target ?? '410 Gone') . '.');
    }

    public function deleteRedirect(int $id)
    {
        $row = DB::table('seo_redirects')->find($id);
        if ($row) {
            DB::table('seo_redirects')->where('id', $id)->delete();
            Seo::flush();
            AdminLog::record('seo.redirect.delete', 'Deleted redirect /' . $row->source, 'seo_redirect', $row->source);
        }
        return back()->with('admin_ok', 'Redirect deleted.');
    }

    public function dismiss404(Request $r)
    {
        if ($r->input('all')) DB::table('seo_404s')->delete();
        else DB::table('seo_404s')->where('id', (int) $r->input('id'))->delete();
        return back()->with('admin_ok', $r->input('all') ? '404 log cleared.' : 'Removed from the 404 log.');
    }

    public function storePage(Request $r)
    {
        $d = $r->validate([
            'id' => 'nullable|integer',
            'path' => 'required|string|max:500',
            'title' => 'nullable|string|max:160',
            'description' => 'nullable|string|max:320',
            'robots' => 'nullable|in:index,noindex,noindex_nofollow',
        ]);
        $path = Seo::norm($d['path']);
        $row = ['path' => $path, 'title' => trim((string) $d['title']) ?: null, 'description' => trim((string) $d['description']) ?: null,
                'robots' => self::ROBOTS[$d['robots'] ?? ''] ?? null, 'updated_at' => now()];
        if (!$row['title'] && !$row['description'] && !$row['robots']) return back()->with('admin_err', 'Fill in a title, a description or a robots rule.')->withInput();
        if (!empty($d['id'])) DB::table('seo_pages')->where('id', $d['id'])->update($row);
        else DB::table('seo_pages')->upsert([$row + ['created_at' => now()]], ['path'], ['title', 'description', 'robots', 'updated_at']);
        Seo::flush();
        AdminLog::record('seo.page', 'SEO override for /' . $path, 'seo_page', $path);
        return redirect('/admin/seo?tab=pages')->with('admin_ok', 'Saved the SEO override for /' . $path . '.');
    }

    public function deletePage(int $id)
    {
        $row = DB::table('seo_pages')->find($id);
        if ($row) {
            DB::table('seo_pages')->where('id', $id)->delete();
            Seo::flush();
            AdminLog::record('seo.page.delete', 'Removed SEO override for /' . $row->path, 'seo_page', $row->path);
        }
        return redirect('/admin/seo?tab=pages')->with('admin_ok', 'Override removed: the page uses its automatic title again.');
    }
}
