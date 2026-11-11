@extends('layouts.app')
@php
  $page = (int) $data['page'];
  $cleanPath = '/shop/' . $group['slug'] . '/' . $cat['slug'];
  $isFiltered = $q !== '' || !empty($filters) || ($sort !== 'popular');
  $count = (int) $data['total'];
  $seriesNames = array_column($catSeries, 'name');
  $catName = \App\Support\PartGlossary::t($cat['name']);
  $sfx = fn ($t) => __($t);
  // title: "{count} {name} in stock: 1310, 1350, 1410 series" trimmed to 60 chars; categories with a search name
  // (config/seo-copy.php "title", from Search Console queries) lead with it instead: "Driveshaft Slip Yokes: 1310, 1350 Series"
  $searchName = !empty($seoCopy['title']) ? __((string) $seoCopy['title']) : null;
  $titleBase = $searchName ?? ((!$isFiltered && $count ? number_format($count) . ' ' : '') . $catName);
  if ($seriesNames && !$isFiltered) {
    $names = array_slice($seriesNames, 0, 5);
    while ($names && mb_strlen($titleBase . ': ' . implode(', ', $names) . $sfx(' series') . ' | UDS') > 60) array_pop($names);
    if ($names) $titleBase .= ': ' . implode(', ', $names) . $sfx(' series');
    elseif (mb_strlen($titleBase . $sfx(' in stock') . ' | UDS') <= 60) $titleBase .= $sfx(' in stock');
  } elseif (!$isFiltered && mb_strlen($titleBase . $sfx(' in stock') . ' | UDS') <= 60) $titleBase .= $sfx(' in stock');
  if (isset($filters['pa_series'])) $titleBase = $catName . ' – ' . ($catSeries[$filters['pa_series']]['name'] ?? strtoupper($filters['pa_series'])) . $sfx(' series');
  if ($page > 1) $titleBase = ($count ? number_format($count) . ' ' : '') . $catName . $sfx(' – page ') . $page;
  $title = $titleBase . ' | UDS';
  // description 140–160 from the category copy + live count
  $intro = ($seoCopy['intro'] ?? '') !== '' ? __((string) $seoCopy['intro']) : '';
  $first = $intro !== '' ? preg_split('/(?<=[.!?])\s+/', $intro)[0] : '';
  $desc = ($count ? number_format($count) . ' ' : '') . $catName . $sfx(' in stock') . ($seriesNames ? $sfx(' in ') . implode(', ', array_slice($seriesNames, 0, 4)) . $sfx(' series') : '') . $sfx(', ships same day from Florida. ') . $first;
  if (mb_strlen($desc) < 120 && $intro !== '') $desc = trim($desc . ' ' . (preg_split('/(?<=[.!?])\s+/', $intro)[1] ?? '')); // tiny categories: add a second sentence of the copy
  if (mb_strlen($desc) < 120) $desc = trim($desc . ' ' . __('Live pricing and stock, UPS rates at checkout, cross-referenced to OEM and Spicer numbers.'));
  if (!empty($seoCopy['desc'])) $desc = str_replace('{count}', $count ? number_format($count) : '', __((string) $seoCopy['desc']));
  $desc = trim(preg_replace('/^\s+|\s{2,}/', ' ', $desc));
  if (mb_strlen($desc) > 160) $desc = rtrim(mb_substr($desc, 0, 157), ' ,.;') . '...';
  if ($page > 1) { $desc = 'Page ' . $page . ' of ' . $data['pages'] . ': ' . $desc; if (mb_strlen($desc) > 160) $desc = rtrim(mb_substr($desc, 0, 157), ' ,.;') . '...'; }
  // the category's own SEO title / description from the admin (unfiltered first page only)
  if (!$isFiltered && $page === 1) { $title = ($seoOwn['uds_seo_title'] ?? '') ?: $title; $desc = ($seoOwn['uds_seo_desc'] ?? '') ?: $desc; }
  $canonical = (!$isFiltered && $page > 1) ? $cleanPath . '?page=' . $page : $cleanPath;
  $absBase = rtrim(config('app.url'), '/');
@endphp
@section('title', $title)
@section('desc', $desc)
@section('canonical', $canonical)
@if($isFiltered)@section('robots', 'noindex,follow')@endif

