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
  if ($cats) $faq[] = [__('Which :name series parts does UDS stock?', ['name' => $name]), __(':n :name series parts across :c categories: ', ['n' => number_format($total), 'name' => $name, 'c' => count($cats)]) . implode(', ', array_map(fn ($c) => $c['total'] . ' ' . mb_strtolower(\App\Support\PartGlossary::t($c['cat']['name'])), array_slice($cats, 0, 6))) . (count($cats) > 6 ? __(' and more') : '') . __('. Everything listed in stock ships the same day from Florida when ordered before 2pm ET.')];
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
@if($hero)@section('ogimage', str_starts_with($hero['image'], 'http') ? $hero['image'] : $base . $hero['image'])@endif

@push('head')
<style>
.srs-hero{background:var(--paper-2);border-bottom:1px solid var(--line);padding-bottom:clamp(1.75rem,1rem + 2vw,2.75rem)}
.srs-hero h1{font-size:clamp(1.9rem,1.4rem + 2.2vw,2.9rem);margin:.4rem 0 .75rem;letter-spacing:-.02em}
.srs-hero .lead{max-width:70ch}
.srs-facts{display:flex;flex-wrap:wrap;gap:.75rem;margin:1.25rem 0 0;padding:0;list-style:none}
.srs-facts li{background:#fff;border:1px solid var(--line);border-radius:var(--r-sm);padding:.7rem 1rem;min-width:140px}
.srs-facts b{display:block;font-family:var(--font-m);font-size:1.15rem;font-weight:600;color:var(--ink)}
.srs-facts span{font-size:.74rem;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-faint);font-family:var(--font-m)}
.srs-toc{display:flex;flex-wrap:wrap;gap:.45rem;margin-top:1.25rem}
.srs-toc a{font-family:var(--font-m);font-weight:600;font-size:.8rem;padding:.35rem .7rem;border-radius:var(--r-pill);background:#fff;border:1px solid var(--line);color:var(--blue)}
.srs-toc a small{font-weight:500;color:var(--ink-faint);margin-left:.3rem}
.srs-cat{padding-block:clamp(1.75rem,1rem + 2vw,2.75rem);border-bottom:1px solid var(--line)}
.srs-cat .s-head{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;flex-wrap:wrap;margin-bottom:1.1rem}
.srs-cat .s-head h2{font-size:1.35rem;margin:0}
.srs-cat .s-head p{margin:.25rem 0 0;color:var(--ink-2);font-size:.92rem}
.srs-cat .pcard__name{font-size:.84rem;min-height:0}
.pgrid{grid-template-columns:repeat(4,minmax(0,1fr))}.pgrid .pcard{min-width:0}.pcard__media{overflow:hidden;min-height:0}.pcard__media img{width:auto;height:auto;max-width:100%;max-height:100%}
.pcard__media:not(:has(img)){background:var(--paper-2)}.pcard__media > .i{font-size:2.4rem;opacity:.35}
.srs-cat .pgrid{grid-template-columns:repeat(6,minmax(0,1fr))}
@media (max-width:1100px){.srs-cat .pgrid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media (max-width:760px){.srs-cat .pgrid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:520px){.srs-cat .pgrid{grid-template-columns:repeat(2,minmax(0,1fr))}}
.srs-more{margin-top:1rem}
.srs-others{display:flex;flex-wrap:wrap;gap:.5rem;list-style:none;padding:0;margin:1rem 0 0}
.srs-others a{display:inline-block;font-family:var(--font-m);font-weight:600;font-size:.84rem;padding:.4rem .8rem;border-radius:var(--r-pill);background:#fff;border:1px solid var(--line);color:var(--ink)}
.srs-others a:hover{border-color:var(--blue);color:var(--blue)}
.srs-faq .faq{max-width:80ch}
</style>
@endpush

@push('schema')
<script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<header class="srs-hero">
  <div class="wrap">
    <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
      <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
      <a href="/shop">{{ __('Catalog') }}</a><x-icon name="caret-right"/>
      <a href="/series">{{ __('Series') }}</a><x-icon name="caret-right"/>
      <span aria-current="page">{{ $name }} {{ __('Series') }}</span>
    </nav>
    <span class="kicker">{{ __('U-joint series') }}</span>
    <h1>{{ $name }} {{ __('Series Driveshaft Parts') }}</h1>
    <p class="lead">
      @if($s['desc']){{ __($s['desc']) }} @endif
      @if($s['cap']){{ __('Nominal :name dimensions: :cap bearing cap diameter', ['name' => $name, 'cap' => $s['cap']]) }}{{ $s['width'] ? __(', :w snap-ring-to-snap-ring width', ['w' => $s['width']]) : '' }}{{ __('. Measure the caps on your old joint to confirm the series before ordering.') }}@endif
      {{ __('UDS stocks :n :name series parts across :c categories; everything in stock ships the same day from Florida.', ['n' => number_format($total), 'name' => $name, 'c' => count($cats)]) }}
    </p>
    <ul class="srs-facts">
      @if($s['cap'])<li><b>{{ $s['cap'] }}</b><span>{{ __('Cap diameter (nominal)') }}</span></li>@endif
      @if($s['width'])<li><b>{{ $s['width'] }}</b><span>{{ __('Width, ring to ring') }}</span></li>@endif
      <li><b>{{ number_format($total) }}</b><span>{{ __('Parts in stock') }}</span></li>
      <li><b>{{ count($cats) }}</b><span>{{ __('Categories') }}</span></li>
    </ul>
    @if($cats)
      <nav class="srs-toc" aria-label="{{ __('Part types in this series') }}">
        @foreach($cats as $c)<a href="#srs-{{ $c['cat']['slug'] }}">{{ $c['cat']['name'] }}<small>{{ $c['total'] }}</small></a>@endforeach
      </nav>
    @endif
  </div>
</header>

<div class="wrap">
  @forelse($cats as $c)
    <section class="srs-cat" id="srs-{{ $c['cat']['slug'] }}" aria-labelledby="srs-h-{{ $c['cat']['slug'] }}">
      <div class="s-head">
        <div>
          <h2 id="srs-h-{{ $c['cat']['slug'] }}">{{ $name }} {{ __('Series') }} {{ $c['cat']['name'] }}</h2>
          <p>{{ __(':n :type in the :name series.', ['n' => $c['total'], 'type' => mb_strtolower(\App\Support\PartGlossary::t($c['cat']['name'])), 'name' => $name]) }}@if(($t = config("seo-copy.categories.{$c['cat']['slug']}.intro"))) {{ preg_split('/(?<=[.!?])\s+/', __($t))[0] }}@endif</p>
        </div>
        <a class="s-link" href="{{ $c['cat']['path'] }}?series={{ $s['slug'] }}">{{ __('All') }} {{ $c['total'] }} {{ __('in the table') }} <x-icon name="arrow-right"/></a>
      </div>
      <div class="pgrid">
        @foreach($c['items'] as $it)
          <a class="pcard" href="/parts/{{ rawurlencode($it['sku']) }}">
            <span class="pcard__media">@if($it['image'])<img src="{{ $it['image'] }}" alt="{{ $it['sku'] }} {{ \Illuminate\Support\Str::limit($svc->cleanName($it), 60, '') }}" loading="lazy" width="160" height="160">@else<x-icon name="package"/>@endif</span>
            <span class="pcard__body">
              <span class="pcard__sku">{{ $it['sku'] }}</span>
              <span class="pcard__name">{{ \Illuminate\Support\Str::limit($svc->cleanName($it), 64) }}</span>
              <span class="pcard__foot"><b>${{ number_format($it['price'], 2) }}</b><small class="{{ $it['stock_status'] === 'instock' ? 'pcard__ok' : '' }}">{{ $it['stock_status'] === 'instock' ? __('In stock') : ($it['stock_status'] === 'onbackorder' ? __('Backorder') : __('Call')) }}</small></span>
            </span>
          </a>
        @endforeach
      </div>
      @if($c['total'] > count($c['items']))
        <p class="srs-more"><a class="btn btn-ghost" href="{{ $c['cat']['path'] }}?series={{ $s['slug'] }}">{{ __('See all') }} {{ $c['total'] }} {{ $name }} {{ __('series') }} {{ strtolower($c['cat']['name']) }} <x-icon name="arrow-right"/></a></p>
      @endif
    </section>
  @empty
    <div class="aempty" style="margin-top:2rem"><b>{{ __('No parts listed for the :name series yet', ['name' => $name]) }}</b>{{ __('Call (813) 524-6491 and we will check the shelf.') }}</div>
  @endforelse

  <section class="srs-cat srs-faq" aria-labelledby="srs-faq-h">
    <h2 id="srs-faq-h" style="font-size:1.35rem">{{ $name }} {{ __('series questions') }}</h2>
    <div class="faq">
      @foreach($faq as $i => $f)
        <details @if($i === 0) open @endif><summary>{{ $f[0] }}</summary><p>{{ $f[1] }}</p></details>
      @endforeach
    </div>
  </section>

  <section class="srs-cat" style="border-bottom:0" aria-labelledby="srs-other-h">
    <h2 id="srs-other-h" style="font-size:1.35rem">{{ __('Other u-joint series') }}</h2>
    <ul class="srs-others">
      @foreach($others as $o)<li><a href="/series/{{ $o['slug'] }}">{{ \App\Support\PartGlossary::t($o['name']) }}{{ $o['cap'] ? ' · ' . $o['cap'] : '' }}</a></li>@endforeach
    </ul>
    <p class="xref-note" style="margin-top:1.5rem">{{ __('Series designations (1310, 1350, 3R, SPL…) are Spicer') }}<sup>{{ __('®') }}</sup> {{ __('/ Dana') }}<sup>{{ __('®') }}</sup> {{ __('nomenclature used across the industry and are shown for identification and cross-reference only. Dimensions are nominal; verify fitment before ordering.') }} <a href="/copyright">{{ __('Copyright & trademark notice') }}</a></p>
  </section>
</div>
@endsection
