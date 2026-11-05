@extends('admin.layout')
@section('title','SEO')
@section('crumb','Catalog')
@section('body')
@php $O = \App\Http\Controllers\Admin\SeoController::ORIGINS; @endphp
<div class="chips">@foreach(\App\Http\Controllers\Admin\SeoController::TABS as $k=>$l)<a href="/admin/seo?tab={{ $k }}" class="chip @if($tab===$k) is-on @endif">{{ $l }}@if($counts[$k] !== null) <b>{{ number_format($counts[$k]) }}</b>@endif</a>@endforeach</div>

@if($tab === 'performance')
@php
  // position: lower is better, so a drop in the number is good news
  $posDelta = function ($cur, $was) {
      if ($cur === null || $was === null) return '<span class="muted">—</span>';
      $d = (float) $was - (float) $cur;
      if (abs($d) < 0.05) return '<span class="muted">=</span>';
      return '<span class="' . ($d > 0 ? 'up' : 'down') . '" style="color:var(' . ($d > 0 ? '--ok' : '--err') . ')">' . ($d > 0 ? '▲ ' : '▼ ') . number_format(abs($d), 1) . '</span>';
  };
  $pct = fn ($cur, $prev) => $prev > 0 ? (($cur - $prev) / $prev) * 100 : null;
  $delta = function ($cur, $prev) use ($pct) { $p = $pct($cur, $prev); return $p === null ? '<span class="kpi__delta muted">—</span>' : '<span class="kpi__delta ' . ($p >= 0 ? 'up' : 'down') . '">' . ($p >= 0 ? '▲' : '▼') . ' ' . number_format(abs($p), 0) . '% <span class="muted">vs prior 28 days</span></span>'; };
  // the canonical address shows as a bare path; anything else (http://, www.) keeps its host so duplicates stand out
  $short = fn ($u) => str_starts_with((string) $u, 'https://universaldriveshafts.com') ? (substr((string) $u, 32) ?: '/') : preg_replace('#^https?://#', '', (string) $u);
@endphp
@if(!$gsc['configured'] || !$kp)
<div class="card mb"><div class="card__head"><h2>Connect Google Search Console</h2></div><div class="card__body small" style="max-width:80ch">
  @if(!$gsc['configured'])
    <p>Average position, clicks and the searches people find the store with come from Search Console. It needs a Google Cloud
    service account that is a user of the property, its <b>JSON key</b> at <span class="mono">storage/app/google/search-console.json</span>
    and the <b>Google Search Console API</b> enabled in that project.</p>
  @else
    <p>Key found for <b class="mono">{{ $gsc['account'] }}</b> · property <b class="mono">{{ $gsc['site'] }}</b>. The first sync loads the 16 months Google keeps; it then refreshes every morning.</p>
  @endif
  @if($gsc['status'])<div class="flash flash--{{ $gsc['status']['ok'] ? 'ok' : 'warn' }}" style="margin-top:10px">{!! ic($gsc['status']['ok'] ? 'check' : 'alert') !!}<span>Last sync {{ \Carbon\Carbon::parse($gsc['status']['at'])->diffForHumans() }}: {{ $gsc['status']['message'] }}</span></div>@endif
</div></div>
@else
<div class="kpis mb" style="grid-template-columns:repeat(auto-fit,minmax(min(100%,190px),1fr))">
  <div class="kpi"><span class="kpi__label" title="Impression-weighted, as Search Console shows it. Lower is better.">Average position</span><span class="kpi__value">{{ $kp['pos'] !== null ? number_format($kp['pos'], 1) : '—' }}</span><span class="kpi__delta">{!! $posDelta($kp['pos'], $kp['pos_prev']) !!} <span class="muted">vs prior 28 days</span></span></div>
  <div class="kpi"><span class="kpi__label">Clicks</span><span class="kpi__value">{{ number_format($kp['clicks']) }}</span>{!! $delta($kp['clicks'], $kp['clicks_prev']) !!}</div>
  <div class="kpi"><span class="kpi__label">Impressions</span><span class="kpi__value">{{ number_format($kp['impr']) }}</span>{!! $delta($kp['impr'], $kp['impr_prev']) !!}</div>
  <div class="kpi"><span class="kpi__label">Click-through rate</span><span class="kpi__value">{{ number_format($kp['ctr'], 2) }}%</span>{!! $delta($kp['ctr'], $kp['ctr_prev']) !!}</div>
