@extends('layouts.app')
@section('robots','noindex,nofollow')
@section('title','Pay order '.$order->order_number.' | Universal Driveshafts')
@section('desc','Secure online payment for your Universal Driveshafts order.')
@section('content')
<section class="phero phero--rich payp-sec"><div class="wrap">
  <div class="payp">
    <div class="payp__main">
      <span class="kicker">{{ __('Order :n', ['n' => $order->order_number]) }}</span>
      @if($order->payment_status==='paid')
        <h1>{!! __('Thank you — this order is paid.') !!}</h1>
        <p class="lead">{{ __('We received :amount on :date. Follow progress on the status page.', ['amount' => '$'.number_format($order->total,2), 'date' => \Carbon\Carbon::parse($order->paid_at ?: $order->updated_at)->locale(app()->getLocale())->translatedFormat(['es' => 'j \\d\\e F \\d\\e Y', 'zh' => 'Y年n月j日', 'ja' => 'Y年n月j日'][app()->getLocale()] ?? 'M j, Y')]) }}</p>
        <a href="{{ $statusUrl }}" class="btn btn-primary btn-lg">{!! __('Order status') !!} <x-icon name="arrow-right"/></a>
      @elseif(!$payable)
        <h1>{!! __('Nothing to pay on this order.') !!}</h1>
        <p class="lead">{{ __('This order is :status and has no balance due. Questions? Call (813) 524-6491.', ['status' => __($order->status)]) }}</p>
      @else
        <h1>{{ __('Pay :amount', ['amount' => '$'.number_format($balance,2)]) }}</h1>
        <p class="lead">{{ __('Hi :name, here is the balance on your order.', ['name' => $order->billing_first_name]) }} @if($stripeKey){{ __('Pay securely by card below — your parts start moving as soon as it clears.') }}@else {{ __('Card payment on this link is almost ready — until then we can take payment by phone.') }}@endif</p>
        <ol class="payp__steps" aria-label="{!! __('How it works') !!}">
          <li class="is-on"><b>1</b> {!! __('Review your order') !!}</li>
          <li class="@if($stripeKey) is-on @endif"><b>2</b> {!! __('Pay') !!}</li>
          <li><b>3</b> {!! __('We build &amp; ship') !!}</li>
        </ol>
        <div class="payp__card">
          @if($stripeKey)
            <div class="field"><label for="pay-name">{!! __('Name on card') !!}</label><input id="pay-name" class="input" value="{{ trim($order->billing_first_name.' '.$order->billing_last_name) }}" autocomplete="cc-name"></div>
            <div class="field"><label>{!! __('Card') !!}</label><div id="pay-el" class="payp__el"></div><div class="payp__err" id="pay-err" role="alert"></div></div>
            <button type="button" class="btn btn-primary btn-lg payp__btn" id="pay-btn">{{ __('Pay :amount', ['amount' => '$'.number_format($balance,2)]) }} <x-icon name="arrow-right"/></button>
            <p class="payp__secure"><x-icon name="lock"/> {!! __('Encrypted and processed by Stripe. We never see your card number.') !!}</p>
          @else
            <div class="payp__phone">
              <span class="payp__phone-ic"><x-icon name="phone"/></span>
              <div>
                <h2>{!! __('Pay by phone in two minutes') !!}</h2>
                <p>{{ __('Call us with your order number and we will take the card payment right away.') }} {{ __(config('uds.stores.0.hours')) }}.</p>
              </div>
            </div>
            <div class="payp__ref"><span>{!! __('Order number') !!}</span><b id="pay-ref">{{ $order->order_number }}</b><button type="button" class="payp__copy" data-copy-ref>{!! __('Copy') !!}</button></div>
            <div class="payp__cta">
              <a class="btn btn-primary btn-lg" href="tel:+18135246491"><x-icon name="phone"/> {!! __('Call (813) 524-6491') !!}</a>
              <a class="btn btn-lg payp__alt" href="mailto:{{ config('uds.email') }}?subject={{ rawurlencode('Payment for order '.$order->order_number) }}"><x-icon name="mail"/> {!! __('E-mail us') !!}</a>
            </div>
            <p class="payp__secure"><x-icon name="shield"/> {!! __('You can also just reply to your order e-mail — we will call you back.') !!}</p>
          @endif
        </div>
        <ul class="payp__trust">
          <li><x-icon name="lock"/> {!! __('Secure checkout') !!}</li>
          <li><x-icon name="factory"/> {!! __('Built in Florida') !!}</li>
          <li><x-icon name="truck"/> {!! __('Ships from Florida') !!}</li>
        </ul>
      @endif
    </div>
    <aside class="payp__side">
      <div class="payp__box">
        <h2>{!! __('Order summary') !!}</h2>
        <ul class="payp__items">
          @foreach($items as $it)<li><span><b>{{ $it->qty }}×</b> {{ $it->name }}@if($it->sku) <small>{{ $it->sku }}</small>@endif</span><span>{{ '$'.number_format($it->line_total,2) }}</span></li>@endforeach
        </ul>
        <div class="payp__tot"><span>{!! __('Subtotal') !!}</span><span>{{ '$'.number_format($order->subtotal,2) }}</span></div>
        @if($order->discount_total>0)<div class="payp__tot"><span>{!! __('Discount') !!}</span><span>-{{ '$'.number_format($order->discount_total,2) }}</span></div>@endif
        @if($order->shipping_method==='quote')
          <div class="payp__tot"><span>{!! __('Shipping') !!} <small>{!! __('Quoted separately by our team') !!}</small></span><span class="payp__tbd">TBD</span></div>
        @else
          <div class="payp__tot"><span>{{ __('Shipping') }} @if($order->shipping_label)<small>{{ \App\Services\CartService::labelFor($order->shipping_label) }}</small>@endif</span><span>{{ $order->shipping_total>0 ? '$'.number_format($order->shipping_total,2) : __('Free') }}</span></div>
        @endif
        @if($order->tax_total>0)<div class="payp__tot"><span>{!! __('Tax') !!}</span><span>{{ '$'.number_format($order->tax_total,2) }}</span></div>@endif
        @if($order->refund_total>0)<div class="payp__tot"><span>{!! __('Refunded') !!}</span><span>-{{ '$'.number_format($order->refund_total,2) }}</span></div>@endif
        <div class="payp__tot payp__tot--due"><span>{{ $order->payment_status==='paid' ? __('Paid') : __('Due now') }}</span><span>{{ '$'.number_format($order->payment_status==='paid' ? $order->total : $balance,2) }}</span></div>
        @php
          $shipCity = $order->ship_to_different ? $order->shipping_city : $order->billing_city;
          $shipState = $order->ship_to_different ? $order->shipping_state : $order->billing_state;
          $dest = trim($shipCity.($shipCity && $shipState ? ', ' : '').$shipState);
        @endphp
        <p class="payp__addr">@if($order->shipping_method===\App\Services\CartService::PICKUP_ID)<x-icon name="pin"/> Local pickup — Florida @elseif($dest)<x-icon name="truck"/> Ships to {{ $dest }}@endif<a href="{{ $statusUrl }}">{!! __('Order status page') !!} <x-icon name="arrow-right"/></a></p>
      </div>
      <p class="payp__help">{!! __('Questions about this order?') !!} <a href="tel:+18135246491">(813) 524-6491</a> · <a href="mailto:{{ config('uds.email') }}">{{ config('uds.email') }}</a></p>
    </aside>
  </div>