@push('head')
<style>
.ucathead__series{display:flex;flex-wrap:wrap;align-items:center;gap:.45rem;margin-top:1rem;font-size:.84rem;color:var(--ink-faint)}
.ucathead__series a{font-family:var(--font-m);font-weight:600;font-size:.8rem;padding:.3rem .6rem;border-radius:var(--r-sm);background:#fff;border:1px solid var(--line);color:var(--blue)}
.ucathead__series a:hover{border-color:var(--blue)}
.ucathead__series a.is-on{background:var(--blue);border-color:var(--blue);color:#fff}.ucathead__series a.is-on small{color:rgba(255,255,255,.75)}
.ucathead__series a small{font-weight:500;color:var(--ink-faint);margin-left:.3rem}
</style>
@endpush

@push('scripts')
@include('shop.partials.js-i18n')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
<script src="{{ asset('js/shop-filters.js') }}?v={{ @filemtime(public_path('js/shop-filters.js')) }}" defer></script>
@if($measure)<script src="{{ asset('js/measure.js') }}?v={{ @filemtime(public_path('js/measure.js')) }}" defer></script>@endif
@endpush

@push('schema')
@include('partials.breadcrumb-schema', ['crumbs' => [
  ['Home', $absBase.'/'], ['Catalog', $absBase.'/shop'],
  [$group['label'], $absBase.'/shop/'.$group['slug']], [$cat['name'], $absBase.$cleanPath],
]])
@if($page === 1 && !$isFiltered && count($data['items']))
@php
  $svc = app(\App\Services\CatalogService::class);
  $items = collect($data['items'])->values()->map(fn ($p, $i) => [
    '@type' => 'ListItem', 'position' => $i + 1,
    'item' => array_filter([
      '@type' => 'Product', 'name' => $p['sku'] . ' ' . \Illuminate\Support\Str::limit($svc->cleanName($p), 80, ''), 'sku' => $p['sku'], 'mpn' => $p['sku'],
      'url' => $absBase . '/parts/' . rawurlencode($p['sku']),
      'image' => $p['image'] ? (str_starts_with($p['image'], 'http') ? $p['image'] : $absBase . $p['image']) : null,
      'brand' => ['@type' => 'Brand', 'name' => $p['brand'] ?: 'UDS'],
      'offers' => ['@type' => 'Offer', 'price' => number_format($p['price'], 2, '.', ''), 'priceCurrency' => 'USD',
        'availability' => 'https://schema.org/' . ($p['stock_status'] === 'outofstock' ? 'OutOfStock' : ($p['stock_status'] === 'onbackorder' ? 'BackOrder' : 'InStock')),
        'url' => $absBase . '/parts/' . rawurlencode($p['sku'])],
    ], fn ($v) => $v !== null),
  ])->all();
@endphp
<script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $cat['name'], 'url' => $absBase . $cleanPath, 'description' => $first ?: $desc, 'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $count, 'itemListOrder' => 'https://schema.org/ItemListOrderAscending', 'itemListElement' => $items]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endif
@endpush

@section('content')
@include('shop.partials.style')

<header class="ucathead">
  <div class="wrap">
    <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
      <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
      <a href="/shop">{{ __('Catalog') }}</a><x-icon name="caret-right"/>
      <a href="/shop/{{ $group['slug'] }}">{{ __($group['label']) }}</a><x-icon name="caret-right"/>
      <span aria-current="page">{{ \App\Support\PartGlossary::t($cat['name']) }}</span>
    </nav>
    <div class="ucathead__row">
      <h1>{{ \App\Support\PartGlossary::t($cat['name']) }}@if(isset($filters['pa_series'])) <span class="ucathead__sub">· {{ strtoupper($filters['pa_series']) }} {{ __('series') }}</span>@elseif($page > 1) <span class="ucathead__sub">{{ __('· page') }} {{ $page }}</span>@endif</h1>
      {{-- the category copy shows on hover / focus only (owner, 2026-11): still on the page for search engines --}}
      @if($intro !== '')<span class="cinfo"><button type="button" class="cinfo__btn"><x-icon name="info"/>{{ __('About :name', ['name' => mb_strtolower(\App\Support\PartGlossary::t($cat['name']))]) }}</button><span class="cinfo__pop" role="tooltip">{{ $intro }}</span></span>@endif
    </div>
    @if($catSeries)
      <nav class="ucathead__series" aria-label="{{ __('Series in this category') }}">
        <span>{{ __('By series:') }}</span>
        {{-- filter this table in place (audit 2026-11-11: the chips used to leave for /series/…, with counts that did not match) --}}
        @php $onSeries = $filters['pa_series'] ?? null; @endphp
        @foreach(array_slice($catSeries, 0, 12, true) as $slug => $sv)
          <a href="/shop/{{ $group['slug'] }}/{{ $cat['slug'] }}{{ $onSeries === $slug ? '' : '?series=' . $slug }}" @if($onSeries === $slug) aria-current="true" class="is-on" @endif title="{{ __(':series series :type', ['series' => $sv['name'], 'type' => mb_strtolower(\App\Support\PartGlossary::t($cat['name']))]) }}">{{ \App\Support\PartGlossary::t($sv['name']) }}<small>{{ $sv['count'] }}</small></a>
        @endforeach
        @if($onSeries)<a href="/shop/{{ $group['slug'] }}/{{ $cat['slug'] }}" style="color:var(--ink-2)">{{ __('All series') }}</a>@endif
      </nav>
    @endif
  </div>
</header>

<section class="section" style="padding-top:0">
  <div class="wrap">
    <div class="shop-controls" id="shop-controls">
      <div class="shop-search">
        <input type="text" id="shop-q" value="{{ $q }}" placeholder="{{ __('Search part number') }}" autocomplete="off" aria-label="{{ __('Search part number') }}">
        <button type="button" id="shop-q-btn" aria-label="{{ __('Search') }}"><x-icon name="search"/></button>
      </div>
      @foreach($schema['filters'] as $f)
        <select class="sortsel shop-filter" data-name="{{ $f['param'] }}" aria-label="{{ __('Filter by :name', ['name' => __($f['label'])]) }}">
          <option value="">{{ __($f['label']) }}{{ __(': all') }}</option>
          @foreach($f['options'] as $slug => $name)<option value="{{ $slug }}" @selected(($filters[$f['key']] ?? '')===$slug)>{{ $name }}</option>@endforeach
        </select>
      @endforeach
      <select class="sortsel" id="shop-sort" aria-label="{{ __('Sort results') }}" style="margin-left:auto">
        @if(str_starts_with($sort,'attr:'))<option value="{{ $sort }}" selected>{{ __('Sorted by column') }}</option>@endif
        <option value="popular" @selected($sort==='popular')>{{ __('Most popular') }}</option>
        <option value="price_asc" @selected($sort==='price_asc')>{{ __('Price: low to high') }}</option>
        <option value="price_desc" @selected($sort==='price_desc')>{{ __('Price: high to low') }}</option>
        <option value="part_asc" @selected($sort==='part_asc')>{{ __('Part number: A to Z') }}</option>
        <option value="part_desc" @selected($sort==='part_desc')>{{ __('Part number: Z to A') }}</option>
      </select>
    </div>

    @if($measure)
      @php $mdLabels = array_column($schema['columns'], 'label', 'key'); @endphp
      <details class="md-panel" data-md-scope>
        <summary><x-icon name="ruler"/>{{ __('How to measure') }}</summary>
        <div class="md-panel__body">
          <div>{!! \App\Support\Measure\Sketch::uniq($measure['svg']) !!}</div>
          <div>
            <ul class="md-legend">
              @foreach($measure['codes'] as $key => $letter)
                <li data-md="{{ $key }}"><span class="md-chip">{{ $letter }}</span>{{ __($mdLabels[$key]) }}</li>
              @endforeach
            </ul>
            <p class="md-note-txt">{{ __('Letters match the table columns. Not to scale. Measure the old part, or the mating part, before you order.') }}</p>
          </div>
        </div>
      </details>
    @endif

  </div>
  {{-- outside the rail: the table uses the screen and always shows every column ("TABLE FIT" in uds-2026.css) --}}
  <div class="shop-bleed"><div id="shop-table" class="ptfit">@include('shop.partials.table')</div>@include('shop.partials.table-fit')</div>
</section>

@if($measure)
<div class="md-pop" id="md-pop" aria-hidden="true"><p class="md-pop__t"><span class="md-chip"></span><span></span></p>{!! \App\Support\Measure\Sketch::uniq($measure['svg']) !!}</div>
@endif

<div class="stock-pop" id="stock-pop"></div>
<div class="img-pop" id="img-pop" aria-hidden="true"><img alt=""></div>

@endsection