</div>
<p class="muted small" style="margin:-4px 0 12px">Last 28 days to {{ $kp['end']->format('M j, Y') }} (Search Console runs 2–3 days behind) · {{ $gsc['site'] }}@if($gsc['status']) · synced {{ \Carbon\Carbon::parse($gsc['status']['at'])->diffForHumans() }}@endif</p>
<div class="grid grid--2 mb">
  <div class="card"><div class="card__head"><h2>Clicks from Google, by month</h2></div><div class="card__body"><svg class="chart" role="img" aria-label="Clicks per month" data-chart='{{ json_encode($chartClicks) }}'></svg></div></div>
  <div class="card"><div class="card__head"><h2>Average position, by month</h2><span class="muted small">lower is better</span></div><div class="card__body"><svg class="chart" role="img" aria-label="Average position per month" data-chart='{{ json_encode($chartPos) }}'></svg></div></div>
</div>
<div class="card mb"><div class="card__head"><h2>Month by month</h2></div><div class="tbl-wrap"><table class="tbl tbl--compact">
  <thead><tr><th>Month</th><th class="right">Clicks</th><th class="right">Impressions</th><th class="right">CTR</th><th class="right">Avg. position</th><th class="right">vs prior month</th></tr></thead>
  <tbody>@php $prevP = null; $rowsM = []; foreach ($months as $m) { $rowsM[] = [$m, $prevP]; $prevP = $m->p; } @endphp
  @foreach(array_reverse($rowsM) as [$m, $pp])<tr><td class="nowrap"><b>{{ \Carbon\Carbon::parse($m->m)->format('M Y') }}</b>@if($m->d < 28) <span class="muted small">· {{ $m->d }} days</span>@endif</td><td class="right num">{{ number_format($m->c) }}</td><td class="right num">{{ number_format($m->i) }}</td><td class="right num">{{ $m->i ? number_format($m->c / $m->i * 100, 2) . '%' : '—' }}</td><td class="right num"><b>{{ $m->p !== null ? number_format($m->p, 1) : '—' }}</b></td><td class="right">{!! $posDelta($m->p, $pp) !!}</td></tr>@endforeach
  </tbody></table></div></div>

@if($month)
<form class="toolbar" method="get" action="/admin/seo"><input type="hidden" name="tab" value="performance">
  <label class="small muted" for="seo-month">Searches and pages for</label>
  <select class="input input--sm" id="seo-month" name="month" onchange="this.form.submit()">@foreach($avail as $a)<option value="{{ $a }}" @selected($a === $month)>{{ \Carbon\Carbon::parse($a)->format('F Y') }}{{ $loop->first ? ' (so far)' : '' }}</option>@endforeach</select>
  <span class="small muted">compared with the month before</span>
</form>
<div class="grid grid--2 mb">
  @foreach(['query' => 'Searches', 'page' => 'Pages'] as $kind => $title)
  <div class="card"><div class="card__head"><h2>{{ $title }}</h2><span class="muted small">top 50 by clicks</span></div><div class="tbl-wrap" style="max-height:520px;overflow-y:auto"><table class="tbl tbl--compact">
    <thead><tr><th>{{ $kind === 'query' ? 'Search' : 'Page' }}</th><th class="right">Clicks</th><th class="right">Impr.</th><th class="right">Position</th><th class="right">Change</th></tr></thead>
    <tbody>@forelse($lists[$kind] as $x)<tr><td style="max-width:320px;overflow-wrap:anywhere">@if($kind === 'page')<a href="{{ $x->key }}" target="_blank" rel="noopener" class="mono small">{{ $short($x->key) }}</a>@else{{ $x->key }}@endif</td><td class="right num">{{ number_format($x->clicks) }}</td><td class="right num">{{ number_format($x->impressions) }}</td><td class="right num"><b>{{ number_format($x->position, 1) }}</b></td><td class="right">{!! $posDelta($x->position, $x->was) !!}</td></tr>@empty<tr><td colspan="5" class="muted">No data for this month.</td></tr>@endforelse</tbody>
  </table></div></div>
  @endforeach
</div>
<div class="grid grid--2">
  @foreach(['query_down' => 'Searches losing ground', 'page_down' => 'Pages losing ground', 'query_up' => 'Searches gaining', 'page_up' => 'Pages gaining'] as $k => $title)
  <div class="card"><div class="card__head"><h2>{{ $title }}</h2><span class="muted small">20+ impressions both months</span></div><div class="tbl-wrap"><table class="tbl tbl--compact"><tbody>
    @forelse($lists[$k] as $x)<tr><td style="max-width:320px;overflow-wrap:anywhere">{{ str_starts_with($k, 'page') ? $short($x->key) : $x->key }}</td><td class="right num nowrap">{{ number_format($x->was, 1) }} → <b>{{ number_format($x->position, 1) }}</b></td><td class="right">{!! $posDelta($x->position, $x->was) !!}</td></tr>@empty<tr><td class="muted">Nothing moved by a full position.</td></tr>@endforelse
  </tbody></table></div></div>
  @endforeach
