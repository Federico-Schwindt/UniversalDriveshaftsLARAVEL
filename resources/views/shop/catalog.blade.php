@extends('layouts.app')
@section('title','Driveshaft Parts Catalog: U-Joints, Yokes, Tubing | UDS')
@section('canonical','/shop')
@section('desc','The full Universal Driveshafts catalog: complete driveshafts, u-joints, yokes, center support bearings, driveshaft tubing and small parts, by series and size.')
@push('schema')
@include('partials.breadcrumb-schema', ['crumbs' => [
  ['Home', rtrim(config('app.url'),'/').'/'], ['Catalog', url()->current()],
]])
@endpush

@section('content')
@include('shop.partials.style')
@php
  $cs = app(\App\Services\CatalogService::class);
  $totalParts = array_sum(array_column($groups, 'count'));
@endphp
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Catalog') }}</span></nav>
  <div class="pagehead__row">
    <h1>{{ __('Driveshaft parts catalog') }}</h1>
    <a class="btn btn-ghost btn-sm" href="/quick-order"><x-icon name="list-numbers"/> {{ __('Quick order by part number') }}</a>
  </div>
  <p class="pagehead__sub">{{ __(':n parts in :c categories, in stock in Florida.', ['n' => number_format($totalParts), 'c' => $udsCatalog['cats'] ?? 44]) }}</p>
</div>

@foreach($groups as $g)
  @php $grp = $cs->group($g['slug']); @endphp
  @if($grp && count($grp['subcategories']))
  <section class="catsec"><div class="wrap">
    <div class="catsec__head">
      <h2><a href="/shop/{{ $g['slug'] }}">{{ __($g['label']) }}</a> <span>{{ number_format($g['count']) }}</span></h2>
      @if($g['slug'] === 'complete-driveshafts')<a class="textlink" href="/catalog/driveshafts"><x-icon name="car"/> {{ __('Find by vehicle') }}</a>@endif
    </div>
    @include('shop.partials.cat-tiles', ['group' => $g['slug'], 'subs' => $grp['subcategories']])
  </div></section>
  @endif
@endforeach

<section class="catsec"><div class="wrap">
  <div class="catsec__head"><h2>{{ __('Shop by series') }}</h2></div>
  <ul class="n-series">
    @foreach(array_slice($cs->seriesList(), 0, 16, true) as $slug => $sv)
      <li><a href="/series/{{ $slug }}" title="{{ __(':n :series series parts', ['n' => $sv['count'], 'series' => $sv['name']]) }}">{{ \App\Support\PartGlossary::t($sv['name']) }}</a></li>
    @endforeach
    <li><a href="/series">{{ __('All series') }} <x-icon name="arrow-right"/></a></li>
  </ul>
</div></section>
@endsection
