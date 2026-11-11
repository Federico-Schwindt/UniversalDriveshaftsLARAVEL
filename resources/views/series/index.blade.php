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
@section('bodyclass','pg')

@push('schema')
<script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <a href="/shop">{{ __('Catalog') }}</a><x-icon name="caret-right"/>
    <span aria-current="page">{{ __('Series') }}</span>
  </nav>
  <div class="pagehead__row">
    <h1>{{ __('Driveshaft parts by u-joint series') }}</h1>
    <div class="btn-row"><a class="btn btn-ghost btn-sm" href="/shop/driveshaft-components/u-joints"><x-icon name="package"/> {{ __('Shop u-joints') }}</a></div>
  </div>
  <p class="pagehead__sub">{{ __('A driveshaft\'s series is set by its universal joint: the bearing cap diameter and the width from snap ring to snap ring. Every yoke, slip assembly and joint on the shaft has to match it. Pick your series below to see the') }} {{ number_format($total) }} {{ __('matching parts we stock, or measure the old joint with a caliper and compare it to the nominal dimensions.') }}</p>
</div>

<section class="catsec catsec--end"><div class="wrap">
  <div class="catsec__head"><h2>{{ __('Series chart') }}</h2><p>{{ __('Nominal Spicer-style dimensions. Cap = bearing cup outside diameter; width = outside of cap to outside of cap.') }}</p></div>
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
  <p class="xref-note">{{ __('Series designations are Spicer') }}<sup>{{ __('®') }}</sup> {{ __('/ Dana') }}<sup>{{ __('®') }}</sup> {{ __('nomenclature used across the industry and are shown for identification and cross-reference only. Dimensions are nominal; verify fitment before ordering.') }} <a href="/copyright">{{ __('Copyright & trademark notice') }}</a></p>
</div></section>
@endsection
