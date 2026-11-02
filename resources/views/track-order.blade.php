@extends('layouts.app')
@section('robots','noindex,follow')
@section('title', __('Track your order | Universal Driveshafts'))
@section('desc', __('Track a Universal Driveshafts order with your order number and billing email: payment, build, shipping and tracking number in one place.'))
@section('content')
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Track order') }}</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">{{ __('Orders') }}</span>
      <h1>{{ __('Track your order') }}</h1>
      <p class="lead">{{ __('Enter the order number from your confirmation e-mail and the e-mail you used at checkout. You will see payment, build status and, once it leaves the shop, the live carrier tracking.') }}</p>
      <div class="trk" id="track">
        @if(session('track_notice'))
          <div class="trk__notice" role="alert"><x-icon name="info"/><div><b>{{ __('We could not find that order') }}</b><p>{{ __(session('track_notice')) }}</p><a href="/track-order#track" class="btn btn-ghost btn-sm">{{ __('Try again') }}</a></div></div>
        @else
        <form class="trk__form" method="POST" action="/track-order#track" novalidate>
          @csrf
          <div class="field"><label for="t-id">{{ __('Order number') }}</label><input id="t-id" type="text" name="orderid" placeholder="UDS-260910-W0GM" autocomplete="off" inputmode="text" required><small>{{ __('Starts with UDS, in the subject line of your confirmation.') }}</small></div>
          <div class="field"><label for="t-email">{{ __('Billing e-mail') }}</label><input id="t-email" type="email" name="order_email" placeholder="you@company.com" autocomplete="email" required><small>{{ __('The address the confirmation was sent to.') }}</small></div>
          <button type="submit" class="btn btn-primary btn-lg">{{ __('Track my order') }} <x-icon name="arrow-right"/></button>
        </form>
        @endif
      </div>
    </div>
    <aside class="trk__side">
      <b>{{ __('Signed in?') }}</b>
      <p>{{ __('Every order, invoice and tracking number is on your account page, no lookup needed.') }}</p>
      <a class="btn btn-ghost" href="{{ auth()->check() ? '/account/orders' : '/login' }}">{{ __(auth()->check() ? 'Open my orders' : 'Sign in') }} <x-icon name="arrow-right"/></a>
      <hr>
      <b>{{ __('Rather ask a person?') }}</b>
      <p>{{ __('Call with the order number and we will read you the status.') }}</p>
      <a class="textlink" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a>
    </aside>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>{{ __('What happens after you order') }}</h2><p>{{ __('Four steps. The confirmation page and your e-mail update at each one.') }}</p></div>
  <ol class="trk__steps">
    <li><span class="trk__n u-mono">1</span><b>{{ __('Received') }}</b><p>{{ __('Your order number is issued and the build sheet goes to the shop. You get an e-mail and a text.') }}</p></li>
    <li><span class="trk__n u-mono">2</span><b>{{ __('Paid') }}</b><p>{{ __('Card payments clear immediately. For payment links and call-to-pay orders, we start once the payment is in.') }}</p></li>
    <li><span class="trk__n u-mono">3</span><b>{{ __('Picked or built') }}</b><p>{{ __('Stock parts are pulled the same day. Custom shafts are cut, welded and balanced, typically 2 to 5 business days.') }}</p></li>
    <li><span class="trk__n u-mono">4</span><b>{{ __('Shipped or ready') }}</b><p>{{ __('You receive the UPS or FedEx tracking number by e-mail and text. Pickup orders get a "ready at the counter" message instead.') }}</p></li>
  </ol>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="why-grid">
    <div class="why-card"><h3>{{ __('Shipping') }}</h3><p>{{ __('Stock orders placed before 2pm ET ship the same day from Florida. Free ground shipping in the lower 48 over $:min (tubing and driveshafts excluded); live UPS, FedEx and freight rates otherwise.', ['min' => number_format(\App\Services\CartService::freeMin())]) }}</p><a class="textlink" href="/shipping-policy">{{ __('Shipping policy') }} <x-icon name="arrow-right"/></a></div>
    <div class="why-card"><h3>{{ __('Returns and exchanges') }}</h3><p>{{ __('Unused stock parts can come back within 14 days of delivery with an RMA number. Custom-built shafts are made to your measurements and are not returnable.') }}</p><a class="textlink" href="/return-request-form">{{ __('Start a return or exchange') }} <x-icon name="arrow-right"/></a></div>
    <div class="why-card"><h3>{{ __('Local pickup') }}</h3><p>{{ __('Pickup orders are ready at our shop, :hours; the address is on your order confirmation. Need another time? Call first and we will have it waiting.', ['hours' => __(config('uds.pickup.hours'))]) }}</p><a class="textlink" href="/contact">{{ __('Contact the shop') }} <x-icon name="arrow-right"/></a></div>
  </div>
</div></section>
@endsection