</div>
@endif
@endif
@endif

@if($tab === 'redirects')
<p class="muted small" style="max-width:80ch;margin:0 0 12px">Old URLs that would answer 404 are sent on with a 301. Imported from the old WordPress site (Rank Math, Redirection, ThinkRank and every old product, page and category URL); a real page on the store always wins over a redirect.</p>
<div class="card"><div class="card__head"><h2>Add or change a redirect</h2></div>
  <form method="post" action="/admin/seo/redirects" class="card__body form-grid">@csrf
    <div class="field"><label>Old URL</label><input class="input mono" name="source" value="{{ old('source') }}" required placeholder="/product/old-slug"></div>
    <div class="field"><label>Send to</label><input class="input mono" name="target" value="{{ old('target') }}" placeholder="/parts/5-1310X"></div>
    <div class="field"><label>Type</label><select class="select" name="code"><option value="301">301 moved permanently</option><option value="302" @selected(old('code')==='302')>302 temporary</option><option value="410" @selected(old('code')==='410')>410 gone (no target)</option></select></div>
    <div class="field" style="justify-content:flex-end"><button class="btn btn--primary">{!! ic('check') !!} Save redirect</button></div>
  </form></div>
<form class="toolbar" method="get" action="/admin/seo"><input type="hidden" name="tab" value="redirects">
  <div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $q }}" placeholder="Old or new URL…"></div>
  <select class="select" name="origin" onchange="this.form.submit()"><option value="">All sources</option>@foreach($O as $k=>$l)<option value="{{ $k }}" @selected($origin===$k)>{{ $l }} ({{ number_format($byOrigin[$k] ?? 0) }})</option>@endforeach</select>
  <button class="btn btn--sm">Search</button></form>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Old URL</th><th>Goes to</th><th>Source</th><th class="right">Hits here</th><th class="right">Hits on WordPress</th><th></th></tr></thead><tbody>
  @forelse($rows as $r)
  <tr>
    <td class="primary"><span class="title mono">/{{ $r->source }}</span>@if($r->note)<small>{{ $r->note }}</small>@endif</td>
    <td data-label="Goes to" class="mono small">@if($r->code === 410)<span class="badge badge--err">410 gone</span>@else<a href="{{ $r->target }}" target="_blank" rel="noopener">{{ $r->target }}</a>@if($r->code !== 301) <span class="badge badge--plain tiny">{{ $r->code }}</span>@endif @endif</td>
    <td data-label="Source"><span class="badge badge--plain">{{ $O[$r->origin] ?? $r->origin }}</span></td>
    <td class="right num" data-label="Hits here">{{ number_format($r->hits) }}@if($r->last_hit_at)<small class="muted"> · {{ \Carbon\Carbon::parse($r->last_hit_at)->diffForHumans(null, true) }}</small>@endif</td>
    <td class="right num muted" data-label="Hits on WordPress">{{ $r->wp_hits ? number_format($r->wp_hits) : '—' }}</td>
    <td class="actions"><a href="#" class="btn btn--xs" data-post="/admin/seo/redirects/{{ $r->id }}/delete" data-confirm="Delete the redirect for /{{ $r->source }}? That URL will answer 404.">{!! ic('trash') !!}</a></td>
  </tr>
  @empty<tr><td colspan="6"><div class="empty">{!! ic('search') !!}<b>No redirects match</b></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>

@elseif($tab === 'notfound')
<p class="muted small" style="max-width:80ch;margin:0 0 12px">Addresses visitors or search engines asked for that answered 404 and have no redirect. Point the ones that matter at the right page; scanners' probes for WordPress files are not logged.</p>
<form class="toolbar" method="get" action="/admin/seo"><input type="hidden" name="tab" value="notfound"><div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $q }}" placeholder="URL…"></div><button class="btn btn--sm">Search</button>
  @if($counts['notfound'])<a href="#" class="btn btn--sm" data-post="/admin/seo/404/dismiss" data-fields='{"all":1}' data-confirm="Clear the whole 404 log?">Clear log</a>@endif</form>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>URL</th><th class="right">Hits</th><th>Last seen</th><th>Send it to</th><th></th></tr></thead><tbody>
  @forelse($rows as $r)
  <tr>
    <td class="primary"><span class="title mono">/{{ $r->path }}</span>@if($r->referer)<small class="muted">from {{ \Illuminate\Support\Str::limit($r->referer, 70) }}</small>@endif</td>
    <td class="right num" data-label="Hits">{{ number_format($r->hits) }}</td>
    <td data-label="Last seen" class="small">{{ $r->last_seen_at ? \Carbon\Carbon::parse($r->last_seen_at)->diffForHumans() : '' }}</td>
    <td data-label="Send it to"><form method="post" action="/admin/seo/redirects" style="display:flex;gap:6px">@csrf<input type="hidden" name="source" value="/{{ $r->path }}"><input type="hidden" name="code" value="301"><input type="hidden" name="from_404" value="{{ $r->id }}"><input class="input input--sm mono" name="target" required placeholder="/shop/…" style="min-width:180px"><button class="btn btn--xs btn--primary">Redirect</button></form></td>
    <td class="actions"><a href="#" class="btn btn--xs" data-post="/admin/seo/404/dismiss" data-fields='{"id":{{ $r->id }}}' title="Remove from the log">{!! ic('x') !!}</a></td>
  </tr>
  @empty<tr><td colspan="5"><div class="empty">{!! ic('check') !!}<b>No 404s logged</b><span class="muted small">Every address asked for so far has a page or a redirect.</span></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>

