@extends('layouts.app')
@section('robots','noindex,follow')
@section('title','Your cart | Universal Driveshafts')
@section('desc','Review the parts in your Universal Driveshafts cart before checkout.')

@push('scripts')
<script src="{{ asset('js/cart.js') }}?v={{ @filemtime(public_path('js/cart.js')) }}" defer></script>
@endpush

@section('content')
<div class="wrap">
  <nav class="ucrumb" aria-label="{!! __('Breadcrumb') !!}">
    <a href="/">{!! __('Home') !!}</a><x-icon name="caret-right"/><span aria-current="page">{!! __('Cart') !!}</span>
  </nav>
</div>

<section class="section" style="padding-top:1rem">
  <div class="wrap">
    <div class="s-head" style="margin-bottom:1.75rem"><h1>{!! __('Your cart') !!}</h1></div>

    @if(empty($items))
      <div class="cartempty">
        <x-icon name="cart"/>
        <h2>{!! __('Nothing in the cart yet') !!}</h2>
        <p>{!! __('Search a part number, browse the catalog, or build a driveshaft to your own measurements.') !!}</p>
        <div class="cartempty__cta">
          <a href="/shop" class="btn btn-primary">{!! __('Browse the catalog') !!} <x-icon name="arrow-right"/></a>
          <a href="/online-builder" class="btn btn-ghost">{!! __('Open the online builder') !!}</a>
        </div>
      </div>
    @else
      @php $freeBase = $freeBase ?? $subtotal; $remaining = max(0, $freeMin - $freeBase); $pct = $freeMin > 0 ? min(100, round($freeBase / $freeMin * 100)) : 100; @endphp
      <div class="cartpage" data-cart>
        <div class="cartlist">
          @foreach($items as $i)
            <article class="cartline" data-key="{{ $i['key'] }}" data-price="{{ $i['price'] }}">
              <span class="cartline__media">
                @if(!empty($i['image']))<img src="{{ $i['image'] }}" alt="" loading="lazy">@else<x-icon name="package"/>@endif
              </span>
              <div class="cartline__info">
                @if($i['sku'])<span class="cartline__sku">{{ $i['sku'] }}</span>@endif
                <p class="cartline__name">{{ \Illuminate\Support\Str::limit($i['name'],90) }}</p>
                @if(!empty($i['meta']['summary']))<p class="cartline__cfg">{{ $i['meta']['summary'] }}</p>@endif
                <span class="cartline__unit">${{ number_format($i['price'],2) }} each</span>
                @if(!empty($i['meta']['config']))@include('partials.build-config', ['config' => $i['meta']['config']])@endif
              </div>
              <div class="cartline__right">
                <span class="cartline__total" data-line-total>${{ number_format($i['price']*$i['qty'],2) }}</span>
                <div class="uqty">
                  <button type="button" data-q="-1" aria-label="{!! __('Decrease quantity') !!}"><x-icon name="minus"/></button>
                  <input type="number" min="1" value="{{ $i['qty'] }}" data-qty aria-label="{{ __('Quantity for :sku', ['sku' => $i['sku'] ?: __('item')]) }}">
                  <button type="button" data-q="1" aria-label="{!! __('Increase quantity') !!}"><x-icon name="plus"/></button>
                </div>
                <button type="button" class="cartline__rm" data-remove><x-icon name="trash"/> {!! __('Remove') !!}</button>
              </div>
            </article>
          @endforeach
        </div>

        <aside class="cartsum">
          <h2>{!! __('Order summary') !!}</h2>
          <div class="freebar {{ $remaining <= 0 ? 'is-done' : '' }}" data-freebar data-min="{{ $freeMin }}" data-tube="{{ round($subtotal - $freeBase, 2) }}">
            <div class="freebar__track"><span class="freebar__fill" style="width:{{ $pct }}%"></span></div>
            <p data-freetext>
              @if($subtotal - $freeBase > 0.009) {{ __('Tubing and driveshafts ship at the carrier rate and do not qualify for free shipping.') }}
              @elseif($remaining <= 0) {{ __('Free US ground shipping applied.') }}
              @else {!! __(':amount more for free US ground shipping.', ['amount' => '<b>$'.number_format($remaining,2).'</b>']) !!} @endif
            </p>
          </div>
          <div class="cartsum__row"><span>{{ __('Subtotal') }} ({{ trans_choice(':count item|:count items', count($items)) }})</span><span data-subtotal>${{ number_format($subtotal,2) }}</span></div>
          <div class="cartsum__row"><span>{!! __('Shipping') !!}</span><span>{!! __('Calculated at checkout') !!}</span></div>
          <div class="cartsum__total"><span>{!! __('Total') !!}</span><span data-total>${{ number_format($subtotal,2) }}</span></div>
          <a href="/checkout" class="btn btn-primary btn-block btn-lg">{!! __('Checkout') !!} <x-icon name="arrow-right"/></a>
          @include('partials.ship-countdown', ['class' => 'shipcd--cart'])
          @include('partials.affirm-msg', ['amount' => $subtotal, 'class' => 'affirm-msg--cart'])
          <a href="/shop" class="textlink" style="justify-content:center"><x-icon name="arrow-left"/> {!! __('Keep shopping') !!}</a>
          <p class="cartsum__note">{!! __('Shipping is calculated at checkout. Prices exclude any core charge, which is quoted separately.') !!}</p>
        </aside>
      </div>
    @endif
  </div>
</section>
@endsection
