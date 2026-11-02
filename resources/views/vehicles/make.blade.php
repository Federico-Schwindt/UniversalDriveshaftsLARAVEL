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
<header class="ucathead">
  <div class="wrap">
    <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
      <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
      <a href="/driveshafts">{{ __('Driveshafts by vehicle') }}</a><x-icon name="caret-right"/>
      <span aria-current="page">{{ $make }}</span>
    </nav>
    @php
      // whole sentences, so they translate as sentences (Spanish word order and plurals differ)
      $blurb = trans_choice('{1} We stock :parts complete driveshaft for :make, covering model years :span.|[2,*] We stock :parts complete driveshafts for :n :make models, covering model years :span.', $n, ['parts' => $fmt($parts), 'n' => $n, 'make' => $make, 'span' => $span]);
      if ($drives) $blurb .= ' ' . __('Available for :drives drivetrains.', ['drives' => VehiclePages::join($drives)]);
      $blurb .= ' ' . ($price_min !== null
          ? __('Every shaft ships assembled and balanced, priced from :price.', ['price' => VehiclePages::money($price_min)])
          : __('Every shaft ships assembled and balanced.'));
    @endphp
    <div class="vhero">
      <div class="vhero__text">
        <p class="kicker">{{ __('Complete driveshafts') }}</p>
        <h1>{{ __(':make driveshafts by model', ['make' => $make]) }}</h1>
        <p class="ucathead__blurb">{{ $blurb }}</p>
        <p class="vhero__how">{{ __('Pick your model for the year-by-year fitment table, part numbers and center bearing details.') }}</p>
        <div class="veh-acts">
          <a class="btn btn-primary" href="/catalog/driveshafts?{{ http_build_query(['make' => $make]) }}"><x-icon name="crosshair"/> {{ __('Find my :make driveshaft', ['make' => $make]) }}</a>
          <a class="btn btn-ghost" href="#models">{{ __('Jump to models') }}</a>
        </div>
      </div>
      <aside class="vhero__card" aria-label="{{ __(':make coverage', ['make' => $make]) }}">
        <div class="vhero__brand">
          @if($logo)<span class="vhero__logo"><img src="{{ $logo }}" alt="{{ $make }}" width="160" height="80"></span>@endif
          <span class="vhero__make">{{ $make }}</span>
        </div>
        <dl class="vhero__stats">
          <div><dt>{{ trans_choice('{1} Model|[2,*] Models', $n) }}</dt><dd>{{ $n }}</dd></div>
          <div><dt>{{ trans_choice('{1} Driveshaft|[2,*] Driveshafts', $parts) }}</dt><dd>{{ $fmt($parts) }}</dd></div>
          <div><dt>{{ __('Model years') }}</dt><dd>{{ $span }}</dd></div>
          <div><dt>{{ __('Price from') }}</dt><dd>{{ $price_min !== null ? VehiclePages::money($price_min) : __('Call') }}</dd></div>
        </dl>
        <ul class="vhero__trust">
          <li><x-icon name="check"/> {{ __('Assembled and balanced') }}</li>
          <li><x-icon name="check"/> {{ __('Ships from Florida') }}</li>
        </ul>
      </aside>
    </div>
  </div>
</header>

<section class="section">
  <div class="wrap">
    <h2 id="models">{{ __(':make models we cover', ['make' => $make]) }} <span class="vcount">{{ $n }}</span></h2>
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
  </div>
</section>

<section class="section section--tint">
  <div class="wrap">
    <h2>{{ __('Other makes') }}</h2>
    <div class="veh-makes veh-makes--compact" role="list">
      @foreach($others as $o)
        <a class="veh-make" role="listitem" href="{{ $o['url'] }}">
          <span>@if($o['logo'])<img src="{{ $o['logo'] }}" alt="" width="44" height="44" loading="lazy">@else<x-icon name="truck"/>@endif</span>
          <div><b>{{ $o['name'] }}</b><small>{{ trans_choice('{1} :n model|[2,*] :n models', $o['models'], ['n' => $o['models']]) }}</small></div>
        </a>
      @endforeach
    </div>
    <p class="veh-cta"><a class="btn btn-primary" href="/catalog/driveshafts?{{ http_build_query(['make' => $make]) }}"><x-icon name="crosshair"/> {{ __('Not sure? Use the vehicle finder') }}</a> <a class="textlink" href="/driveshafts">{{ __('All makes') }} <x-icon name="arrow-right"/></a></p>
    <p class="xref-note veh-note">{{ __('Vehicle makes, logos, models and manufacturer part numbers are trademarks of their respective owners, used for identification and fitment reference only. Universal Driveshafts is not affiliated with them. Fitment data may contain errors, verify before ordering.') }} <a href="/terms">{{ __('Terms of use') }}</a></p>
  </div>
</section>
@endsection
