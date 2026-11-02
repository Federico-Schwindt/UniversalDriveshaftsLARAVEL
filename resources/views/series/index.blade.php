@extends('layouts.app')
@php
  $base = rtrim(config('app.url'), '/');
  $withDims = array_values(array_filter($series, fn ($s) => $s['cap']));
  $graph = [
    ['@type' => 'CollectionPage', 'url' => $base . '/series', 'name' => 'Driveshaft Parts by U-Joint Series',
      'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($series), 'itemListElement' => collect($series)->values()->map(fn ($s, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $s['name'] . ' Series Driveshaft Parts', 'url' => $base . '/series/' . $s['slug']])->all()]],
    ['@type' => 'BreadcrumbList', 'itemListElement' => [
      ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/'],
      ['@type' => 'ListItem', 'position' => 2, 'name' => 'Catalog', 'item' => $base . '/shop'],
      ['@type' => 'ListItem', 'position' => 3, 'name' => 'Series', 'item' => $base . '/series'],
    ]],
  ];
@endphp
@section('title', 'U-Joint Series Chart: 1310, 1330, 1350, 1410, 1480 | UDS')
@section('desc', 'U-joint series chart: nominal cap diameter and width for 1310, 1330, 1350, 1410, 1480, 1550, 3R and 7260, with ' . number_format($total) . ' matching yokes and joints in stock at UDS.')
@section('canonical', '/series')

@push('head')
<style>
.srs-hero{background:var(--paper-2);border-bottom:1px solid var(--line);padding-bottom:clamp(1.75rem,1rem + 2vw,2.75rem)}
.srs-hero h1{font-size:clamp(1.9rem,1.4rem + 2.2vw,2.9rem);margin:.4rem 0 .75rem;letter-spacing:-.02em}
.srs-hero .lead{max-width:70ch}
.srs-tbl{width:100%;border-collapse:collapse;font-size:.92rem;background:#fff;border:1px solid var(--line);border-radius:var(--r);overflow:hidden}
.srs-tbl th{background:var(--steel);color:#c3d0e6;font-family:var(--font-m);font-weight:500;font-size:.7rem;letter-spacing:.06em;text-transform:uppercase;text-align:left;padding:.8rem .9rem}
.srs-tbl td{padding:.7rem .9rem;border-top:1px solid var(--line-2);vertical-align:top;font-variant-numeric:tabular-nums}
.srs-tbl td.n{font-family:var(--font-m);font-weight:600;white-space:nowrap}
.srs-tbl td.n a{color:var(--blue)}
.srs-tbl td.d{color:var(--ink-2);line-height:1.5;max-width:60ch}
.srs-tbl tr:hover td{background:var(--blue-050)}
.srs-others{display:flex;flex-wrap:wrap;gap:.5rem;list-style:none;padding:0;margin:1rem 0 0}
.srs-others a{display:inline-block;font-family:var(--font-m);font-weight:600;font-size:.84rem;padding:.4rem .8rem;border-radius:var(--r-pill);background:#fff;border:1px solid var(--line);color:var(--ink)}
.srs-others a:hover{border-color:var(--blue);color:var(--blue)}
@media (max-width:700px){.srs-tbl td.d{display:none}.srs-tbl th:nth-child(4){display:none}}
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
      <span aria-current="page">{{ __('Series') }}</span>
    </nav>
    <span class="kicker">{{ __('Shop by series') }}</span>
    <h1>{{ __('Driveshaft parts by u-joint series') }}</h1>
    <p class="lead">{{ __('A driveshaft\'s series is set by its universal joint: the bearing cap diameter and the width from snap ring to snap ring. Every yoke, slip assembly and joint on the shaft has to match it. Pick your series below to see the') }} {{ number_format($total) }} {{ __('matching parts we stock, or measure the old joint with a caliper and compare it to the nominal dimensions.') }}</p>
  </div>
</header>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>{{ __('Series chart') }}</h2><p>{{ __('Nominal Spicer-style dimensions. Cap = bearing cup outside diameter; width = outside of cap to outside of cap.') }}</p></div>
  <div class="ptbl-wrap">
  <table class="srs-tbl">
    <thead><tr><th>{{ __('Series') }}</th><th>{{ __('Cap dia') }}</th><th>{{ __('Width') }}</th><th>{{ __('Typical use') }}</th><th>{{ __('Parts') }}</th></tr></thead>
    <tbody>
      @foreach($series as $s)
        <tr>
          <td class="n"><a href="/series/{{ $s['slug'] }}">{{ \App\Support\PartGlossary::t($s['name']) }}</a></td>
          <td>{{ $s['cap'] ?: '—' }}</td>
          <td>{{ $s['width'] ?: '—' }}</td>
          <td class="d">{{ $s['desc'] ? preg_split('/(?<=[.!?])\s+/', __($s['desc']))[0] : '' }}</td>
          <td class="n">{{ number_format($s['count']) }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  </div>
  <p class="xref-note" style="margin-top:1.5rem">{{ __('Series designations are Spicer') }}<sup>{{ __('®') }}</sup> {{ __('/ Dana') }}<sup>{{ __('®') }}</sup> {{ __('nomenclature used across the industry and are shown for identification and cross-reference only. Dimensions are nominal; verify fitment before ordering.') }} <a href="/copyright">{{ __('Copyright & trademark notice') }}</a></p>
</div></section>
@endsection
