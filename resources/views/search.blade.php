@extends('layouts.app')
@section('robots','noindex,follow')
@section('canonical','/search')
@section('title', ($q !== '' ? __('Search: :q', ['q' => $q]) : __('Search the catalog')) . ' | Universal Driveshafts')
@section('desc', __('Search Universal Driveshafts by part number, name or series. Results grouped by category with specs, prices and stock.'))
@push('scripts')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
@endpush
@section('content')
@include('shop.partials.style')
@include('shop.partials.js-i18n')
@php
  $rowCap = 6;   // per-category rows on this page: 24 per group made a 92,000px phone page; the rest is one tap away
  $nCats = count($sections) + ($driveshafts ? 1 : 0);
  $money = fn ($n) => '$' . number_format((float) $n, 2);
@endphp
<header class="ucathead srch-head">
  <div class="wrap">
    <nav class="ucrumb" aria-label="{!! __('Breadcrumb') !!}"><a href="/">{!! __('Home') !!}</a><x-icon name="caret-right"/><a href="/shop">{!! __('Catalog') !!}</a><x-icon name="caret-right"/><span aria-current="page">{!! __('Search') !!}</span></nav>
    <h1>@if($q === ''){{ __('Search the catalog') }} @else{!! trans_choice(':count result for :q|:count results for :q', $total, ['count' => number_format($total), 'q' => '<span class="srch-q">'.e($q).'</span>']) !!}@endif</h1>
    <form class="srch-form" action="/search" method="get" role="search">
      <x-icon name="search"/>
      <input type="search" name="q" value="{{ $q }}" placeholder="{!! __('Part number, name or series — e.g. 5-1310X, flange yoke, U2594-173') !!}" aria-label="{!! __('Search parts') !!}" autocomplete="off" autofocus>
      <button type="submit" class="btn btn-primary">{!! __('Search') !!}</button>
    </form>
    @if($nCats > 1)
      <div class="srch-jump" aria-label="{!! __('Jump to category') !!}">
        @foreach($sections as $s)<a href="#cat-{{ $s['cat']['slug'] }}">{{ $s['cat']['name'] }} <b>{{ number_format($s['data']['total']) }}</b></a>@endforeach
        @if($driveshafts)<a href="#cat-driveshafts">{!! __('Complete driveshafts') !!} <b>{{ count($driveshafts) }}</b></a>@endif
      </div>
    @endif
  </div>
</header>

