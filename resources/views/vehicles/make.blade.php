@extends('layouts.app')
@php
  use App\Services\VehiclePages;
  $fmt = fn ($n) => number_format((int) $n); $n = count($models);
  $span = $y1 === $y2 ? (string) $y1 : "{$y1}–{$y2}";
@endphp
@section('title', VehiclePages::title("$make Driveshafts by Model", $span))
@section('desc', VehiclePages::desc([
  "Complete driveshafts for $n $make model" . ($n === 1 ? '' : 's') . ", $span: {$fmt($parts)} part numbers with year ranges, position, drivetrain and price.",
  $price_min !== null ? 'Priced from ' . VehiclePages::money($price_min) . '.' : 'Call for pricing.',
  'Assembled and balanced, ships from Florida.',
]))
@section('canonical', $url)
@section('bodyclass','pg')
@push('schema')
@include('partials.breadcrumb-schema', ['crumbs' => [['Home', url('/')], ['Driveshafts by vehicle', url('/driveshafts')], [$make, url($url)]]])
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org', '@@type' => 'CollectionPage',
  'name' => "$make driveshafts by model", 'url' => url($url),
  'description' => "Complete driveshafts for $n $make models, $span.",
  'mainEntity' => ['@@type' => 'ItemList', 'numberOfItems' => $n, 'itemListElement' => collect($models)->values()->map(fn ($m, $i) => [
      '@@type' => 'ListItem', 'position' => $i + 1, 'name' => "{$m['make']} {$m['model']} driveshafts", 'url' => url($m['url']),
  ])->all()],
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
@section('content')
@php
  // whole sentences, so they translate as sentences (Spanish word order and plurals differ)
  $blurb = trans_choice('{1} We stock :parts complete driveshaft for :make, covering model years :span.|[2,*] We stock :parts complete driveshafts for :n :make models, covering model years :span.', $n, ['parts' => $fmt($parts), 'n' => $n, 'make' => $make, 'span' => $span]);
  if ($drives) $blurb .= ' ' . __('Available for :drives drivetrains.', ['drives' => VehiclePages::join($drives)]);
  $blurb .= ' ' . ($price_min !== null
      ? __('Every shaft ships assembled and balanced, priced from :price.', ['price' => VehiclePages::money($price_min)])
      : __('Every shaft ships assembled and balanced.'));
@endphp
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <a href="/driveshafts">{{ __('Driveshafts by vehicle') }}</a><x-icon name="caret-right"/>
    <span aria-current="page">{{ $make }}</span>
  </nav>
  <div class="pagehead__row">
    <div class="pagehead__title">
      @if($logo)<span class="pagehead__logo"><img src="{{ $logo }}" alt="{{ $make }}" width="160" height="80"></span>@endif
      <h1>{{ __(':make driveshafts by model', ['make' => $make]) }}</h1>
    </div>
    <div class="btn-row"><a class="btn btn-primary btn-sm" href="/catalog/driveshafts?{{ http_build_query(['make' => $make]) }}"><x-icon name="car"/> {{ __('Find my :make driveshaft', ['make' => $make]) }}</a></div>
  </div>
  <p class="pagehead__sub">{{ $blurb }} {{ __('Pick your model for the year-by-year fitment table, part numbers and center bearing details.') }}</p>
</div>

<section class="catsec"><div class="wrap">
  <div class="catsec__head"><h2 id="models">{{ __(':make models we cover', ['make' => $make]) }} <span>{{ $n }}</span></h2></div>
  <div class="ptbl-wrap"><table class="ptbl veh-table veh-table--models">
    <thead><tr><th class="l">{{ __('Model') }}</th><th>{{ __('Model years') }}</th><th>{{ __('Driveshafts') }}</th><th>{{ __('Fitments') }}</th><th></th></tr></thead>
    <tbody>
    @foreach($models as $m)
      <tr>
        <td class="l" data-label="{{ __('Model') }}"><a class="veh-model" href="{{ $m['url'] }}">{{ $make }} {{ $m['model'] }}</a></td>
        <td data-label="{{ __('Model years') }}">{{ $m['y1'] === $m['y2'] ? $m['y1'] : $m['y1'] . '–' . $m['y2'] }}</td>
        <td data-label="{{ __('Driveshafts') }}">{{ $m['parts'] }}</td>
        <td data-label="{{ __('Fitments') }}">{{ $fmt($m['fitments']) }}</td>
        <td data-label="{{ __('View') }}"><a class="btn btn-line btn-sm" href="{{ $m['url'] }}">{{ __('View parts') }} <x-icon name="arrow-right"/></a></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  <div class="catsec__head"><h2>{{ __('Other makes') }}</h2><a class="textlink" href="/driveshafts">{{ __('All makes') }} <x-icon name="arrow-right"/></a></div>
  <div class="veh-makes veh-makes--compact" role="list">
    @foreach($others as $o)
      <a class="veh-make" role="listitem" href="{{ $o['url'] }}">
        <span>@if($o['logo'])<img src="{{ $o['logo'] }}" alt="" width="44" height="44" loading="lazy">@else<x-icon name="truck"/>@endif</span>
        <div><b>{{ $o['name'] }}</b><small>{{ trans_choice('{1} :n model|[2,*] :n models', $o['models'], ['n' => $o['models']]) }}</small></div>
      </a>
    @endforeach
  </div>
  <p class="xref-note veh-note">{{ __('Vehicle makes, logos, models and manufacturer part numbers are trademarks of their respective owners, used for identification and fitment reference only. Universal Driveshafts is not affiliated with them. Fitment data may contain errors, verify before ordering.') }} <a href="/terms">{{ __('Terms of use') }}</a></p>
</div></section>
@endsection