@elseif($tab === 'pages')
<p class="muted small" style="max-width:80ch;margin:0 0 12px">Every page writes its own title and description automatically (products and categories from their specs). Set an override here for any URL; products and categories can also be edited from their own form. Overrides apply to the English pages.</p>
@php $e = $edit; $robotsKey = $e ? (array_search($e->robots, \App\Http\Controllers\Admin\SeoController::ROBOTS, true) ?: '') : old('robots', ''); @endphp
<div class="card"><div class="card__head"><h2>{{ $e ? 'Edit override for /' . $e->path : 'Add an override' }}</h2>@if($e)<a href="/admin/seo?tab=pages" class="btn btn--xs">Cancel</a>@endif</div>
  <form method="post" action="/admin/seo/pages" class="card__body form-grid">@csrf
    @if($e)<input type="hidden" name="id" value="{{ $e->id }}">@endif
    <div class="field"><label>Page URL</label><input class="input mono" name="path" value="{{ old('path', $e ? '/' . $e->path : '') }}" required placeholder="/heavy-duty-driveshafts"></div>
    <div class="field"><label>Robots</label><select class="select" name="robots"><option value="">Automatic</option><option value="index" @selected($robotsKey==='index')>Index, follow</option><option value="noindex" @selected($robotsKey==='noindex')>Noindex, follow</option><option value="noindex_nofollow" @selected($robotsKey==='noindex_nofollow')>Noindex, nofollow</option></select></div>
    <div class="field span-2"><label>Title <span class="muted" data-count-for="seo-title"></span></label><input class="input" id="seo-title" name="title" maxlength="160" value="{{ old('title', $e->title ?? '') }}" placeholder="Blank = automatic. Aim for 50–60 characters."></div>
    <div class="field span-2"><label>Meta description <span class="muted" data-count-for="seo-desc"></span></label><textarea class="input" id="seo-desc" name="description" rows="2" maxlength="320" placeholder="Blank = automatic. Aim for 140–160 characters.">{{ old('description', $e->description ?? '') }}</textarea></div>
    <div class="field span-2"><button class="btn btn--primary" style="align-self:flex-start">{!! ic('check') !!} Save override</button></div>
  </form></div>
<form class="toolbar" method="get" action="/admin/seo"><input type="hidden" name="tab" value="pages"><div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $q }}" placeholder="URL or title…"></div><button class="btn btn--sm">Search</button></form>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Page</th><th>Title</th><th>Description</th><th>Robots</th><th></th></tr></thead><tbody>
  @forelse($rows as $r)
  <tr data-href="/admin/seo?tab=pages&edit={{ $r->id }}">
    <td class="primary"><span class="title mono">/{{ $r->path }}</span></td>
    <td data-label="Title" class="small">{{ $r->title ?: '—' }}</td>
    <td data-label="Description" class="small muted">{{ \Illuminate\Support\Str::limit($r->description ?: '—', 90) }}</td>
    <td data-label="Robots" class="small">{{ $r->robots ?: 'auto' }}</td>
    <td class="actions"><a href="#" class="btn btn--xs" data-post="/admin/seo/pages/{{ $r->id }}/delete" data-confirm="Remove the override for /{{ $r->path }}?">{!! ic('trash') !!}</a></td>
  </tr>
  @empty<tr><td colspan="5"><div class="empty">{!! ic('edit') !!}<b>No overrides</b><span class="muted small">All pages use their automatic titles.</span></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>
<script>
document.querySelectorAll('[data-count-for]').forEach(function (c) {
  var f = document.getElementById(c.dataset.countFor); if (!f) return;
  var lim = f.id === 'seo-title' ? 60 : 160;
  var up = function () { var n = f.value.length; c.textContent = n ? '(' + n + ' / ' + lim + ')' : ''; c.style.color = n > lim ? 'var(--err)' : ''; };
  f.addEventListener('input', up); up();
});
</script>
@endif
@endsection
