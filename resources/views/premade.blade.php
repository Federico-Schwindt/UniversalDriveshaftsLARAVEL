@extends('layouts.app')
@section('title','Premade driveshafts, ready to ship | Universal Driveshafts')
@section('desc','Ready-to-install premade driveshafts for common fitments, assembled, balanced and shipped the day you order from Florida. Find yours by vehicle.')
@section('bodyclass','pg')
@section('content')
@php $cs = app(\App\Services\CatalogService::class); @endphp
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><a href="/shop/complete-driveshafts">{{ __('Complete driveshafts') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Premade driveshafts') }}</span></nav>
  <div class="pagehead__row">
    <h1>{{ __('Premade driveshafts, assembled and balanced') }}</h1>
    <div class="btn-row"><a href="/catalog/driveshafts" class="btn btn-primary btn-sm"><x-icon name="car"/> {{ __('Find yours by vehicle') }}</a><a href="/shop/complete-driveshafts/premade-driveshafts" class="btn btn-ghost btn-sm">{{ __('Shop all') }} {{ $total }} {{ __('premade') }}</a></div>
  </div>
  <p class="pagehead__sub">{{ __('Common fitments built ahead of time so you are not waiting on a custom shaft. Every one is balanced in our shop and packed the day you order. Stock orders placed before 2pm EST ship the same day.') }}</p>
</div>

<section class="catsec"><div class="wrap">
  <div class="catsec__head">
    <h2>{{ __('In stock right now') }} <span>{{ $total }}</span></h2>
    <a class="textlink" href="/shop/complete-driveshafts/premade-driveshafts">{{ __('All') }} {{ $total }} {{ __('premade driveshafts') }} <x-icon name="arrow-right"/></a>
  </div>
  <ul class="pcards">
    @foreach($products as $p)
      <li class="pcard">
        <a class="pcard__img" href="/parts/{{ rawurlencode($p['sku']) }}">@if($p['image'])<img src="{{ $p['image'] }}" alt="{{ $p['sku'] }}" width="240" height="240" loading="lazy">@else<x-icon name="package"/>@endif</a>
        <a class="pcard__sku" href="/parts/{{ rawurlencode($p['sku']) }}">{{ $p['sku'] }}</a>
        <span class="pcard__name">{{ \Illuminate\Support\Str::limit($cs->cleanName($p), 64) }}</span>
        <div class="pcard__buy">
          <span class="pcard__price">@if($p['price'] > 0)${{ number_format($p['price'], 2) }}@else{{ __('Call for price') }}@endif</span>
          @if($p['price'] > 0 && !empty($p['id']))<button type="button" class="btn btn-primary btn-sm p-plus" data-add="{{ $p['id'] }}" aria-label="{{ __('Add :sku to cart', ['sku' => $p['sku']]) }}"><x-icon name="cart"/> {{ __('Add') }}</button>@endif
        </div>
      </li>
    @endforeach
  </ul>
  <ul class="pg-chips" style="margin-top:.9rem">
    <li><a href="/shop/complete-driveshafts/certifed-driveshafts">{{ __('Certified take-offs') }}<small>{{ $certified }}</small></a></li>
    <li><a href="/shop/complete-driveshafts/short-couple-driveshafts">{{ __('Short couple shafts') }}<small>{{ $shortCouple }}</small></a></li>
    <li><a href="/shop/complete-driveshafts">{{ __('All complete driveshafts') }}</a></li>
  </ul>
</div></section>

<section class="catsec"><div class="wrap">
  <div class="catsec__head"><h2>{{ __('Premade, or built to your measurements?') }}</h2></div>
  <div class="pg-opts">
    <div class="pg-opt">
      <h3>{{ __('Premade: factory-length replacement') }}</h3>
      <ul class="pg-checks"><li>{{ __('Balanced, boxed, on the shelf') }}</li><li>{{ __('Ships same day on stock') }}</li><li>{{ __('Best price for an OEM fitment') }}</li><li>{{ __('Returnable if it does not fit') }}</li></ul>
      <a href="/shop/complete-driveshafts/premade-driveshafts" class="btn btn-primary btn-sm">{{ __('Shop premade') }}</a>
    </div>
    <div class="pg-opt">
      <h3>{{ __('Built to spec: custom length, your yokes') }}</h3>
      <ul class="pg-checks"><li>{{ __('Pick duty, series, material and yokes') }}</li><li>{{ __('Enter six measurements, see the price live') }}</li><li>{{ __('One piece or two, steel or aluminum') }}</li><li>{{ __('Built and balanced in Florida') }}</li></ul>
      <a href="/online-builder" class="btn btn-ghost btn-sm">{{ __('Open the online builder') }}</a>
    </div>
  </div>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  <div class="catsec__head"><h2>{{ __('Frequently asked questions') }}</h2></div>
  <div class="pg-faq">
  @include('partials.faq', ['faqs' => [
    ['How do I know if I need a driveshaft replacement?', 'Vibration that changes with speed, a clunk when you shift into gear, or play in the u-joints are the usual signs. If the tube is dented or the slip yoke is worn, replace the shaft rather than the joints.'],
    ['Is a premade driveshaft as good as a custom one?', 'For a stock vehicle, yes: it is the same assembly and balancing process, built ahead of time to the factory length. Custom makes sense when the vehicle is lifted, swapped or the length is non-standard.'],
    ['How long does shipping take?', 'Stock orders placed before 2pm EST ship the same day from Florida. Most of the U.S. sees the shaft in 2 to 5 business days by ground.'],
    ['What if it does not fit?', 'Unused premade shafts can be returned under the return policy. Measure twice: the part page lists the overall length and the yoke series.'],
  ]])
  </div>
</div></section>

<div class="stock-pop" id="stock-pop"></div>
@endsection
@push('scripts')
@include('shop.partials.js-i18n')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
@endpush
