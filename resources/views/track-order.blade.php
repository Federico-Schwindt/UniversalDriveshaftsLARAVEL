@extends('layouts.app')
@section('robots','noindex,follow')
@section('title', __('Track your order | Universal Driveshafts'))
@section('desc', __('Track a Universal Driveshafts order with your order number and billing email: payment, build, shipping and tracking number in one place.'))
@section('bodyclass','pg')
@section('content')
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Track order') }}</span></nav>
  <div class="pagehead__row">
    <h1>{{ __('Track your order') }}</h1>
    <div class="btn-row"><a class="btn btn-ghost btn-sm" href="{{ auth()->check() ? '/account/orders' : '/login' }}"><x-icon name="user"/> {{ __(auth()->check() ? 'Open my orders' : 'Sign in') }}</a></div>
  </div>
  <p class="pagehead__sub">{{ __('Enter the order number from your confirmation e-mail and the e-mail you used at checkout. You will see payment, build status and, once it leaves the shop, the live carrier tracking.') }}</p>
</div>

<section class="catsec catsec--end"><div class="wrap"><div class="pg-cols">
  <div class="trk" id="track">
    @if(session('track_notice'))
      <div class="trk__notice" role="alert"><x-icon name="info"/><div><b>{{ __('We could not find that order') }}</b><p>{{ __(session('track_notice')) }}</p><a href="/track-order#track" class="btn btn-ghost btn-sm">{{ __('Try again') }}</a></div></div>
    @else
    <form class="trk__form" method="POST" action="/track-order#track" novalidate>
      @csrf
      <div class="field"><label for="t-id">{{ __('Order number') }}</label><input id="t-id" type="text" name="orderid" placeholder="UDS-260910-W0GM" autocomplete="off" inputmode="text" required><small>{{ __('Starts with UDS, in the subject line of your confirmation.') }}</small></div>
      <div class="field"><label for="t-email">{{ __('Billing e-mail') }}</label><input id="t-email" type="email" name="order_email" placeholder="you@company.com" autocomplete="email" required><small>{{ __('The address the confirmation was sent to.') }}</small></div>
      <button type="submit" class="btn btn-primary">{{ __('Track my order') }} <x-icon name="arrow-right"/></button>
    </form>
    @endif
    <p class="pg-note">{{ __('Stock orders placed before 2pm EST ship the same day from Florida. Custom shafts are cut, welded and balanced, typically 2 to 5 business days. You receive the UPS or FedEx tracking number by e-mail and text.') }}</p>
  </div>

  <aside class="pg-side">
    <div class="pg-card">
      <h2>{{ __('Rather ask a person?') }}</h2>
      <p>{{ __('Call with the order number and we will read you the status.') }}</p>
      <div class="pg-rows"><a href="tel:+18135246491"><x-icon name="phone"/><span class="pg-tel">(813) 524-6491</span></a></div>
    </div>
    <div class="pg-card">
      <h2>{{ __('After delivery') }}</h2>
      <div class="pg-rows">
        <a href="/shipping-policy"><x-icon name="truck"/>{{ __('Shipping policy') }}</a>
        <a href="/return-request-form"><x-icon name="returns"/>{{ __('Start a return or exchange') }}</a>
        <a href="/contact"><x-icon name="headset"/>{{ __('Contact the shop') }}</a>
      </div>
    </div>
  </aside>
</div></div></section>
@endsection
