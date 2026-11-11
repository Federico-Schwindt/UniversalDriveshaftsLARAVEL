@extends('layouts.app')
@php $fmt = fn ($n) => number_format((int) $n); @endphp
@section('title', 'Driveshafts by Vehicle Make & Model | Universal Driveshafts')
@section('desc', \App\Services\VehiclePages::desc([
  "Complete driveshafts for {$fmt($total_models)} vehicle models from {$total_makes} makes, {$y1}–{$y2}: {$fmt($total_parts)} part numbers with price, position and center bearing.",
  'Browse by make or use the vehicle finder.',
]))
@section('canonical', '/driveshafts')
@section('bodyclass','pg')
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
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <span aria-current="page">{{ __('Driveshafts by vehicle') }}</span>
  </nav>
  <div class="pagehead__row">
    <h1>{{ __('Driveshafts by vehicle make and model') }}</h1>
    <div class="btn-row"><a class="btn btn-primary btn-sm" href="/catalog/driveshafts"><x-icon name="car"/> {{ __('Find by year, make and model') }}</a><a class="btn btn-ghost btn-sm" href="/online-builder"><x-icon name="wrench"/> {{ __('Build one to spec') }}</a></div>
  </div>
  <p class="pagehead__sub">{{ __(':parts complete, balanced driveshafts matched to :models models from :makes makes, model years :y1 to :y2.', ['parts' => $fmt($total_parts), 'models' => $fmt($total_models), 'makes' => $total_makes, 'y1' => $y1, 'y2' => $y2]) }} {{ __('Each make page lists every model with the years we cover and how many shafts fit it.') }}</p>
</div>

<section class="catsec"><div class="wrap">
  <div class="catsec__head"><h2 id="makes">{{ __('Choose your make') }} <span>{{ $total_makes }}</span></h2><a class="textlink" href="/shop/complete-driveshafts/by-vehicle">{{ __('Browse all complete driveshafts') }} <x-icon name="arrow-right"/></a></div>
  <div class="veh-makes" role="list">
    @foreach($makes as $m)
      <a class="veh-make" role="listitem" href="{{ $m['url'] }}">
        <span>@if($m['logo'])<img src="{{ $m['logo'] }}" alt="" width="44" height="44" loading="lazy">@else<x-icon name="truck"/>@endif</span>
        <div><b>{{ $m['name'] }}</b><small>{{ trans_choice('{1} :n model|[2,*] :n models', $m['models'], ['n' => $m['models']]) }} · {{ trans_choice('{1} :n shaft|[2,*] :n shafts', $m['parts'], ['n' => $fmt($m['parts'])]) }}</small></div>
      </a>
    @endforeach
  </div>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  <div class="pg-call">
    <p><b>{{ __('Not listed?') }}</b> {{ __('Call (813) 524-6491 and a driveline tech confirms the fit before you order, or we build custom and heavy-duty driveshafts to your measurements in Florida.') }}</p>
    <a class="pg-tel" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a>
    <a class="btn btn-ghost btn-sm" href="/online-builder">{{ __('Build one to spec') }}</a>
  </div>
  <p class="xref-note veh-note">{{ __('Vehicle makes, logos, models and manufacturer part numbers are trademarks of their respective owners, used for identification and fitment reference only. Universal Driveshafts is not affiliated with them. Fitment data may contain errors, verify before ordering.') }} <a href="/terms">{{ __('Terms of use') }}</a></p>
</div></section>
@endsection
