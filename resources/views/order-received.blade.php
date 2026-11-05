@extends('layouts.app')
@section('robots','noindex,follow')
@section('title','Order received | Universal Driveshafts')
@section('desc','Your Universal Driveshafts order has been received.')
@section('content')
<section class="section"><div class="wrap">
<div class="orc">
  @php
    // a card order whose payment did not go through (declined, closed 3-D Secure, abandoned wallet): nothing was
    // charged and no confirmation went out, so say that and offer the pay page instead of a "thank you"
    $unpaidCard = $order->payment_method === 'stripe' && !($order->payment_processing ?? false) && \App\Support\PayLink::payable($order);
  @endphp
  @if($unpaidCard)
  <div class="orc__badge" style="background:var(--warn-bg);color:var(--warn)"><x-icon name="warning"/></div>
  <h1>{!! __('Payment not completed') !!}</h1>
  <p class="orc__lead">{{ __('Your order is saved, but the payment did not go through and you have not been charged. Pay now to send it to the shop, or call (813) 524-6491.') }}</p>
  <div class="orc__cta" style="margin:0 0 1.25rem"><a href="{{ \App\Support\PayLink::url($order) }}" class="btn btn-primary">{{ __('Pay now') }} {{ '$'.number_format(\App\Support\PayLink::balance($order),2) }} <x-icon name="arrow-right"/></a><a href="/checkout" class="btn btn-ghost">{{ __('Back to checkout') }}</a></div>
  @else
  <div class="orc__badge"><x-icon name="check"/></div>
  <h1>{!! __('Order received') !!}</h1>
  <p class="orc__lead">{{ $order->payment_status === 'paid' || in_array($order->payment_method, ['net_terms', 'pay_at_pickup', 'manual'], true) ? __('Thanks. A confirmation is on its way to your inbox.') : __('Thanks. We have your order.') }}</p>
  @endif
  <span class="orc__num">{{ $order->order_number }}</span>
  <dl class="orc-details">
    <div class="orc-row"><dt>{!! __('Date') !!}</dt><dd>{{ \Carbon\Carbon::parse($order->created_at)->format('F j, Y') }}</dd></div>
    <div class="orc-row"><dt>{!! __('Email') !!}</dt><dd>{{ $order->email }}</dd></div>
    @php $isPickup = $order->shipping_method === \App\Services\CartService::PICKUP_ID; @endphp
    @if($isPickup)
    <div class="orc-row"><dt>{!! __('Pickup') !!}</dt><dd><b>{{ config('uds.pickup.address') }}</b><br>{{ config('uds.pickup.hours') }}. {{ __('Need another time? Call') }} <a href="{{ config('uds.pickup.phone_href') }}">{{ config('uds.pickup.phone') }}</a> {!! __('first. Nothing from this order will be shipped.') !!}</dd></div>
    @else
    <div class="orc-row"><dt>{!! __('Shipping') !!}</dt><dd>{{ \App\Services\CartService::labelFor((string) $order->shipping_label) }} @if($order->shipping_total>0)(${{ number_format($order->shipping_total,2) }})@else ({{ __('free') }}) @endif</dd></div>
    @endif
    <div class="orc-row"><dt>{!! __('Payment') !!}</dt><dd>@if($order->payment_status==='paid') {{ __('Paid') }}@elseif($order->payment_processing ?? false) {{ __('Payment received, Stripe is confirming it. You will get an e-mail as soon as it clears.') }}@elseif($order->payment_method==='pay_at_pickup') {{ __('Due at the counter when you collect your parts. Bring your order number.') }}@elseif($order->payment_method==='net_terms') {{ __('Invoice, due :date. We e-mailed it with a link to pay.', ['date' => \Carbon\Carbon::parse($order->terms_due_at)->format('F j, Y')]) }}@elseif($unpaidCard) {{ __('Not paid yet. Use Pay now above, or call (813) 524-6491.') }}@else {{ __('Not paid yet. Pay any time from the link in your confirmation e-mail, or call (813) 524-6491.') }}@endif</dd></div>
    @if($order->tracking_number)
      <div class="orc-row orc-row--ship"><dt>{!! __('Shipped') !!}</dt><dd>
        <a href="{{ \App\Support\OrderEvents::carrierUrl($order->tracking_carrier,$order->tracking_number) }}" target="_blank" rel="noopener" class="orc-track">
          <x-icon name="package"/> {{ strtoupper($order->tracking_carrier) }} {{ $order->tracking_number }} <x-icon name="arrow-up-right"/>
        </a>
        @if($order->shipped_at)<span class="orc-shipdate">{{ \Carbon\Carbon::parse($order->shipped_at)->format('F j, Y') }}</span>@endif
      </dd></div>
    @elseif($isPickup && $order->status !== 'cancelled')
      <div class="orc-row"><dt>{!! __('Pickup status') !!}</dt><dd>{!! __('Being prepared. We will e-mail and text you the moment your parts are ready at the counter.') !!}</dd></div>
    @elseif($order->status !== 'cancelled')
      <div class="orc-row"><dt>{!! __('Shipping status') !!}</dt><dd>{!! __('Not shipped yet, we will e-mail and text you the tracking number the moment it leaves the shop.') !!}</dd></div>
    @endif
  </dl>
  <h2>{!! __('What you ordered') !!}</h2>
  <dl class="orc-details">
    @foreach($items as $i)
      @php $meta = json_decode($i->meta ?? '{}', true); @endphp
      <div class="orc-row orc-row--line"><dt style="color:var(--ink);font-weight:600;text-align:left">{{ $i->name }} × {{ $i->qty }}@if(!empty($meta['summary']))<br><small style="color:var(--ink-faint);font-weight:400">{{ $meta['summary'] }}</small>@endif</dt><dd>${{ number_format($i->line_total,2) }}</dd></div>
      @if(!empty($meta['config']))<div class="orc-cfg">@include('partials.build-config', ['config' => $meta['config'], 'open' => true])</div>@endif
    @endforeach
    <div class="orc-row orc-row--line"><dt>{!! __('Subtotal') !!}</dt><dd>${{ number_format($order->subtotal,2) }}</dd></div>
    @if($order->discount_total>0)
    <div class="orc-row orc-row--line"><dt>{!! __('Discount') !!}@if($order->coupon_code) <b>{{ $order->coupon_code }}</b>@elseif(($order->tier_discount ?? 0) > 0 && $order->b2b_tier) <b>{{ __(':tier account', ['tier' => \App\Services\B2bAccounts::label($order->b2b_tier)]) }}</b>@endif</dt><dd>-${{ number_format($order->discount_total,2) }}</dd></div>
    @endif
    <div class="orc-row orc-row--line"><dt>{!! __('Shipping') !!}</dt><dd>@if($order->shipping_total>0)${{ number_format($order->shipping_total,2) }}@else {{ __('Free') }} @endif</dd></div>
    <div class="orc-row orc-row--total"><dt>{!! __('Total') !!}</dt><dd>${{ number_format($order->total,2) }}</dd></div>
  </dl>
  <p class="orc__help">{!! __('Questions about your order? Call') !!} <a href="tel:+18135246491">(813) 524-6491</a> {!! __('or email') !!} <a href="mailto:Inquiry@universaldriveshafts.com">{!! __('Inquiry@universaldriveshafts.com') !!}</a> {!! __('with your order number.') !!}</p>
  <div class="orc__cta"><a href="/shop" class="btn btn-primary">{!! __('Keep shopping') !!} <x-icon name="arrow-right"/></a><a href="{{ \App\Support\OrderEvents::statusUrl($order, false) }}" class="btn btn-ghost">{!! __('Order status &amp; tracking') !!} <x-icon name="arrow-right"/></a></div>