</div></section>
@if($payable && !$stripeKey)
<script>document.querySelector('[data-copy-ref]')&&document.querySelector('[data-copy-ref]').addEventListener('click',function(){var b=this,t=document.getElementById('pay-ref').textContent;(navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){b.textContent='Copied';setTimeout(function(){b.textContent='Copy'},1600)}).catch(function(){});});</script>
@endif
@if($payable && $stripeKey)
<script src="https://js.stripe.com/v3/"></script>
<script>
(function(){
  var btn=document.getElementById('pay-btn'),err=document.getElementById('pay-err'),base=location.pathname;
  var stripe=Stripe(@json($stripeKey)),elements,secret;
  function fail(m){err.textContent=m||'Payment could not be started. Please call (813) 524-6491.';btn.disabled=false;}
  fetch(base+'/intent',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}}).then(function(r){return r.json();}).then(function(d){
    if(d.paid&&d.redirect){location.href=d.redirect;return;}
    if(!d.client_secret){fail(d.error);return;}
    secret=d.client_secret;elements=stripe.elements({clientSecret:secret,appearance:{theme:'stripe',variables:{colorPrimary:'#1246ab',borderRadius:'10px'}}});
    elements.create('payment').mount('#pay-el');
  }).catch(function(){fail();});
  btn.addEventListener('click',function(){
    if(!elements)return;btn.disabled=true;err.textContent='';
    stripe.confirmPayment({elements:elements,redirect:'if_required',confirmParams:{return_url:location.href,payment_method_data:{billing_details:{name:document.getElementById('pay-name').value,email:@json($order->email)}}}}).then(function(res){
      if(res.error){fail(res.error.message);return;}
      fetch(base+'/confirm',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({payment_intent:res.paymentIntent&&res.paymentIntent.id})}).then(function(r){return r.json();}).then(function(d){location.href=d.redirect||(@json($statusUrl)+'?paid=1');}).catch(function(){location.href=@json($statusUrl);});
    });
  });
})();
</script>
@endif
@endsection