<section class="section srch-body"><div class="wrap">
  @if($q !== '' && $total === 0)
    <div class="cartempty">
      <x-icon name="search"/>
      <h2>{{ __('Nothing matched “:q”', ['q' => $q]) }}</h2>
      <p>{!! __('Check the number against the one stamped on the part, try the series only (1310, 1350) or the bare number without a prefix. Complete driveshafts are easier to find by vehicle.') !!}</p>
      <div class="cartempty__cta"><a class="btn btn-primary" href="/catalog/driveshafts"><x-icon name="crosshair"/> {!! __('Find by vehicle') !!}</a><a class="btn btn-ghost" href="/shop">{!! __('Browse the catalog') !!}</a><a class="btn btn-ghost" href="/quick-order">{!! __('Quick order') !!}</a></div>
    </div>
  @elseif($q === '')
    <div class="srch-hints">
      <div><h2>{!! __('Search by') !!}</h2><ul><li><b>{!! __('Part number') !!}</b> — 5-1310X, 2-4-6491X, U2594-173</li><li><b>{!! __('Series') !!}</b> — 1310, 1350, 1410, 1480</li><li><b>{!! __('Name') !!}</b> {!! __('— flange yoke, slip yoke, center support bearing') !!}</li></ul></div>
      <div><h2>{!! __('Or browse') !!}</h2><div class="srch-quick"><a class="btn btn-ghost" href="/shop">{!! __('Catalog') !!}</a><a class="btn btn-ghost" href="/catalog/driveshafts">{!! __('Find by vehicle') !!}</a><a class="btn btn-ghost" href="/online-builder">{!! __('Online builder') !!}</a><a class="btn btn-ghost" href="/driveshaft-designer">{!! __('Driveshaft designer') !!}</a></div></div>
    </div>
  @endif

  @if($nCats)
  {{-- every table whole on screen and all the same width: the set takes its widest table's ("TABLE FIT" in uds-2026.css) --}}
  <div class="shop-bleed"><div class="ptfit-set">
  @foreach($sections as $s)
    <section class="srch-sec{{ in_array($s['cat']['slug'], ['xref', 'exact'], true) ? ' xref-sec' : '' }}" id="cat-{{ $s['cat']['slug'] }}">
      <div class="srch-sec__head">
        <h2>{{ $s['cat']['name'] }} <span>{{ trans_choice(':count part|:count parts', $s['data']['total'], ['count' => number_format($s['data']['total'])]) }}</span></h2>
        @if(!empty($s['cat']['group']))<a class="textlink" href="/shop/{{ $s['cat']['group'] }}/{{ $s['cat']['slug'] }}?q={{ rawurlencode($q) }}">{!! __('Open category') !!} <x-icon name="arrow-right"/></a>@endif
      </div>
      @if(!empty($s['xref']))
        <p class="xref-cap"><x-icon name="returns"/><span class="xref-cap__t">{{ trans_choice('These UDS parts replace the competitor / OEM number|These UDS parts replace the competitor / OEM numbers', count($s['xref'])) }} @foreach($s['xref'] as $n)<b>{{ $n }}</b>@if(!$loop->last), @endif @endforeach — {{ __('the matched number is shown in the') }} <em>{!! __('Replaces') !!}</em> {!! __('column.') !!}</span></p>
      @endif
      @php $shown = !empty($s['cat']['group']) ? array_slice($s['data']['items'], 0, $rowCap) : $s['data']['items']; @endphp
      <div class="ptfit">@include('shop.partials.search-table', ['data' => ['items' => $shown] + $s['data'], 'schema' => $s['schema']])</div>@include('shop.partials.table-fit')
      @if($s['data']['total'] > count($shown))
        <p class="srch-more">{{ __('Showing :n of :total.', ['n' => count($shown), 'total' => number_format($s['data']['total'])]) }}
          @if(!empty($s['cat']['group']))<a class="btn btn-line btn-sm" href="/shop/{{ $s['cat']['group'] }}/{{ $s['cat']['slug'] }}?q={{ rawurlencode($q) }}">{{ __('Show all :n in :cat', ['n' => number_format($s['data']['total']), 'cat' => \App\Support\PartGlossary::t($s['cat']['name'])]) }}</a>
          @endif
        </p>
      @endif
    </section>
  @endforeach

  @if($driveshafts)
    <section class="srch-sec" id="cat-driveshafts">
      <div class="srch-sec__head">
        <h2>{!! __('Complete driveshafts') !!} <span>{{ trans_choice(':count part|:count parts', count($driveshafts)) }}</span></h2>
        <a class="textlink" href="/shop/complete-driveshafts/by-vehicle?q={{ rawurlencode($q) }}">{!! __('Open category') !!} <x-icon name="arrow-right"/></a>
      </div>
      <div class="ptfit"><div class="ptbl-wrap"><table class="ptbl">
        <thead><tr><th>{!! __('Add') !!}</th><th>{!! __('Pic') !!}</th><th>{!! __('Part #') !!}</th><th>{!! __('Price') !!}</th><th class="l">{!! __('Description') !!}</th><th class="l">{!! __('Fits') !!}</th><th>{!! __('Weight') !!}</th></tr></thead>
        <tbody>
        @foreach(array_slice($driveshafts, 0, $rowCap) as $d)
          <tr>
            <td data-label="{{ __('Add') }}" data-col="add"><span class="p-add">@if($d['can_buy'])<button class="p-plus" data-ds-add="{{ $d['part_number'] }}" aria-label="{{ __('Add :sku to cart', ['sku' => $d['sku']]) }}" title="{{ __('Add to cart') }}"><x-icon name="cart"/><span class="p-plus__t">{{ __('Add') }}</span></button>@else<button type="button" class="p-call" data-quote="{{ $d['sku'] }}" data-pn="{{ $d['part_number'] }}">{{ __('Get a quote') }}</button>@endif</span></td>
            <td data-label="{{ __('Pic') }}" data-col="pic">@if($d['image'])<img class="p-pic" width="44" height="44" src="{{ $d['image'] }}" data-zoom="{{ $d['image'] }}" alt="{{ $d['sku'] }}" loading="lazy">@else<span class="p-pic-ph">{!! '<svg viewBox="0 0 64 40" width="26" height="26" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linejoin="round"><circle cx="12" cy="20" r="7"/><circle cx="52" cy="20" r="7"/><rect x="19" y="16.5" width="26" height="7" rx="2"/></svg>' !!}</span>@endif</td>
            <td data-label="{{ __('Part #') }}" data-col="part"><a class="p-pn" href="{{ $d['url'] }}">{{ $d['sku'] }}<x-icon name="arrow-up-right"/></a></td>
            <td data-label="{{ __('Price') }}" data-col="price"><div class="p-price">@if($d['can_buy'])<span class="now">{{ $money($d['price']) }}</span>@if($d['core_price'])<span class="save">{{ __('+ :amount core', ['amount' => $money($d['core_price'])]) }}</span>@endif @else<span class="p-muted">{!! __('Call for price') !!}</span>@endif</div></td>
            <td class="l" data-label="{{ __('Description') }}">{{ $d['description'] ?: __('Complete driveshaft') }}@if($d['condition'] && $d['condition'] !== 'new') <span class="p-muted">({{ $d['condition'] }})</span>@endif</td>
            <td class="l" data-label="{{ __('Fits') }}">@if(!empty($d['fits']))<span class="p-notes">{{ implode(' · ', array_slice($d['fits'], 0, 3)) }}@if(count($d['fits']) > 3) <a href="{{ $d['url'] }}">{{ __('+:n more', ['n' => count($d['fits']) - 3]) }}</a>@endif</span>@else<span class="p-muted">-</span>@endif</td>
            <td data-label="{{ __('Weight') }}">{{ $d['weight'] ? number_format($d['weight'], 1) . ' lb' : '-' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table></div></div>@include('shop.partials.table-fit')
      @if(count($driveshafts) > $rowCap)
        <p class="srch-more">{{ __('Showing :n of :total.', ['n' => $rowCap, 'total' => count($driveshafts)]) }} <a class="btn btn-line btn-sm" href="/shop/complete-driveshafts/by-vehicle?q={{ rawurlencode($q) }}">{{ __('Show all :n in :cat', ['n' => count($driveshafts), 'cat' => __('Complete driveshafts')]) }}</a></p>
      @endif
    </section>
  @endif
  </div></div>
  @endif
</div></section>

</div>
<div class="stock-pop" id="stock-pop"></div>
<div class="img-pop" id="img-pop"><img alt=""></div>
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.ds-add')
@endsection