</div>
</div></section>
@endsection
@if(!$unpaidCard)
@push('scripts')
@php
  // item_id = the id Merchant Center knows the product by (Google Ads ties the sale to it); Meta keeps the SKU
  $convItems = $items->map(fn ($i) => ['item_id' => $i->wp_product_id && $i->type === 'product' ? \App\Http\Controllers\FeedController::offerId((int) $i->wp_product_id) : (string) ($i->sku ?: $i->wp_product_id),
      'item_name' => (string) $i->name, 'price' => round((float) $i->price, 2), 'quantity' => (int) $i->qty, 'sku' => (string) ($i->sku ?: $i->wp_product_id)])->values()->all();
  $convOrder = ['transaction_id' => (string) $order->order_number, 'value' => round((float) $order->total, 2), 'currency' => 'USD',
                'shipping' => round((float) $order->shipping_total, 2), 'coupon' => (string) ($order->coupon_code ?? ''), 'items' => $convItems];
@endphp
{{-- conversion for GA4 / Google Ads / Meta, sent once per order even if the page is reloaded --}}
<script>
(function () {
  var key = 'uds_conv_{{ $order->order_number }}';
  try { if (localStorage.getItem(key)) return; localStorage.setItem(key, '1'); } catch (e) {}
  var data = @json($convOrder), items = data.items;
  if (window.gtag) gtag('event', 'purchase', data);
  if (window.fbq) fbq('track', 'Purchase', {value: data.value, currency: 'USD', content_type: 'product', contents: items.map(function (i) { return {id: i.sku, quantity: i.quantity}; })});
})();
</script>
@endpush
@endif
