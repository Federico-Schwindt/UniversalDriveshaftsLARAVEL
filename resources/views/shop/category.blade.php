@extends('layouts.app')
@php
  $page = (int) $data['page'];
  $cleanPath = '/shop/' . $group['slug'] . '/' . $cat['slug'];
  $isFiltered = $q !== '' || !empty($filters) || ($sort !== 'price_asc');
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
.ucathead__blurb{margin:.75rem 0 0;color:var(--ink-2);max-width:72ch;line-height:1.6}
.ucathead__series{display:flex;flex-wrap:wrap;align-items:center;gap:.45rem;margin-top:1rem;font-size:.84rem;color:var(--ink-faint)}
.ucathead__series a{font-family:var(--font-m);font-weight:600;font-size:.8rem;padding:.3rem .65rem;border-radius:var(--r-pill);background:#fff;border:1px solid var(--line);color:var(--blue)}
.ucathead__series a:hover{border-color:var(--blue)}
.ucathead__series a small{font-weight:500;color:var(--ink-faint);margin-left:.3rem}
</style>
@endpush

@push('scripts')
@include('shop.partials.js-i18n')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
<script src="{{ asset('js/shop-filters.js') }}?v={{ @filemtime(public_path('js/shop-filters.js')) }}" defer></script>
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
<script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@type' => 'CollectionPage', 'name' => $cat['name'], 'url' => $absBase . $cleanPath, 'description' => $first ?: $desc, 'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $count, 'itemListOrder' => 'https://schema.org/ItemListOrderAscending', 'itemListElement' => $items]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
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
    <h1>{{ \App\Support\PartGlossary::t($cat['name']) }}@if(isset($filters['pa_series'])) <span style="color:var(--ink-faint);font-weight:500">· {{ strtoupper($filters['pa_series']) }} {{ __('series') }}</span>@elseif($page > 1) <span style="color:var(--ink-faint);font-weight:500">{{ __('· page') }} {{ $page }}</span>@endif</h1>
    @if($intro !== '')<p class="ucathead__blurb">{{ $intro }}</p>@endif
    @if($catSeries)
      <nav class="ucathead__series" aria-label="{{ __('Series in this category') }}">
        <span>{{ __('By series:') }}</span>
        @foreach(array_slice($catSeries, 0, 12, true) as $slug => $sv)
          <a href="/series/{{ $slug }}" title="{{ __('All :series series parts', ['series' => $sv['name']]) }}">{{ \App\Support\PartGlossary::t($sv['name']) }}<small>{{ $sv['count'] }}</small></a>
        @endforeach
        <a href="/series" style="color:var(--ink-2)">{{ __('All series') }}</a>
      </nav>
    @endif
    @if(count($siblings) > 1)
      <div class="usiblings" role="list" aria-label="{{ __('Related categories') }}">
        @foreach($siblings as $s)
          @if($s['slug'] !== $cat['slug'])
            <a class="usib" role="listitem" href="/shop/{{ $group['slug'] }}/{{ $s['slug'] }}">
              <span>@if($s['image'])<img src="{{ $s['image'] }}" alt="{{ \App\Support\PartGlossary::t($s['name']) }}" width="30" height="30" loading="lazy">@else<x-icon name="package"/>@endif</span>
              <b>{{ \App\Support\PartGlossary::t($s['name']) }}</b>
            </a>
          @endif
        @endforeach
      </div>
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
        <option value="price_asc" @selected($sort==='price_asc')>{{ __('Price: low to high') }}</option>
        <option value="price_desc" @selected($sort==='price_desc')>{{ __('Price: high to low') }}</option>
        <option value="part_asc" @selected($sort==='part_asc')>{{ __('Part number: A to Z') }}</option>
        <option value="part_desc" @selected($sort==='part_desc')>{{ __('Part number: Z to A') }}</option>
      </select>
    </div>

    <div id="shop-table">@include('shop.partials.table')</div>
  </div>
</section>

<div class="stock-pop" id="stock-pop"></div>
<div class="img-pop" id="img-pop" aria-hidden="true"><img alt=""></div>

<div class="wrap">
  <p class="xref-note">{{ __('Manufacturer names, series designations and part numbers shown in this catalog (including Spicer') }}<sup>{{ __('®') }}</sup>{{ __(', Dana') }}<sup>{{ __('®') }}</sup>{{ __(', Neapco') }}<sup>{{ __('®') }}</sup> {{ __('and vehicle makes) are trademarks of their respective owners and are used for identification and cross-reference only. They do not indicate affiliation, endorsement, or that the part was made by that manufacturer unless the brand is expressly stated. Parts under a UDS part number are UDS-brand replacements. Interchange data, stock and images are provided for reference and may contain errors, so verify fitment before ordering.') }} <a href="/copyright">{{ __('Copyright & trademark notice') }}</a> {{ __('·') }} <a href="/terms">{{ __('Terms of use') }}</a></p>
</div>
@endsection
