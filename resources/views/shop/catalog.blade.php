@extends('layouts.app')
@section('title','Driveshaft Parts Catalog: U-Joints, Yokes, Tubing | UDS')
@section('canonical','/shop')
@section('desc','The full Universal Driveshafts catalog: complete driveshafts, u-joints, yokes, center support bearings, custom cut tubing and small parts, by series and size.')
@push('schema')
@include('partials.breadcrumb-schema', ['crumbs' => [
  ['Home', rtrim(config('app.url'),'/').'/'], ['Catalog', url()->current()],
]])
@endpush

@section('content')
@include('shop.partials.style')
@php
  $cats = config('uds.categories'); $totalParts = array_sum(array_map(fn ($g) => $g['count'], $groups));
  // the cards' counts come from the shelf (term counts), not the numbers typed into config
  $liveCounts = \Illuminate\Support\Facades\Cache::remember('shop_cat_counts', 600, fn () => collect($cats)->mapWithKeys(fn ($c) => [$c[1] => optional(app(\App\Services\CatalogService::class)->term($c[1]))->count])->filter(fn ($n) => $n !== null)->all());
@endphp
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Catalog') }}</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">{{ __('Parts catalog') }}</span>
      <h1>{{ __('Every part we stock, grouped the way a driveline is built') }}</h1>
      <p class="lead">{{ __(':n parts across :c categories, cross-referenced to the series and the part numbers you already run. Search by number, or walk the groups.', ['n' => number_format($totalParts), 'c' => $udsCatalog['cats'] ?? count($cats)]) }}</p>
      <div class="phero__cta">
        <button type="button" class="btn btn-primary btn-lg" data-open-search><x-icon name="search"/> {{ __('Search by part number') }}</button>
        <a href="/quick-order" class="btn btn-ghost btn-lg">{{ __('Quick order, many numbers at once') }}</a>
      </div>
      <ul class="phero__facts">
        @foreach($groups as $g)<li><b class="u-mono">{{ number_format($g['count']) }}</b><span>{{ mb_strtolower(__($g['label'])) }}</span></li>@endforeach
      </ul>
    </div>
    <div class="phero__media"><picture><source srcset="{{ asset('img/photos/tile-driveshaft-components.webp') }}" type="image/webp"><img src="{{ asset('img/photos/tile-driveshaft-components.jpg') }}" width="1000" height="750" alt="{{ __('Driveshaft joints and yokes on the bench') }}" class="phero__photo"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head s-head--between"><div><h2>{{ __('Shop by group') }}</h2><p>{{ __('Four groups, from the finished shaft down to the snap ring.') }}</p></div></div>
  <div class="uhub">
    @foreach($groups as $g)
      <a class="uhubcard" href="/shop/{{ $g['slug'] }}">
        <span class="uhubcard__media">@if($g['image'])<img src="{{ $g['image'] }}" alt="{{ __($g['label']) }}" loading="lazy" width="400" height="300">@else<x-icon name="package"/>@endif</span>
        <span class="uhubcard__body"><b>{{ __($g['label']) }}</b><span>{{ __($g['cat_count'] == 1 ? ':n parts in :c category' : ':n parts in :c categories', ['n' => number_format($g['count']), 'c' => $g['cat_count']]) }}</span></span>
      </a>
    @endforeach
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head s-head--between">
    <div><h2>{{ __('Series we stock') }}</h2><p>{{ __('Universal joints, yokes and slip assemblies are organized by series. Pick yours to see everything that fits it.') }}</p></div>
    <a class="s-link" href="/shop/driveshaft-components/u-joints">{{ __('All universal joints') }} <x-icon name="arrow-right"/></a>
  </div>
  <ul class="n-series">
    @foreach(array_slice(app(\App\Services\CatalogService::class)->seriesList(), 0, 16, true) as $slug => $sv)
      <li><a href="/series/{{ $slug }}" title="{{ __(':n :series series parts', ['n' => $sv['count'], 'series' => $sv['name']]) }}">{{ \App\Support\PartGlossary::t($sv['name']) }}</a></li>
    @endforeach
    <li><a href="/series">{{ __('All series') }} <x-icon name="arrow-right"/></a></li>
  </ul>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head s-head--between"><div><h2>{{ __('Shop by category') }}</h2><p>{{ __('Counts are live from the shelf.') }}</p></div></div>
  <div class="catgrid">
    @foreach($cats as [$name, $slug, $count, $img])
      <a class="catcard" href="{{ app(\App\Services\CatalogService::class)->categoryPath($slug) }}">
        <span class="catcard__media"><img src="{{ media_url($img, 160) }}" alt="" loading="lazy" width="80" height="80"></span>
        <span class="catcard__body"><b>{{ __($name) !== $name ? __($name) : \App\Support\PartGlossary::t($name) }}</b><span class="u-mono">{{ $liveCounts[$slug] ?? $count }} {{ __('parts') }}</span></span>
      </a>
    @endforeach
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="ctaband">
    <div><span class="kicker">{{ __('Need the whole shaft?') }}</span><h2>{{ __('Find it by vehicle, or build it to your measurements.') }}</h2><p>{{ __('Complete driveshafts for :makes makes in the finder, and the online builder prices a custom one from six measurements.', ['makes' => (\Illuminate\Support\Facades\Cache::get('uds_finder_totals')['makes'] ?? 43)]) }}</p></div>
    <div class="phero__cta"><a href="/catalog/driveshafts" class="btn btn-primary btn-lg"><x-icon name="crosshair"/> {{ __('Find by vehicle') }}</a><a href="/online-builder" class="btn btn-ghost btn-lg">{{ __('Open the online builder') }}</a></div>
  </div>
</div></section>
@endsection
