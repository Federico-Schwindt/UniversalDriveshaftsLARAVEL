@extends('layouts.app')
@php $fmt = fn ($n) => number_format((int) $n); @endphp
@section('title', 'Driveshafts by Vehicle Make & Model | Universal Driveshafts')
@section('desc', \App\Services\VehiclePages::desc([
  "Complete driveshafts for {$fmt($total_models)} vehicle models from {$total_makes} makes, {$y1}–{$y2}: {$fmt($total_parts)} part numbers with price, position and center bearing.",
  'Browse by make or use the vehicle finder.',
]))
@section('canonical', '/driveshafts')
@push('schema')
@include('partials.breadcrumb-schema', ['crumbs' => [['Home', url('/')], ['Driveshafts by vehicle', url('/driveshafts')]]])
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org', '@@type' => 'CollectionPage',
  'name' => 'Driveshafts by vehicle make and model', 'url' => url('/driveshafts'),
  'description' => "Complete driveshafts for {$total_models} vehicle models from {$total_makes} makes.",
  'mainEntity' => ['@@type' => 'ItemList', 'numberOfItems' => count($makes), 'itemListElement' => collect($makes)->values()->map(fn ($m, $i) => [
      '@@type' => 'ListItem', 'position' => $i + 1, 'name' => $m['name'] . ' driveshafts', 'url' => url($m['url']),
  ])->all()],
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
@section('content')
<header class="ucathead">
  <div class="wrap">
    <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
      <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
      <span aria-current="page">{{ __('Driveshafts by vehicle') }}</span>
    </nav>
    <p class="kicker">{{ __('Complete driveshafts') }}</p>
    <h1>{{ __('Driveshafts by vehicle make and model') }}</h1>
    <p class="ucathead__blurb">{{ __(':parts complete, balanced driveshafts matched to :models models from :makes makes, model years :y1 to :y2.', ['parts' => $fmt($total_parts), 'models' => $fmt($total_models), 'makes' => $total_makes, 'y1' => $y1, 'y2' => $y2]) }} {{ __('Pick your make below to see every model we cover with year ranges, positions, drivetrains, center bearing numbers and prices, or let the finder narrow it down for you.') }}</p>
    <div class="veh-stats" aria-label="{{ __('Catalog coverage') }}">
      <div><b>{{ $total_makes }}</b>{{ __('Makes') }}</div>
      <div><b>{{ $fmt($total_models) }}</b>{{ __('Models') }}</div>
      <div><b>{{ $fmt($total_parts) }}</b>{{ __('Driveshafts') }}</div>
      <div><b>{{ $y1 }}–{{ $y2 }}</b>{{ __('Model years') }}</div>
    </div>
    <div class="veh-acts">
      <a class="btn btn-primary" href="/catalog/driveshafts"><x-icon name="crosshair"/> {{ __('Find by year, make and model') }}</a>
      <a class="btn btn-ghost" href="/online-builder">{{ __('Build one to spec') }}</a>
    </div>
  </div>
</header>

<section class="section">
  <div class="wrap">
    <h2 id="makes">{{ __('Choose your make') }}</h2>
    <p class="veh-lead">{{ __('Each make page lists every model with the years we cover and how many shafts fit it.') }}</p>
    <div class="veh-makes" role="list">
      @foreach($makes as $m)
        <a class="veh-make" role="listitem" href="{{ $m['url'] }}">
          <span>@if($m['logo'])<img src="{{ $m['logo'] }}" alt="" width="44" height="44" loading="lazy">@else<x-icon name="truck"/>@endif</span>
          <div><b>{{ $m['name'] }}</b><small>{{ trans_choice('{1} :n model|[2,*] :n models', $m['models'], ['n' => $m['models']]) }} · {{ trans_choice('{1} :n shaft|[2,*] :n shafts', $m['parts'], ['n' => $fmt($m['parts'])]) }}</small></div>
        </a>
      @endforeach
    </div>
  </div>
</section>

<section class="section section--tint">
  <div class="wrap">
    <h2>{{ __('How it works') }}</h2>
    <div class="veh-steps">
      <div class="veh-step"><i>1</i><h3>{{ __('Pick the vehicle') }}</h3><p>{{ __('Open your make, then your model. Every page is built from our fitment catalog: :n models, year by year, with the position (front, rear or intermediate), drivetrain and transmission each shaft fits.', ['n' => $fmt($total_models)]) }}</p></div>
      <div class="veh-step"><i>2</i><h3>{{ __('Match the part number') }}</h3><p>{{ __('Each row is one complete driveshaft. Two-piece shafts list the center support bearing that ships installed. Open the part page for photos, weight, weld-to-weld length and every other vehicle it fits.') }}</p></div>
      <div class="veh-step"><i>3</i><h3>{{ __('Order or call') }}</h3><p>{{ __('Add to cart with live pricing, or call (813) 524-6491 and a driveline tech confirms the fit before you order. Not listed? We build custom and heavy-duty driveshafts to your measurements in Florida.') }}</p></div>
    </div>
    <p class="veh-cta"><a class="btn btn-primary" href="/catalog/driveshafts"><x-icon name="crosshair"/> {{ __('Not sure? Use the vehicle finder') }}</a> <a class="textlink" href="/shop/complete-driveshafts/by-vehicle">{{ __('Browse all complete driveshafts') }} <x-icon name="arrow-right"/></a></p>
    <p class="xref-note veh-note">{{ __('Vehicle makes, logos, models and manufacturer part numbers are trademarks of their respective owners, used for identification and fitment reference only. Universal Driveshafts is not affiliated with them. Fitment data may contain errors, verify before ordering.') }} <a href="/terms">{{ __('Terms of use') }}</a></p>
  </div>
</section>
@endsection
