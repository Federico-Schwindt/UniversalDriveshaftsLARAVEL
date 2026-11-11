@extends('layouts.app')
@php
  $base = rtrim(config('app.url'), '/');
  $svc = app(\App\Services\CatalogService::class);
  $name = $s['name'];
  $catNames = array_map(fn ($c) => $c['cat']['name'], $cats);
  $title = $name . ' Series Driveshaft Parts: U-Joints, Yokes & More | UDS';
  if (mb_strlen($title) > 60) $title = $name . ' Series Driveshaft Parts: U-Joints, Yokes | UDS';
  if (mb_strlen($title) > 60) $title = $name . ' Series Driveshaft Parts | UDS';
  $dims = $s['cap'] ? ' Nominal ' . $s['cap'] . ' caps' . ($s['width'] ? ' × ' . $s['width'] . ' wide' : '') . '.' : '';
  $desc = number_format($total) . ' ' . $name . ' series driveshaft parts in stock: ' . implode(', ', array_map('strtolower', array_slice($catNames, 0, 4))) . '.' . $dims . ' Ships same day from Florida.';
  if (mb_strlen($desc) > 160) $desc = number_format($total) . ' ' . $name . ' series driveshaft parts in stock: ' . implode(', ', array_map('strtolower', array_slice($catNames, 0, 3))) . '.' . $dims . ' Ships same day from Florida.';
  if (mb_strlen($desc) > 160) $desc = rtrim(mb_substr($desc, 0, 157), ' ,.;') . '...';
  $others = array_values(array_filter($all, fn ($x) => $x['slug'] !== $s['slug']));
  $hero = null;
  foreach ($cats as $c) foreach ($c['items'] as $it) { if ($it['image']) { $hero = $it; break 2; } }

  // FAQ: only facts we hold (dimensions, same-cap neighbours, what we stock)
  $faq = [];
  if ($s['cap']) $faq[] = [__('What size is a :name series u-joint?', ['name' => $name]), __('A :name series universal joint has :cap diameter bearing caps', ['name' => $name, 'cap' => $s['cap']]) . ($s['width'] ? __(' and measures :w from snap ring to snap ring (outside of cap to outside of cap)', ['w' => $s['width']]) : '') . __('. These are nominal figures; measure the caps on your old joint with a caliper to confirm the series.')];
  if ($sameCap) $faq[] = [__('Is the :name series the same as the :others?', ['name' => $name, 'others' => implode(__(' or '), array_column($sameCap, 'name'))]), __('No. The :name and :others series share the same :cap cap diameter but differ in width, so their yokes are not interchangeable. Use a conversion u-joint when you need to join a :name yoke to a yoke from a different series.', ['name' => $name, 'others' => implode(', ', array_column($sameCap, 'name')), 'cap' => $s['cap']])];
  if ($cats) $faq[] = [__('Which :name series parts does UDS stock?', ['name' => $name]), __(':n :name series parts across :c categories: ', ['n' => number_format($total), 'name' => $name, 'c' => count($cats)]) . implode(', ', array_map(fn ($c) => $c['total'] . ' ' . mb_strtolower(\App\Support\PartGlossary::t($c['cat']['name'])), array_slice($cats, 0, 6))) . (count($cats) > 6 ? __(' and more') : '') . __('. Everything listed in stock ships the same day from Florida when ordered before 2pm EST.')];
  $faq[] = [__('How do I match :name series parts on a driveshaft?', ['name' => $name]), __('Every u-joint on the shaft must match the yokes it sits in: a :name series joint needs :name series weld yokes, slip yokes, end yokes or flange yokes', ['name' => $name]) . ($s['cap'] ? __(' (:cap cap bores)', ['cap' => $s['cap']]) : '') . __('. Mixed-series shafts use a conversion joint with a different cap size on each axis.')];

  $listItems = []; $pos = 0;
  foreach ($cats as $c) foreach (array_slice($c['items'], 0, 6) as $it) {
    $listItems[] = ['@type' => 'ListItem', 'position' => ++$pos, 'url' => $base . '/parts/' . rawurlencode($it['sku']), 'name' => $it['sku'] . ' ' . \Illuminate\Support\Str::limit($svc->cleanName($it), 80, '')];
  }
  $graph = [
    ['@type' => 'CollectionPage', '@id' => $base . '/series/' . $s['slug'], 'url' => $base . '/series/' . $s['slug'], 'name' => $name . ' Series Driveshaft Parts', 'description' => $desc,
      'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => $total, 'itemListElement' => $listItems]],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
      ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/'],
      ['@type' => 'ListItem', 'position' => 2, 'name' => 'Catalog', 'item' => $base . '/shop'],
      ['@type' => 'ListItem', 'position' => 3, 'name' => 'Series', 'item' => $base . '/series'],
      ['@type' => 'ListItem', 'position' => 4, 'name' => $name . ' Series', 'item' => $base . '/series/' . $s['slug']],
    ]],
    ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq)],
  ];
@endphp
@section('title', $title)
@section('desc', $desc)
@section('canonical', '/series/' . $s['slug'])
@section('bodyclass','pg')
@if($hero)@section('ogimage', str_starts_with($hero['image'], 'http') ? $hero['image'] : $base . $hero['image'])@endif

