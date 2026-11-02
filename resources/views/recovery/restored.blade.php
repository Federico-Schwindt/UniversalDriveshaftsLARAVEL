@extends('layouts.app')
@section('title', __('Your cart is back | Universal Driveshafts'))
@section('robots', 'noindex,nofollow')
@section('content')
<section class="section"><div class="wrap rcv-page">
  @if($restored)
    <p class="kicker">{{ __('Cart restored') }}</p>
    <h1 style="margin:.3rem 0 .6rem">{{ __('Welcome back. Your parts are in the cart.') }}</h1>
    <p style="color:var(--ink-2);margin:0 0 .4rem">{{ __("We re-checked today's prices and availability on every line.") }} {{ trans_choice(':count item is ready to check out, ships from Florida.|:count items are ready to check out, ships from Florida.', $count) }}</p>
    <ul class="rcv-list">
      @foreach($restored as $i)
        <li>
          @if($i['image'])<img src="{{ media_url($i['image'], 160) }}" alt="" loading="lazy">@else<span style="width:52px;height:52px;border-radius:8px;background:var(--paper-2);flex:0 0 auto"></span>@endif
          <span class="n">{{ $i['name'] }}@if($i['sku'])<small>{{ $i['sku'] }} · {{ __('qty :n', ['n' => $i['qty']]) }}</small>@else<small>{{ __('qty :n', ['n' => $i['qty']]) }}</small>@endif</span>
          <span class="p">${{ number_format($i['price'] * $i['qty'], 2) }}</span>
        </li>
      @endforeach
    </ul>
    @if($skipped)
      <div class="rcv-skipped"><b>{{ __('Not restored:') }}</b> {{ implode(', ', $skipped) }}. {!! trans_choice('This part is no longer available online. Call :phone and we will source it.|These parts are no longer available online. Call :phone and we will source them.', count($skipped), ['phone' => '<a href="tel:+18135246491" style="color:inherit;font-weight:600">(813) 524-6491</a>']) !!}</div>
    @endif
    <div class="btn-row">
      <a class="btn btn-primary btn-lg" href="/checkout">{{ __('Check out') }} · ${{ number_format($subtotal, 2) }}</a>
      <a class="btn btn-ghost btn-lg" href="/cart">{{ __('Review cart') }}</a>
    </div>
    <p style="margin-top:1.2rem;font-size:.92rem;color:var(--ink-faint)">{{ __('Prefer to collect? Choose local pickup at checkout, :hours.', ['hours' => __(config('uds.pickup.hours'))]) }} {{ __('Questions about fit:') }} <a href="tel:+18135246491">(813) 524-6491</a>.</p>
  @else
    <div class="aempty">
      <b>{{ __('Those parts are no longer available online') }}</b>
      @if($skipped)<p style="margin:.2rem 0 1rem">{{ implode(', ', $skipped) }}</p>@endif
      <p style="margin:0 0 1.2rem">{!! __('Stock moves fast on driveline parts. Call :phone and a tech will source the same part, or browse the catalog for an alternate.', ['phone' => '<a href="tel:+18135246491">(813) 524-6491</a>']) !!}</p>
      <div class="btn-row" style="justify-content:center"><a class="btn btn-primary" href="/shop">{{ __('Browse the catalog') }}</a><a class="btn btn-ghost" href="/cart">{{ __('Open my cart') }}</a></div>
    </div>
  @endif
</div></section>
@endsection