@push('schema')
<script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <a href="/shop">{{ __('Catalog') }}</a><x-icon name="caret-right"/>
    <a href="/series">{{ __('Series') }}</a><x-icon name="caret-right"/>
    <span aria-current="page">{{ $name }} {{ __('Series') }}</span>
  </nav>
  <div class="pagehead__row">
    <h1>{{ $name }} {{ __('Series Driveshaft Parts') }}</h1>
    <div class="btn-row"><a class="btn btn-ghost btn-sm" href="/series">{{ __('All series') }}</a></div>
  </div>
  <p class="pagehead__sub">
    @if($s['desc']){{ __($s['desc']) }} @endif
    @if($s['cap']){{ __('Nominal :name dimensions: :cap bearing cap diameter', ['name' => $name, 'cap' => $s['cap']]) }}{{ $s['width'] ? __(', :w snap-ring-to-snap-ring width', ['w' => $s['width']]) : '' }}{{ __('. Measure the caps on your old joint to confirm the series before ordering.') }}@endif
  </p>
  <ul class="srs-facts">
    @if($s['cap'])<li><b>{{ $s['cap'] }}</b> {{ __('Cap diameter (nominal)') }}</li>@endif
    @if($s['width'])<li><b>{{ $s['width'] }}</b> {{ __('Width, ring to ring') }}</li>@endif
    <li><b>{{ number_format($total) }}</b> {{ __('Parts in stock') }}</li>
    <li><b>{{ count($cats) }}</b> {{ __('Categories') }}</li>
  </ul>
  @if($cats)
    <nav class="srs-toc" aria-label="{{ __('Part types in this series') }}">
      @foreach($cats as $c)<a href="#srs-{{ $c['cat']['slug'] }}">{{ $c['cat']['name'] }}<small>{{ $c['total'] }}</small></a>@endforeach
    </nav>
  @endif
</div>

@forelse($cats as $c)
  <section class="catsec srs-cat" id="srs-{{ $c['cat']['slug'] }}" aria-labelledby="srs-h-{{ $c['cat']['slug'] }}"><div class="wrap">
    <div class="catsec__head">
      <h2 id="srs-h-{{ $c['cat']['slug'] }}"><a href="{{ $c['cat']['path'] }}?series={{ $s['slug'] }}">{{ $name }} {{ __('Series') }} {{ $c['cat']['name'] }}</a> <span>{{ $c['total'] }}</span></h2>
      <a class="textlink" href="{{ $c['cat']['path'] }}?series={{ $s['slug'] }}">{{ __('All') }} {{ $c['total'] }} {{ __('in the table') }} <x-icon name="arrow-right"/></a>
    </div>
    <ul class="pcards">
      @foreach(array_slice($c['items'], 0, 6) as $it)
        <li class="pcard">
          <a class="pcard__img" href="/parts/{{ rawurlencode($it['sku']) }}">@if($it['image'])<img src="{{ $it['image'] }}" alt="{{ $it['sku'] }} {{ \Illuminate\Support\Str::limit($svc->cleanName($it), 60, '') }}" loading="lazy" width="240" height="240">@else<x-icon name="package"/>@endif</a>
          <a class="pcard__sku" href="/parts/{{ rawurlencode($it['sku']) }}">{{ $it['sku'] }}</a>
          <span class="pcard__name">{{ \Illuminate\Support\Str::limit($svc->cleanName($it), 64) }}</span>
          <div class="pcard__buy">
            <span class="pcard__price">@if($it['price'] > 0)${{ number_format($it['price'], 2) }}@else{{ __('Call for price') }}@endif</span>
            @if($it['price'] > 0 && !empty($it['id']) && $it['stock_status'] !== 'outofstock')<button type="button" class="btn btn-primary btn-sm p-plus" data-add="{{ $it['id'] }}" aria-label="{{ __('Add :sku to cart', ['sku' => $it['sku']]) }}"><x-icon name="cart"/> {{ __('Add') }}</button>@endif
          </div>
        </li>
      @endforeach
    </ul>
  </div></section>
@empty
  <section class="catsec"><div class="wrap"><div class="aempty"><b>{{ __('No parts listed for the :name series yet', ['name' => $name]) }}</b>{{ __('Call (813) 524-6491 and we will check the shelf.') }}</div></div></section>
@endforelse

<section class="catsec srs-faq" aria-labelledby="srs-faq-h"><div class="wrap">
  <div class="catsec__head"><h2 id="srs-faq-h">{{ $name }} {{ __('series questions') }}</h2></div>
  <div class="pg-faq">
    @foreach($faq as $i => $f)
      <details><summary>{{ $f[0] }}</summary><p>{{ $f[1] }}</p></details>
    @endforeach
  </div>
</div></section>

<section class="catsec catsec--end" aria-labelledby="srs-other-h"><div class="wrap">
  <div class="catsec__head"><h2 id="srs-other-h">{{ __('Other u-joint series') }}</h2></div>
  <ul class="srs-others">
    @foreach($others as $o)<li><a href="/series/{{ $o['slug'] }}">{{ \App\Support\PartGlossary::t($o['name']) }}{{ $o['cap'] ? ' · ' . $o['cap'] : '' }}</a></li>@endforeach
  </ul>
  <p class="xref-note">{{ __('Series designations (1310, 1350, 3R, SPL…) are Spicer') }}<sup>{{ __('®') }}</sup> {{ __('/ Dana') }}<sup>{{ __('®') }}</sup> {{ __('nomenclature used across the industry and are shown for identification and cross-reference only. Dimensions are nominal; verify fitment before ordering.') }} <a href="/copyright">{{ __('Copyright & trademark notice') }}</a></p>
</div></section>

<div class="stock-pop" id="stock-pop"></div>
@endsection
@push('scripts')
@include('shop.partials.js-i18n')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
@endpush
