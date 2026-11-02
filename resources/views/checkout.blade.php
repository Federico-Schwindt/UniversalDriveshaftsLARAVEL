@extends('layouts.app')
@section('robots','noindex,follow')
@section('title','Secure checkout | Universal Driveshafts')
@section('desc','Secure checkout, Universal Driveshafts.')
@section('content')
<div class="ck">
{{-- ================= HERO / PROGRESS ================= --}}
<section class="ck-hero"><div class="wrap">
  <div>
    <p class="ck-eyebrow">{!! __('Universal Driveshafts') !!}</p>
    <h1>{!! __('Secure checkout') !!}</h1>
    <div class="ck-trust">
      <span><svg viewBox="0 0 24 24"><path d="M12 1l9 4v6c0 5.5-3.8 10.7-9 12-5.2-1.3-9-6.5-9-12V5l9-4zm-1 14.6l6-6-1.4-1.4L11 12.8 8.4 10.2 7 11.6l4 4z"/></svg>{!! __('256-bit SSL encrypted') !!}</span>
      <span><svg viewBox="0 0 24 24"><path d="M3 7l9-4 9 4v10l-9 4-9-4V7zm9 4l7-3.1L12 5 5 7.9 12 11z"/></svg>{!! __('Ships from Florida') !!}</span>
      <span><svg viewBox="0 0 24 24"><path d="M20 8h-3V4H3v13h2a3 3 0 006 0h4a3 3 0 006 0h2v-6l-3-3zM6.5 18a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm11 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3zM17 12V9.5h2.5L21 12h-4z"/></svg>{{ __('Free U.S. ground on parts over :amount', ['amount' => '$'.number_format($freeMin)]) }}</span>
    </div>
  </div>
  <div class="ck-steps">
    <a class="st done" href="/cart" style="text-decoration:none"><i><x-icon name="check"/></i><span>{!! __('Cart') !!}</span></a><div class="ln done"></div>
    <div class="st cur"><i>2</i><span>{!! __('Checkout') !!}</span></div><div class="ln"></div>
    <div class="st"><i>3</i><span>{!! __('Confirmation') !!}</span></div>
  </div>
</div></section>

<section class="ck-body"><div class="wrap">
  <div class="co-errors" id="co-errors" role="alert" tabindex="-1"><ul></ul></div>

  <form class="co-form" id="co-form" onsubmit="return false">
  <div class="ck-layout">

  {{-- ================= LEFT: STEP CARDS ================= --}}
  <div class="ck-main">
    @include('partials.ship-countdown', ['class' => 'shipcd--ck'])
    @if($stripeKey && !$freightCart)
    {{-- Apple Pay / Google Pay: the wallet supplies contact, address and payment, so none of the form below is needed --}}
    <div class="co-express" id="co-express" hidden>
      <div class="co-express__h"><b>{!! __('Express checkout') !!}</b><span>{!! __('Pay with your wallet, no forms to fill.') !!}</span></div>
      <div id="express-btns"></div>
      <p class="co-express__terms">{!! __('By paying with your wallet you agree to the') !!} <a href="/terms" target="_blank" rel="noopener">{!! __('Terms of Use &amp; Conditions of Sale') !!}</a> {!! __('and the') !!} <a href="/return-policy" target="_blank" rel="noopener">{!! __('Return Policy') !!}</a>.</p>
      <div class="co-coupon-err" id="express-err"></div>
      <div class="co-express__or"><span>{!! __('or fill in your details') !!}</span></div>
    </div>
    @endif

    {{-- 01 Billing --}}
    <div class="cocard">
      <h3><span class="no">01</span><span class="ttl">{!! __('Billing details') !!}<small>{!! __('Who is placing this order') !!}</small></span></h3>
      <div class="cob">
        <div class="cogrid" data-geo-noip>
          <div><label for="co-email">{!! __('Email address *') !!}</label><input id="co-email" type="email" name="email" required autocomplete="email" value="{{ $authUser->email ?? '' }}" placeholder="{!! __('you@email.com') !!}"></div>
          <div><label for="co-phone">{!! __('Phone / mobile *') !!}</label><input id="co-phone" type="tel" name="phone" required autocomplete="tel" value="{{ $authUser->phone ?? '' }}" placeholder="{!! __('Mobile number') !!}">
            <label class="co-agree" style="display:flex;align-items:flex-start;gap:.5rem;margin:.55rem 0 0;font-weight:400;font-size:.82rem;text-transform:none;letter-spacing:0"><input type="checkbox" name="sms_opt_in" value="1" id="sms-opt-in"><span>{!! __('Text me order updates (shipping and delivery). Msg &amp; data rates may apply.') !!}</span></label></div>
          @php
            // saved on the account (Addresses); falls back to the default shipping address
            $bill = ['a1' => '', 'a2' => '', 'city' => '', 'state' => '', 'zip' => '', 'cc' => 'US', 'co' => $authUser->company ?? ''];
            if ($authUser && filled($authUser->billing_address_1)) {
                $bill = ['a1' => $authUser->billing_address_1, 'a2' => $authUser->billing_address_2, 'city' => $authUser->billing_city, 'state' => $authUser->billing_state, 'zip' => $authUser->billing_postcode, 'cc' => $authUser->billing_country ?: 'US', 'co' => $authUser->company];
            } elseif ($authUser && ($def = $dropship->firstWhere('is_default', true))) {
                $bill = ['a1' => $def->street, 'a2' => $def->unit, 'city' => $def->city, 'state' => $def->state, 'zip' => $def->postcode, 'cc' => $def->country ?: 'US', 'co' => $authUser->company ?: $def->company];
            }
          @endphp
          <div><label for="co-bfn">{!! __('First name *') !!}</label><input id="co-bfn" type="text" name="billing_first_name" required autocomplete="billing given-name" value="{{ $authUser ? explode(' ', $authUser->name)[0] : '' }}"></div>
          <div><label for="co-bln">{!! __('Last name *') !!}</label><input id="co-bln" type="text" name="billing_last_name" required autocomplete="billing family-name" value="{{ $authUser ? trim(implode(' ', array_slice(explode(' ', $authUser->name),1))) : '' }}"></div>
          <div class="full"><label for="co-bco">{!! __('Company (optional)') !!}</label><input id="co-bco" type="text" name="billing_company" autocomplete="billing organization" value="{{ $bill['co'] }}"></div>
          <div class="full"><label for="co-ba1">{!! __('Street address *') !!}</label><input id="co-ba1" type="text" name="billing_address_1" required data-addr-req value="{{ $bill['a1'] }}" data-gplaces data-addr-group="billing" autocomplete="billing address-line1" placeholder="{!! __('Start typing your address…') !!}"></div>
          <div class="full"><label for="co-ba2">{!! __('Suite / unit (optional)') !!}</label><input id="co-ba2" type="text" name="billing_address_2" autocomplete="billing address-line2" value="{{ $bill['a2'] }}"></div>
          <div><label for="co-bcity">{!! __('City *') !!}</label><input id="co-bcity" type="text" name="billing_city" required data-addr-req autocomplete="billing address-level2" value="{{ $bill['city'] }}" data-geo="city" data-addr-group="billing"></div>
          <div><label for="billing_state">{!! __('State') !!}</label><input type="text" name="billing_state" value="{{ $bill['state'] }}" id="billing_state" autocomplete="billing address-level1" placeholder="{!! __('e.g. FL') !!}" data-geo="state" data-addr-group="billing"></div>
          <div><label for="co-bzip">{!! __('ZIP code *') !!}</label><input id="co-bzip" type="text" name="billing_postcode" required data-addr-req autocomplete="billing postal-code" value="{{ $bill['zip'] }}" data-geo="zip" data-addr-group="billing"></div>
          <div><label for="billing_country">{!! __('Country *') !!}</label>
            <select name="billing_country" id="billing_country" autocomplete="billing country" data-geo="country" data-addr-group="billing">
              @foreach(config('countries') as $cc => $cn)<option value="{{ $cc }}" @selected($cc===$bill['cc'])>{{ $cn }}</option>@endforeach
            </select></div>
        </div>
      </div>
    </div>

    {{-- 02 Ship To --}}
    <div class="cocard">
      <h3><span class="no">02</span><span class="ttl">{!! __('Shipping address') !!}<small>{!! __('Where the parts are going') !!}</small></span></h3>
      <div class="cob">
        <div style="display:flex;justify-content:flex-end;margin:0 0 12px"><button type="button" class="co-btn-sm" id="dropship-btn" aria-controls="ship-sec">{!! __('Ship to a different address') !!}</button></div>
        <input type="checkbox" name="ship_to_different" id="ship-diff" value="1" hidden>
        <div id="ship-same" class="co-static"><div><b>{!! __('Same as billing address') !!}</b><span class="muted">{!! __('Your order ships to the billing address above. Pick a saved address below or click') !!} <b>{!! __('Ship to a different address') !!}</b></span></div></div>
        @if(count($dropship ?? []))
        <div id="ship-saved" class="ship-saved">
          @php $manage = true; @endphp
          @foreach([['shipping', __('My addresses')], ['dropship', __('Dropship · your customers')]] as [$k, $lab])
            @php $group = $dropship->filter(fn ($d) => ($d->kind ?? 'shipping') === $k); @endphp
            @continue($group->isEmpty())
            <div class="ss-lab">{{ $lab }}@if($manage) <a href="/account/addresses" style="font-weight:600;margin-left:6px">{!! __('Manage') !!}</a>@php $manage = false; @endphp @endif</div>
            <div class="ss-list">
            @foreach($group as $d)
              <button type="button" class="ss-item" data-ds="{{ json_encode(['name' => $d->name, 'company' => $d->company, 'street' => $d->street, 'unit' => $d->unit, 'city' => $d->city, 'state' => $d->state, 'postcode' => $d->postcode, 'country' => $d->country]) }}" data-kind="{{ $k }}">
                <b>{{ $d->label ?: ($d->company ?: $d->name) }}@if($d->is_default ?? false) <small style="font-weight:600;color:var(--blue)">· {{ __('Default') }}</small>@endif</b>
                <span>{{ $d->street }}@if($d->unit), {{ $d->unit }}@endif · {{ $d->city }}, {{ $d->state }} {{ $d->postcode }}@if($d->phone) · {{ $d->phone }}@endif</span>
              </button>
            @endforeach
            </div>
          @endforeach
        </div>
        @endif
        <div id="ship-sec" class="cogrid" data-geo-noip style="display:none;margin-top:16px">
          <div><label for="co-sfn">{!! __('First name') !!}</label><input id="co-sfn" type="text" name="shipping_first_name" autocomplete="shipping given-name"></div>
          <div><label for="co-sln">{!! __('Last name') !!}</label><input id="co-sln" type="text" name="shipping_last_name" autocomplete="shipping family-name"></div>
          <div class="full"><label for="co-sco">{!! __('Company / name') !!}</label><input id="co-sco" type="text" name="shipping_company" autocomplete="shipping organization"></div>
          <div class="full"><label for="co-sa1">{!! __('Street address') !!}</label><input id="co-sa1" type="text" name="shipping_address_1" data-gplaces data-addr-group="shipping" autocomplete="shipping address-line1" placeholder="{!! __('Start typing your address…') !!}"></div>
          <div class="full"><label for="co-sa2">{!! __('Suite / unit') !!}</label><input id="co-sa2" type="text" name="shipping_address_2" autocomplete="shipping address-line2"></div>
          <div><label for="co-scity">{!! __('City') !!}</label><input id="co-scity" type="text" name="shipping_city" data-geo="city" data-addr-group="shipping" autocomplete="shipping address-level2"></div>
          <div><label for="shipping_state">{!! __('State') !!}</label><input type="text" name="shipping_state" id="shipping_state" placeholder="{!! __('e.g. TX') !!}" data-geo="state" data-addr-group="shipping" autocomplete="shipping address-level1"></div>
          <div><label for="co-szip">{!! __('ZIP code') !!}</label><input id="co-szip" type="text" name="shipping_postcode" data-geo="zip" data-addr-group="shipping" autocomplete="shipping postal-code"></div>
          <div><label for="shipping_country">{!! __('Country') !!}</label><select name="shipping_country" id="shipping_country" autocomplete="shipping country" data-geo="country" data-addr-group="shipping">@foreach(config('countries') as $cc => $cn)<option value="{{ $cc }}" @selected($cc==='US')>{{ $cn }}</option>@endforeach</select></div>
          <div class="full"><button type="button" class="btn btn-ghost btn-sm" id="dropship-cancel">{!! __('Cancel, ship to billing') !!}</button></div>
        </div>
      </div>
    </div>

    {{-- 03 Delivery --}}
    <div class="cocard">
      <h3><span class="no">03</span><span class="ttl">{!! __('Delivery method') !!}<small>{!! __('Live UPS and FedEx rates for your address') !!}</small></span></h3>
      <div class="cob">
        <label for="ship-select">{!! __('Shipping method, estimated delivery, price *') !!}</label>
        <select id="ship-select" name="shipping_method" aria-describedby="ship-note ship-msg"><option value="">{!! __('Enter your address to load rates…') !!}</option></select>
        <div class="co-coupon-err" id="ship-msg" aria-live="polite"></div>
        <div id="ship-note">{!! __('Rates load once your ZIP is entered above.') !!} <button type="button" class="ship-why" data-esm-open>{!! __('How freight is estimated') !!}</button></div>
        <div id="ship-ships" class="co-ships" hidden></div>

        {{-- shown only when the order ships on a pallet: a truck delivers it, and how matters to the price --}}
        <fieldset id="freight-box" class="co-freight" hidden>
          <legend>{!! __('This order ships by truck freight on a pallet. Where is it going?') !!}</legend>
          <label class="co-freight__opt"><input type="radio" name="freight_delivery" value="dock"><span><b>{!! __('Business with a loading dock or forklift') !!}</b><small>{!! __('Lowest freight price') !!}</small></span></label>
          <label class="co-freight__opt"><input type="radio" name="freight_delivery" value="liftgate"><span><b>{!! __('Business without a dock') !!}</b><small>{!! __('The truck brings a liftgate to lower the pallet') !!}</small></span></label>
          <label class="co-freight__opt"><input type="radio" name="freight_delivery" value="residential" checked><span><b>{!! __('Home or residential address') !!}</b><small>{!! __('Residential delivery with a liftgate') !!}</small></span></label>
          <p class="co-freight__note">{!! __('Someone needs to be there to sign for it. The carrier calls ahead to set a delivery window.') !!}</p>
        </fieldset>
        <p id="intl-note" class="co-intl" hidden>{!! __('International orders ship DAP:') !!} <b>{!! __('import duties, taxes and customs fees are paid by the recipient') !!}</b> {!! __('on delivery, and the carrier may contact you to collect them.') !!}</p>

        <div id="pickup-box" class="co-pickup" hidden>
          <div class="co-pickup__head"><x-icon name="factory"/><b>{!! __('You chose local pickup. Nothing will be shipped.') !!}</b></div>
          {{-- the shop's address is private: it is fetched only once the customer picks local pickup (owner, 2026-10-24) --}}
          <p>{!! __('Pick up at') !!} <b id="pickup-addr">…</b> (<a id="pickup-map" href="#" target="_blank" rel="noopener nofollow">{!! __('map') !!}</a>).<br>
             {!! __('Pickup hours:') !!} <b>{{ config('uds.pickup.hours') }}</b>{!! __('. Need a different time? Call') !!} <a href="{{ config('uds.pickup.phone_href') }}">{{ config('uds.pickup.phone') }}</a> {!! __('first so we can confirm and have your parts ready.') !!}</p>
          <p id="pickup-far" class="co-pickup__far" hidden><x-icon name="warning"/> <span>{!! __('Your address is in') !!} <b id="pickup-far-state"></b>{!! __('. Only choose pickup if you will come to Florida in person. If you need these parts delivered, pick a shipping method above instead.') !!}</span></p>
          <label class="co-agree co-pickup__ack"><input type="checkbox" name="pickup_ack" value="1" id="pickup-ack" aria-describedby="pickup-msg"><span>{!! __('I understand I am picking this order up in person at our shop, and that it will not be shipped to any address.') !!}</span></label>
          <div class="co-coupon-err" id="pickup-msg" aria-live="polite"></div>
        </div>

        <div class="ship-meta">
          <div class="m"><i><x-icon name="factory"/></i><div><label id="meta-from-l">{!! __('Shipping From') !!}</label><b>{!! __('Florida') !!}</b><span class="muted">{!! __('') !!}</span></div></div>
          <div class="m"><i><x-icon name="package"/></i><div><label id="meta-date-l">{!! __('Est. Ship Date') !!}</label><b id="meta-date-v">{{ \App\Services\CartService::shipDate()->format('D M j') }}</b><span class="muted" id="meta-date-s">{{ __('Orders before :time ET ship same day', ['time' => \App\Services\CartService::cutoffLabel()]) }}</span></div></div>
        </div>
      </div>
    </div>

    {{-- 04 Payment --}}
    <div class="cocard">
      <h3><span class="no">04</span><span class="ttl">{!! __('Payment') !!}<small>{!! __('Encrypted and processed by Stripe') !!}</small></span></h3>
      <div class="cob">
        @if($b2b)
        <div class="co-paywith" role="radiogroup" aria-label="{{ __('How do you want to pay?') }}">
          <label class="co-paywith__opt"><input type="radio" name="payment_method" value="card" checked><span><b>{!! __('Card, Apple Pay, Google Pay or Affirm') !!}</b><small>{!! __('Pay now') !!}</small></span></label>
          <label class="co-paywith__opt @if(!$b2b['terms']) is-off @endif"><input type="radio" name="payment_method" value="net_terms" @disabled(!$b2b['terms'])><span><b>{{ __('Invoice, Net :days', ['days' => $b2b['days']]) }}</b>
            <small>@if($b2b['terms']){{ __('Due :date · :amount of credit available', ['date' => $b2b['due']->format('M j, Y'), 'amount' => '$' . number_format($b2b['available'], 2)]) }}@elseif($b2b['termsWhy'] === 'overdue'){{ __('Paused: an invoice is past due.') }} <a href="/account/business">{{ __('Pay it') }}</a>@elseif($b2b['termsWhy'] === 'limit'){{ __('Credit limit reached.') }} <a href="/account/business">{{ __('See invoices') }}</a>@else{{ __('Not turned on for this account. Call (813) 524-6491.') }}@endif</small></span></label>
        </div>
        <div class="cogrid" style="margin-bottom:14px"><div class="full"><label for="po-number">{!! __('PO number') !!} <small>{!! __('(printed on the invoice)') !!}</small></label><input id="po-number" type="text" name="po_number" maxlength="80" placeholder="{!! __('Your purchase order / reference') !!}"></div></div>
        <p class="info-note" id="terms-note" hidden><x-icon name="info"/> {!! __('We start on your order right away and e-mail the invoice with a link to pay by card. Check or ACH also work: reply to the invoice e-mail for details.') !!}</p>
        @else
        <input type="hidden" name="payment_method" value="card">
        @endif
        <div id="pay-card-box">
        @if($stripeKey)
          @if(!empty($savedCards))
          <div class="co-saved" role="radiogroup" aria-label="{{ __('Saved cards') }}">
            @foreach($savedCards as $i => $c)
              <label class="co-saved__opt"><input type="radio" name="saved_card" value="{{ $c['id'] }}" @checked($i === 0)><x-icon name="card"/><span><b>{{ $c['brand'] }} •••• {{ $c['last4'] }}</b><small>{{ __('Exp. :date', ['date' => $c['exp']]) }}</small></span></label>
            @endforeach
            <label class="co-saved__opt"><input type="radio" name="saved_card" value=""><x-icon name="plus"/><span><b>{!! __('Use another payment method') !!}</b><small>{!! __('Card, Apple Pay, Google Pay or Affirm') !!}</small></span></label>
          </div>
          @endif
          <div id="new-pay-box" @if(!empty($savedCards)) hidden @endif>
          <label id="stripe-card-l">{!! __('Payment details') !!}</label>
          <div id="stripe-card" role="group" aria-labelledby="stripe-card-l"></div>
          @auth
          <label class="co-savecard"><input type="checkbox" name="dont_save_card" value="1" id="dont-save-card"><span>{!! __("Don't save my card for future purchases") !!} <small>{!! __('Signed-in cards are saved securely by Stripe for faster checkout. Remove them any time in your account.') !!}</small></span></label>
          @endauth
          </div>
          <div class="co-coupon-err" id="stripe-card-err" role="alert"></div>
          <div id="affirm-msg" class="co-affirm"></div>
          <p class="info-note" id="affirm-note" hidden style="margin-top:10px"><x-icon name="info"/> {!! __('Pay over time with') !!} <b>{!! __('Affirm') !!}</b> {!! __('is offered on orders of') !!} <b>{!! __('$50 or more') !!}</b> {!! __('— it will appear here once your total reaches that.') !!}</p>
          <script src="https://js.stripe.com/v3/"></script>
          <script>
            (function(){
              /* Payment Element in deferred-intent mode: the order (and its PaymentIntent) is only
                 created once Place order is pressed, but the card / Apple Pay / Google Pay / Affirm
                 choices have to be on screen before that. */
              var stripe=Stripe(@json($stripeKey)), errEl=document.getElementById('stripe-card-err');
              var elements=stripe.elements({
                mode:'payment', currency:'usd', amount:Math.max(50,Math.round(window.UDS_TOTAL_CENTS||5000)),
                @if(config('services.stripe.pm_config'))
                paymentMethodConfiguration:@json(config('services.stripe.pm_config')),
                @else
                paymentMethodTypes:@json(\App\Support\PayLink::methodTypes((float) $subtotal, 'US')),
                @endif
                @auth
                paymentMethodOptions:{card:{setup_future_usage:'off_session'}},
                @endauth
                appearance:{theme:'stripe',variables:{colorPrimary:'#1246ab',borderRadius:'10px',fontSizeBase:'16px'}}
              });
              var pe=elements.create('payment',{layout:'tabs'});
              pe.mount('#stripe-card');
              window.udsStripe={stripe:stripe,elements:elements};
              /* "as low as $X/mo with Affirm", moving with the total */
              var pmm=null;
              try{ pmm=stripe.elements().create('paymentMethodMessaging',{amount:Math.max(5000,Math.round(window.UDS_TOTAL_CENTS||5000)),currency:'USD',paymentMethodTypes:['affirm'],countryCode:'US'}); pmm.mount('#affirm-msg'); }catch(e){}
              pe.on('change',function(e){errEl.textContent=e.error?e.error.message:'';});

              /* the total moves as shipping is chosen — Affirm's eligibility moves with it */
              window.udsStripeAmount=function(cents){
                try{ elements.update({amount:Math.max(50,Math.round(cents))}); }catch(e){}
                try{ if(pmm) pmm.update({amount:Math.max(5000,Math.round(cents))}); }catch(e){}
                var n=document.getElementById('affirm-note'); if(n) n.hidden=cents>=5000;
              };
              /* deferred mode validates the element before the server makes the intent */
              window.udsStripeValidate=function(){ return elements.submit(); };

              /* saved card vs a new payment method (new cards are kept for later unless "Don't save my card" is ticked) */
              var savedPick=function(){ var r=document.querySelector('[name=saved_card]:checked'); return r?r.value:''; };
              window.udsSavedCard=savedPick;
              document.querySelectorAll('[name=saved_card]').forEach(function(r){ r.addEventListener('change',function(){
                var nb=document.getElementById('new-pay-box'); if(nb) nb.hidden=!!savedPick();
                document.querySelectorAll('.co-saved__opt').forEach(function(o){o.classList.toggle('on',o.querySelector('input').checked);});
              }); });
              document.querySelectorAll('.co-saved__opt input:checked').forEach(function(i){i.closest('.co-saved__opt').classList.add('on');});

              window.udsStripeConfirm=function(cs,redir,btn){
                if(savedPick()){
                  /* the intent already carries the saved card; the bank may still ask for 3-D Secure */
                  stripe.confirmCardPayment(cs,{payment_method:savedPick()}).then(function(res){
                    if(res.error){ errEl.textContent=res.error.message; if(window.udsPlaceFailed)window.udsPlaceFailed(res.error); else btn.disabled=false; return; }
                    location.href=redir;
                  });
                  return;
                }
                var name=(document.querySelector('[name=billing_first_name]').value+' '+document.querySelector('[name=billing_last_name]').value).trim();
                stripe.confirmPayment({
                  elements:elements, clientSecret:cs, redirect:'if_required',
                  confirmParams:{
                    return_url:location.origin+redir,
                    payment_method_data:{billing_details:{name:name,email:document.querySelector('[name=email]').value}}
                  }
                }).then(function(res){
                  if(res.error){
                    errEl.textContent=res.error.message;
                    document.getElementById('stripe-card').scrollIntoView({block:'center',behavior:'smooth'});
                    if(window.udsPlaceFailed)window.udsPlaceFailed(res.error); else btn.disabled=false;
                    return;
                  }
                  location.href=redir;
                });
              };
            })();
          </script>
        @else
          <p class="info-note"><x-icon name="lock"/> <b>{!! __('Secure card payment') !!}</b> {!! __('is being activated on the new store. Place your order now and we\'ll email you a secure payment link, or call') !!} <a href="tel:+18135246491" style="color:var(--blue);font-weight:700">(813) 524-6491</a>.</p>
        @endif
        </div>{{-- /pay-card-box --}}
        <div class="pay-brands"><span>VISA</span><span>MASTERCARD</span><span>AMEX</span><span>DISCOVER</span><span>{!! __('APPLE PAY') !!}</span><span>{!! __('GOOGLE PAY') !!}</span><span>AFFIRM</span></div>
        <div class="pay-secure"><svg viewBox="0 0 24 24"><path d="M12 1l9 4v6c0 5.5-3.8 10.7-9 12-5.2-1.3-9-6.5-9-12V5l9-4zm-1 14.6l6-6-1.4-1.4L11 12.8 8.4 10.2 7 11.6l4 4z"/></svg>{!! __('Your card details never touch our servers, PCI-DSS compliant tokenization.') !!}</div>
      </div>
    </div>

    {{-- 05 Order notes --}}
    <div class="cocard">
      <h3><span class="no">05</span><span class="ttl">{!! __('Order notes') !!}<small>{!! __('Optional: signature, delivery instructions') !!}</small></span></h3>
      <div class="cob">
        <div class="cogrid">

          <div><label for="co-sig">{!! __('Signature required?') !!}</label>
            <select id="co-sig" name="signature_required"><option value="0" selected>{!! __('No') !!}</option><option value="1">{!! __('Yes') !!}</option></select></div>
          <div class="full"><label for="co-instr">{!! __('Delivery instructions') !!}</label><textarea id="co-instr" name="ship_instructions" rows="2" placeholder="{!! __('Notes for delivery, dock #, gate code…') !!}"></textarea></div>
        </div>
      </div>
    </div>

    {{-- Important information --}}
    <details class="ck-info">
      <summary><x-icon name="info"/> {!! __('&nbsp;Important information about freight &amp; shipping') !!}</summary>
      <div class="ib">
        <p class="info-note">{!! __('* Freight is') !!} <b>{!! __('estimated') !!}</b> {!! __('from the weight and box size of the items in your cart. If actual weight or box length differs, or the order needs multiple packages, charges are subject to change, we\'ll confirm before charging.') !!}</p>
        <p class="info-note">{!! __('** All orders ship from our') !!} <b>{!! __('Florida') !!}</b> {!! __('facility. We select the carrier for pre-paid shipments unless you instruct otherwise.') !!}</p>
        <p class="info-note">{!! __('*** A surcharge applies for') !!} <b>{!! __('Signature Required') !!}</b> {!! __('based on the shipping method. Free U.S. ground shipping on parts orders over') !!} <b>${{ number_format($freeMin) }}</b>{!! __('. Custom cut tubing and complete driveshafts do not count toward that total and always ship at live UPS rates, one parcel each.') !!}</p>
      </div>
    </details>
  </div>

  {{-- ================= RIGHT: ORDER SUMMARY ================= --}}
  <aside class="ck-side" id="summary">
    <div class="sum">
      <div class="sum-h"><h2>{!! __('Order summary') !!}</h2><span class="cnt">{{ trans_choice(':count item|:count items', collect($items)->sum('qty')) }}</span></div>

      <div class="sum-items">
      @foreach($items as $i)
        <div class="sum-it">
          <div class="th">
            @if(!empty($i['image']))<img src="{{ $i['image'] }}" alt="" loading="lazy">
            @else<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#1246ab" stroke-width="1.8"><path d="M3 12h3l2-3 3 6 3-9 2 6h5"/></svg>@endif
            <span class="q">{{ $i['qty'] }}</span>
          </div>
          @php
            // product names are stored as "SKU Description"; when the cart line
            // carries no sku, split it off the name instead of printing a dash.
            $ckSku = $i['sku'] ?: strtok($i['name'], ' ');
            $ckDesc = $i['sku'] ? $i['name'] : trim(substr($i['name'], strlen($ckSku)));
          @endphp
          <div class="nm"><b>{{ $ckSku }}</b><span>{{ \Illuminate\Support\Str::limit($ckDesc, 60) }}</span></div>
          <div class="pr">${{ number_format($i['price']*$i['qty'],2) }}@if($i['qty']>1)<small>${{ number_format($i['price'],2) }} ea.</small>@endif</div>
          @if(!empty($i['meta']['config']))
            <div class="sum-it__cfg">@include('partials.build-config', ['config' => $i['meta']['config'], 'compact' => true])</div>
          @endif
        </div>
      @endforeach
      </div>

      {{-- coupon --}}
      <div class="co-coupon2" id="coupon-box">
        <div class="co-coupon-in" id="coupon-in" @if($coupon) style="display:none" @endif>
          <input type="text" id="coupon-code" placeholder="{!! __('Coupon or promo code') !!}" aria-label="{{ __('Coupon or promo code') }}">
          <button type="button" id="coupon-apply" class="co-btn-sm">{!! __('Apply') !!}</button>
        </div>
        <div class="co-coupon-on on" id="coupon-on" @if(!$coupon) style="display:none" @endif>
          <x-icon name="certificate"/> <b id="coupon-label">{{ $coupon['code'] ?? '' }}</b> {!! __('applied') !!} <button type="button" id="coupon-remove">{!! __('Remove') !!}</button>
        </div>
        <span class="co-coupon-err" id="coupon-err"></span>
      </div>

      {{-- totals --}}
      <div class="sum-tot"><table>
        <tr><td>{!! __('Subtotal') !!}</td><td class="r">${{ number_format($subtotal,2) }}</td></tr>
        <tr id="disc-row" @if(!$coupon && !$b2b) hidden @endif><td>{!! __('Discount') !!} <b id="disc-code">{{ $coupon['code'] ?? ($b2b ? __(':tier account', ['tier' => $b2b['label']]) : '') }}</b></td><td class="r">-$<span id="disc-amt">{{ number_format(max($coupon['discount'] ?? 0, $b2b['amount'] ?? 0),2) }}</span></td></tr>
        <tr><td>{!! __('Est. Shipping') !!}</td><td class="r" id="sum-ship">TBD</td></tr>
        <tr class="grand"><td>{!! __('Total') !!}</td><td class="r" id="sum-total">${{ number_format($subtotal,2) }}</td></tr>
      </table></div>

      <div class="sum-free">
        <div class="freeship" id="freeship-bar"></div>
        <div class="free-meter"><i style="--p:{{ min(100, $freeMin>0 ? round(($freeBase ?? $subtotal)/$freeMin*100) : 100) }}%"></i></div>
      </div>

      <div class="co-foot">
        <label class="co-agree"><input type="checkbox" name="agree_terms" value="1" id="agree-terms" aria-describedby="terms-msg"><span>{!! __('I have read and agree to the') !!} <a href="/terms" target="_blank" rel="noopener">{!! __('Terms of Use &amp; Conditions of Sale') !!}</a>{!! __(', the') !!} <a href="/return-policy" target="_blank" rel="noopener">{!! __('Return &amp; Limited Warranty Policy') !!}</a> {!! __('and the') !!} <a href="/privacy-policy" target="_blank" rel="noopener">{!! __('Privacy Policy') !!}</a>{!! __('. I am responsible for verifying fitment and for any measurements I supplied; custom builds are non-returnable.') !!}</span></label>
        <div class="co-coupon-err" id="terms-msg" aria-live="polite"></div>
        <button type="button" class="co-place" id="place-btn">{!! __('Place order&nbsp;&nbsp;') !!}<span id="place-total">${{ number_format($subtotal,2) }}</span></button>
        <a href="/cart" class="co-edit"><x-icon name="arrow-left"/> {!! __('Edit cart') !!}</a>
      </div>
      <div class="sum-trust">
        <span><svg viewBox="0 0 24 24"><path d="M12 1l9 4v6c0 5.5-3.8 10.7-9 12-5.2-1.3-9-6.5-9-12V5l9-4z"/></svg>{!! __('Secure checkout') !!}</span>
        <span><svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20 10 10 0 000-20zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>{!! __('Fitment support (813) 524-6491') !!}</span>
        <span><svg viewBox="0 0 24 24"><path d="M3 7l9-4 9 4v10l-9 4-9-4V7z"/></svg>{!! __('Built in the USA') !!}</span>
      </div>
    </div>
  </aside>

  </div>{{-- /ck-layout --}}
  </form>
</div></section>

{{-- mobile sticky total bar --}}
<div class="ck-mbar"><div><small>{!! __('Order total') !!}</small><b id="mbar-total">${{ number_format($subtotal,2) }}</b></div><a href="#summary">{!! __('Review and place order') !!}</a></div>
</div>{{-- /ck --}}

{{-- estimated shipping modal --}}
<div class="esm-back" id="esm-back"><div class="esm">
  <h2>{!! __('About estimated shipping') !!}</h2><hr>
  <p><b>{!! __('PLEASE BE AWARE:') !!}</b></p>
  <p>{!! __('Freight is estimated from the weight and box size of the items in your cart. If the actual weight, or length of box, is different, or if the order requires multiple packages, the freight charges are subject to change.') !!}</p>
  <p>{!! __('Due to inventory or other issues, your order may occasionally ship from a different location to ensure it\'s processed and delivered on time.') !!}</p>
  <button class="ok" id="esm-ok">{!! __('Got it') !!}</button>
</div></div>

<script>
(function(){
  const CSRF=document.querySelector('meta[name="csrf-token"]').content;
  let SUBTOTAL={{ $subtotal }};   /* replaced by the server's figure if the cart changed in another tab (409 total_changed) */
  const FREEMIN={{ $freeMin }}, FREEBASE={{ $freeBase ?? $subtotal }};
  const form=document.getElementById('co-form');
  let shipCost=0, shipId='', coupon={!! $coupon ? json_encode($coupon) : 'null' !!};
  /* the server's own totals after it refused a changed total; dropped as soon as shipping or the coupon changes */
  let override=null;
  /* a placed card order whose payment failed (declined, 3-D Secure closed): the next click confirms the same
     PaymentIntent again instead of placing a second order — while the total and the way of paying are unchanged */
  let retry=null;
  const M={!! json_encode([
    'place' => __('Place order&nbsp;&nbsp;'), 'placing' => __('Placing order…'), 'ship' => __('Please choose a shipping method.'),
    'pickup' => __('Please confirm you will pick this order up in person at our shop.'), 'terms' => __('Please read and accept the Terms of Use to place your order.'),
    'net' => __('Network error. Please try again.'), 'error' => __('Something went wrong. Please try again or call (813) 524-6491.'), 'free' => __('Free'),
  ], JSON_UNESCAPED_UNICODE) !!};
  /* business accounts: tier discount (does not stack with a coupon's money-off: the larger applies) */
  const TIER={!! $b2b ? json_encode(['label' => __(':tier account', ['tier' => $b2b['label']]), 'amount' => $b2b['amount']]) : 'null' !!};
  function discount(){
    var c=coupon?Number(coupon.discount||0):0, t=TIER?Number(TIER.amount):0;
    return t>c?{amt:t,label:TIER.label}:{amt:c,label:coupon?coupon.code:''};
  }
  const PICKUP='local_pickup', PICKUP_LABEL=@json(config('uds.pickup.label')), PICKUP_HOURS=@json(config('uds.pickup.hours')), PICKUP_PHONE=@json(config('uds.pickup.phone'));
  const SHIP_DATE=document.getElementById('meta-date-v').textContent;

  function money(n){return '$'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function freeBar(){
    var bar=document.getElementById('freeship-bar');
    var rem=FREEMIN-FREEBASE, tube=SUBTOTAL-FREEBASE>0.009;
    if(tube){bar.className='freeship';bar.textContent=udsT('Tubing and driveshafts ship at the carrier rate and do not qualify for free shipping.');}
    else if(rem<=0){bar.className='freeship done';bar.textContent=udsT('Free US ground shipping applied.');}
    else{bar.className='freeship';bar.textContent=udsT('Only :amount left to qualify for FREE Shipping!',{amount:money(rem)});}
  }
  /* ShopController::freeShipCountry: continental US only */
  function couponShipOk(){
    var diff=document.querySelector('[name=ship_to_different]'), p=(diff&&diff.checked)?'shipping':'billing';
    var c=(document.querySelector('[name='+p+'_country]')||{}).value||'US', s=((document.querySelector('[name='+p+'_state]')||{}).value||'').toUpperCase();
    return c.toUpperCase()==='US'&&['AK','HI','PR','VI','GU','AS','MP','UM','AA','AE','AP'].indexOf(s)<0;
  }
  function totals(){
    var d=discount(), disc=override?Number(override.discount):d.amt;
    var dr=document.getElementById('disc-row'); if(dr){dr.hidden=!(disc>0);document.getElementById('disc-code').textContent=d.label;document.getElementById('disc-amt').textContent=disc.toFixed(2);}
    var freeShip=coupon&&coupon.free_shipping&&!/^warp-|freight/.test(shipId)&&couponShipOk(); // coupons never cover freight (CartService::isFreight), and only ship free in the continental US
    var eff=override?Number(override.shipping):(freeShip?0:shipCost);
    document.getElementById('sum-ship').textContent=shipId?(eff>0?money(eff):M.free):'TBD';
    var t=override?Number(override.total):Math.max(0,SUBTOTAL-disc)+eff;
    document.getElementById('sum-total').textContent=money(t);
    var pt=document.getElementById('place-total'); if(pt)pt.textContent=money(t);
    var mb=document.getElementById('mbar-total'); if(mb)mb.textContent=money(t);
    window.UDS_TOTAL_CENTS=Math.round(t*100);
    if(window.udsStripeAmount) window.udsStripeAmount(window.UDS_TOTAL_CENTS);
  }
  /* the Place order button back to its label + live total (a bare textContent would drop #place-total) */
  function resetBtn(){
    var b=document.getElementById('place-btn'); b.disabled=false;
    b.innerHTML=M.place+'<span id="place-total"></span>'; totals();
  }
  window.udsResetPlace=resetBtn;
  function inlineMsg(id,msg,focusEl){
    var el=document.getElementById(id); if(el)el.textContent=msg||'';
    if(msg&&focusEl){focusEl.focus();focusEl.scrollIntoView({block:'center',behavior:'smooth'});}
  }
  function showErrors(list){
    var eb=document.getElementById('co-errors'), ul=eb.querySelector('ul'); ul.innerHTML='';
    (list&&list.length?list:[M.error]).forEach(function(e){var li=document.createElement('li');li.textContent=e;ul.appendChild(li);});
    eb.style.display='block'; eb.focus({preventScroll:true});
    window.scrollTo({top:eb.getBoundingClientRect().top+scrollY-120,behavior:'smooth'});
  }
  /* card payment refused after the order was placed: the button is ready to try the same payment again */
  window.udsPlaceFailed=function(err){
    // a PaymentIntent that can no longer be confirmed (cancelled, already paid): the next click asks the server
    if(err&&err.code==='payment_intent_unexpected_state') retry=null;
    resetBtn();
  };
  function payMode(){
    if((form.querySelector('[name=payment_method]:checked')||form.querySelector('[name=payment_method]')||{}).value==='net_terms') return 'terms';
    return (window.udsSavedCard&&window.udsSavedCard())||'new';
  }

  /* coupon */
  function applyCoupon(){
    var code=document.getElementById('coupon-code').value.trim(); if(!code)return;
    fetch('/cart/coupon',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({code:code})})
      .then(r=>r.json()).then(res=>{
        var err=document.getElementById('coupon-err');
        if(!res.success){err.textContent=res.message||udsT('Invalid coupon');return;}
        err.textContent='';coupon=res.data;override=null;
        document.getElementById('coupon-in').style.display='none';
        document.getElementById('coupon-on').style.display='flex';
        document.getElementById('coupon-label').textContent=res.data.code;
        document.getElementById('disc-code').textContent=res.data.code;
        document.getElementById('disc-amt').textContent=Number(res.data.discount).toFixed(2);
        document.getElementById('disc-row').hidden=false;totals();
      });
  }
  function removeCoupon(){
    fetch('/cart/coupon/remove',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'}}).then(()=>{
      coupon=null;override=null;document.getElementById('coupon-in').style.display='flex';
      document.getElementById('coupon-on').style.display='none';
      document.getElementById('disc-row').hidden=true;totals();
    });
  }
  document.getElementById('coupon-apply').onclick=applyCoupon;
  document.getElementById('coupon-remove').onclick=removeCoupon;

  /* dropship / ship-to-different */
  /* the header button says where the order goes: a dropship address, one of the customer's own saved addresses, or a typed one */
  var SHIP_LABELS={!! json_encode(['off' => __('Ship to a different address'), 'dropship' => __('Using dropship address'), 'shipping' => __('Using saved address'), 'other' => __('Shipping to a different address')]) !!};
  function showDrop(on,kind){
    document.getElementById('ship-diff').checked=on;
    document.getElementById('ship-sec').style.display=on?'grid':'none';
    document.getElementById('ship-same').style.display=on?'none':'flex';
    document.getElementById('dropship-btn').textContent=SHIP_LABELS[on?(kind||'other'):'off'];
    document.getElementById('dropship-btn').setAttribute('aria-expanded',on?'true':'false');
    if(!on||!kind)document.querySelectorAll('.ss-item').forEach(function(x){x.classList.remove('on');});
    loadShipping();
  }
  document.getElementById('dropship-btn').onclick=function(){showDrop(!document.getElementById('ship-diff').checked);};
  document.getElementById('dropship-cancel').onclick=function(){showDrop(false);};
  document.querySelectorAll('.ss-item').forEach(function(el){
    el.onclick=function(){
      var d={}; try{ d=JSON.parse(el.getAttribute('data-ds')); }catch(e){}
      function set(n,val){var f=form.querySelector('[name='+n+']'); if(f)f.value=(val==null?'':val);}
      if(d.name){var nm=String(d.name).trim().split(/\s+/); set('shipping_first_name',nm.shift()); set('shipping_last_name',nm.join(' '));}
      set('shipping_company',d.company); set('shipping_address_1',d.street); set('shipping_address_2',d.unit);
      set('shipping_city',d.city); set('shipping_state',d.state); set('shipping_postcode',d.postcode);
      var cc=document.getElementById('shipping_country'); if(cc)cc.value=d.country||'US';
      showDrop(true,el.getAttribute('data-kind')||'shipping');
      document.querySelectorAll('.ss-item').forEach(function(x){x.classList.remove('on');}); el.classList.add('on');
    };
  });

  /* shipping rates -> select
     Debounced + sequenced: picking a Google Places suggestion (or the /geo
     IP prefill on load) updates several fields at once, each of which used to
     fire its own independent request. Those could resolve OUT OF ORDER — a
     slow, stale "US" quote arriving after a correct international one would
     silently overwrite it with domestic rates. A request token plus aborting
     the in-flight call means only the response to the LATEST request is ever
     applied, and the debounce collapses a burst of field changes into one. */
  let shipReqToken = 0, shipAbort = null, shipDebounce = null;
  function loadShipping(){
    clearTimeout(shipDebounce);
    shipDebounce = setTimeout(doLoadShipping, 180);
  }
  function doLoadShipping(){
    override=null;
    const diff=document.getElementById('ship-diff').checked;
    const country=(diff?document.getElementById('shipping_country'):document.getElementById('billing_country')).value;
    const state=(diff?document.getElementById('shipping_state'):document.getElementById('billing_state')).value;
    const zip=(document.querySelector(diff?'[name=shipping_postcode]':'[name=billing_postcode]')||{}).value||'';
    const grp=diff?'shipping':'billing';
    const street=(document.querySelector('[name='+grp+'_address_1]')||{}).value||'';
    const city=(document.querySelector('[name='+grp+'_city]')||{}).value||'';
    const company=(document.querySelector('[name='+grp+'_company]')||{}).value||'';
    const delivery=(document.querySelector('[name=freight_delivery]:checked')||{}).value||'residential';
    const sel=document.getElementById('ship-select'), note=document.getElementById('ship-note');
    const myToken = ++shipReqToken;
    if (shipAbort) shipAbort.abort();
    if(!zip){var isUS=!country||country==='US';sel.innerHTML='<option value="">'+(isUS?udsT('Enter your ZIP to load rates…'):udsT('Enter your postal code to load rates…'))+'</option>'+(isUS?'<option value="'+PICKUP+'" data-cost="0">'+PICKUP_LABEL+' — '+udsT('Free')+'</option>':'');shipId='';shipCost=0;totals();pickupUI();return;}
    // clear stale options immediately rather than leaving a previous (possibly
    // wrong-country) rate sitting there, selectable, while the new quote loads
    sel.innerHTML='<option value="">'+udsT('Loading rates…')+'</option>';shipId='';shipCost=0;totals();
    note.textContent=udsT('Loading live UPS & FedEx rates…');
    shipAbort = new AbortController();
    fetch('/checkout/shipping-options',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({country,state,zip,street,city,company,delivery}),signal:shipAbort.signal})
      .then(r=>r.json()).then(({data,carrier_rates,reason,freight,international,ships})=>{
        if(myToken!==shipReqToken)return; // a newer request has since started — this reply is stale, ignore it
        if(!data||!data.length){sel.innerHTML='<option value="">'+udsT('No rates for this address')+'</option>';note.textContent='';shipId='';shipCost=0;totals();return;}
        document.getElementById('freight-box').hidden=!freight;
        document.getElementById('intl-note').hidden=!international;
        var sh=document.getElementById('ship-ships'); sh.textContent=ships||''; sh.hidden=!ships||carrier_rates===false;
        sel.innerHTML='<option value="">'+(carrier_rates===false?udsT('No carrier rates for this address'):udsT('Choose a method…'))+'</option>'+data.map(o=>`<option value="${o.id}" data-cost="${o.cost}">${o.label}${o.eta?' · '+o.eta:''} — ${o.cost>0?money(o.cost):udsT('Free')}</option>`).join('');
        note.textContent=reason==='po_box'?udsT('UPS and FedEx cannot deliver to a PO box. Enter a street address, or choose local pickup.')
          :carrier_rates===false?udsT('We could not get UPS or FedEx rates for that address. Check the ZIP code and state, or call (813) 524-6491.')
          :freight?udsT('LTL freight rates from several carriers, shipping from Florida. Delivery dates are estimates.')
          :udsT('Rates shown ship from Florida. Delivery dates are estimates.');
        // preselect the cheapest SHIPPING option. Pickup is $0 and must never
        // win this: a customer who meant "ship it" would end up with nothing shipped.
        var shippable=data.filter(o=>o.id!==PICKUP);
        if(shippable.length){var cheapest=shippable.reduce((a,b)=>b.cost<a.cost?b:a,shippable[0]);sel.value=cheapest.id;shipId=cheapest.id;shipCost=cheapest.cost;}
        else{sel.value='';shipId='';shipCost=0;}
        totals();pickupUI();
      }).catch((e)=>{if(e.name==='AbortError'||myToken!==shipReqToken)return;note.textContent=udsT('Could not load rates. Call (813) 524-6491.');});
  }
  document.getElementById('ship-select').onchange=function(){
    var o=this.options[this.selectedIndex];override=null;shipId=this.value;shipCost=o?Number(o.getAttribute('data-cost')||0):0;inlineMsg('ship-msg','');totals();pickupUI();
  };
  document.getElementById('pickup-ack').addEventListener('change',function(){inlineMsg('pickup-msg','');this.closest('.co-agree').style.outline='';});
  document.getElementById('agree-terms').addEventListener('change',function(){inlineMsg('terms-msg','');this.closest('.co-agree').style.outline='';});

  /* local pickup: notice + acknowledgement, out-of-state warning, pay-at-pickup option */
  function pickupUI(){
    var on=shipId===PICKUP, box=document.getElementById('pickup-box'), far=document.getElementById('pickup-far');
    if(on&&!window._pickupInfo){window._pickupInfo=1;fetch('/checkout/pickup-info',{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(d){var a=document.getElementById('pickup-addr'),m=document.getElementById('pickup-map');if(a&&d.address)a.textContent=d.address;if(m&&d.maps)m.href=d.maps;}).catch(function(){window._pickupInfo=0;});}
    box.hidden=!on;
    // local pickup needs no street / city / ZIP (the server's required_unless says the same)
    form.querySelectorAll('[data-addr-req]').forEach(function(f){f.required=!on;});
    if(on){
      var diff=document.getElementById('ship-diff').checked;
      var st=((diff?document.getElementById('shipping_state'):document.getElementById('billing_state')).value||'').trim().toUpperCase();
      var cc=(diff?document.getElementById('shipping_country'):document.getElementById('billing_country')).value||'US';
      var isFL=cc==='US'&&(st==='FL'||st==='FLORIDA');
      far.hidden=isFL||!st;
      document.getElementById('pickup-far-state').textContent=st+(cc!=='US'?' ('+cc+')':'');
    }else{document.getElementById('pickup-ack').checked=false;}
    document.getElementById('meta-from-l').textContent=on?udsT('Pick up at'):udsT('Shipping From');
    document.getElementById('meta-date-l').textContent=on?udsT('Pickup hours'):udsT('Est. Ship Date');
    document.getElementById('meta-date-v').textContent=on?PICKUP_HOURS:SHIP_DATE;
    document.getElementById('meta-date-s').textContent=on?udsT('Other times: call :phone first',{phone:PICKUP_PHONE}):@json(__('Orders before :time ET ship same day', ['time' => \App\Services\CartService::cutoffLabel()]));
  }
  ['billing_country','billing_state','shipping_country','shipping_state'].forEach(id=>{
    const el=document.getElementById(id); if(el)el.addEventListener('change',loadShipping);
  });
  document.querySelectorAll('[name=billing_postcode],[name=shipping_postcode]').forEach(el=>el.addEventListener('blur',loadShipping));
  // the street and company decide home vs business (FedEx prices them differently), and
  // the freight delivery type decides the liftgate — each one changes the real price
  document.querySelectorAll('[name=billing_address_1],[name=shipping_address_1],[name=billing_company],[name=shipping_company]').forEach(el=>el.addEventListener('blur',loadShipping));
  document.querySelectorAll('[name=freight_delivery]').forEach(el=>el.addEventListener('change',loadShipping));
  document.addEventListener('uds:geo',loadShipping);
  document.addEventListener('uds:address-selected',loadShipping);

  /* estimated shipping modal (once per session) */
  var esm=document.getElementById('esm-back');
  // opened on request only. It used to auto-open over the form on first visit,
  // which is an interstitial between the customer and the pay button.
  function esmOpen(e){ if(e)e.preventDefault(); esm.classList.add('on'); document.getElementById('esm-ok').focus(); }
  function esmClose(){ esm.classList.remove('on'); }
  document.querySelectorAll('[data-esm-open]').forEach(function(t){ t.addEventListener('click', esmOpen); });
  document.getElementById('esm-ok').onclick=esmClose;
  esm.addEventListener('click',function(e){ if(e.target===esm) esmClose(); });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&esm.classList.contains('on')) esmClose(); });

  /* place order */
  document.getElementById('place-btn').onclick=function(){
    document.getElementById('co-errors').style.display='none';
    if(!form.reportValidity())return;   // the browser points at the first empty required field
    if(!shipId){inlineMsg('ship-msg',M.ship,document.getElementById('ship-select'));return;}
    if(shipId===PICKUP){var pa=document.getElementById('pickup-ack');if(!pa.checked){pa.closest('.co-agree').style.outline='2px solid var(--err)';inlineMsg('pickup-msg',M.pickup,pa);return;}}
    var ag=document.getElementById('agree-terms'); if(ag&&!ag.checked){ag.closest('.co-agree').style.outline='2px solid var(--err)';ag.closest('.co-agree').style.borderRadius='12px';inlineMsg('terms-msg',M.terms,ag);return;}
    const btn=this;btn.disabled=true;btn.textContent=M.placing;
    /* deferred-intent mode: the Payment Element must validate before the order is created,
       otherwise an unpayable order is left behind every time a card detail is wrong */
    var mode=payMode(), onTerms=mode==='terms', onSaved=!onTerms&&mode!=='new';
    var cents=window.UDS_TOTAL_CENTS;
    if(retry&&(retry.cents!==cents||retry.mode!==mode)) retry=null;   // the total or the card changed: place it again (the server updates the same order)
    ((window.udsStripeValidate&&!onTerms&&!onSaved)?window.udsStripeValidate():Promise.resolve({}))
      .then(sub=>{
        if(sub&&sub.error){
          document.getElementById('stripe-card-err').textContent=sub.error.message||udsT('Please check your payment details.');
          document.getElementById('stripe-card').scrollIntoView({block:'center',behavior:'smooth'});
          resetBtn();
          return Promise.reject('invalid');
        }
        // same order, same total: try the payment again without placing anything new
        if(retry&&window.udsStripeConfirm){window.udsStripeConfirm(retry.cs,retry.redirect,btn);return Promise.reject('retrying');}
        var fd=new URLSearchParams(new FormData(form)); fd.set('expected_total',String(cents));
        return fetch('/checkout/place',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:fd});
      })
      .then(r=>r.json()).then(res=>{
        if(!res.success){
          if(res.code==='total_changed'&&res.totals){ SUBTOTAL=Number(res.totals.subtotal); override=res.totals; retry=null; }
          showErrors(res.errors);
          resetBtn();return;
        }
        if(res.data.client_secret && window.udsStripeConfirm){
          retry={cs:res.data.client_secret,redirect:res.data.redirect,cents:cents,mode:mode};
          window.udsStripeConfirm(res.data.client_secret,res.data.redirect,btn);
        }
        else{location.href=res.data.redirect;}
      }).catch(e=>{
        if(e==='invalid'||e==='retrying')return;   // the card form already said what is wrong / the payment is being retried
        resetBtn();showErrors([M.net]);
      });
  };

  /* card vs invoice (business accounts) */
  form.querySelectorAll('[name=payment_method][type=radio]').forEach(function(r){r.addEventListener('change',function(){
    var t=form.querySelector('[name=payment_method]:checked').value==='net_terms';
    document.getElementById('pay-card-box').hidden=t; var n=document.getElementById('terms-note'); if(n)n.hidden=!t;
    form.querySelectorAll('.co-paywith__opt').forEach(function(o){o.classList.toggle('on',o.querySelector('input').checked);});
  });});
  form.querySelectorAll('.co-paywith__opt input:checked').forEach(function(i){i.closest('.co-paywith__opt').classList.add('on');});

  /* Express checkout: Apple Pay / Google Pay buttons. The wallet hands over contact, address and payment; rates come
     from the same endpoint as the form, and the order is placed through /checkout/place like any other. */
  (function(){
    var box=document.getElementById('co-express'); if(!box||!window.udsStripe)return;
    var S=window.udsStripe, err=document.getElementById('express-err'), rates={}, chosen=null;
    var ece=S.elements.create('expressCheckout',{buttonType:{applePay:'buy',googlePay:'buy'},buttonHeight:48,paymentMethods:{applePay:'always',googlePay:'always',link:'never',paypal:'never',amazonPay:'never'}});
    ece.mount('#express-btns');
    ece.on('ready',function(e){box.hidden=!(e.availablePaymentMethods&&(e.availablePaymentMethods.applePay||e.availablePaymentMethods.googlePay));});
    function cents(n){return Math.round(n*100);}
    var expressUS=true;   // the wallet's address decides whether a free-shipping coupon applies (continental US only)
    function totalWith(rate){var d=discount().amt, free=coupon&&coupon.free_shipping&&expressUS; return Math.max(0,SUBTOTAL-d)+(free?0:rate);}
    ece.on('click',function(e){err.textContent='';e.resolve({emailRequired:true,phoneNumberRequired:true,billingAddressRequired:true,shippingAddressRequired:true,allowedShippingCountries:['US'],
      shippingRates:[{id:'pending',displayName:udsT('Enter your address'),amount:0}]});});
    ece.on('shippingaddresschange',function(e){
      var a=e.address||{};
      expressUS=String(a.country||'US').toUpperCase()==='US'&&['AK','HI','PR','VI','GU','AS','MP','UM','AA','AE','AP'].indexOf(String(a.state||'').toUpperCase())<0;
      fetch('/checkout/shipping-options',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({country:a.country||'US',state:a.state||'',zip:a.postal_code||'',city:a.city||'',express:'1'})})
        .then(function(r){return r.json();}).then(function(res){
          var list=(res.data||[]).filter(function(o){return o.id!==PICKUP&&!/^warp-|freight/.test(o.id);});
          if(res.freight||!list.length){e.reject();return;}
          list.sort(function(x,y){return x.cost-y.cost;}); rates={}; list.forEach(function(o){rates[o.id]=o;});
          chosen=list[0];
          try{S.elements.update({amount:Math.max(50,cents(totalWith(chosen.cost)))});}catch(x){}
          e.resolve({shippingRates:list.slice(0,6).map(function(o){return {id:o.id,displayName:String(o.label).slice(0,60),amount:cents(o.cost)};})});
        }).catch(function(){e.reject();});
    });
    ece.on('shippingratechange',function(e){
      chosen=rates[e.shippingRate.id]||chosen;
      try{S.elements.update({amount:Math.max(50,cents(totalWith(chosen?chosen.cost:0)))});}catch(x){}
      e.resolve();
    });
    ece.on('confirm',function(e){
      var b=e.billingDetails||{}, sh=e.shippingAddress||{}, ba=b.address&&b.address.line1?b.address:(sh.address||{}), sa=sh.address||{};
      function split(n){n=String(n||'').trim().split(/\s+/);var f=n.shift()||'';return [f,n.join(' ')||f];}
      var bn=split(b.name||sh.name), sn=split(sh.name||b.name);
      var fd=new URLSearchParams({email:b.email||'',phone:b.phone||'',billing_first_name:bn[0],billing_last_name:bn[1],
        billing_address_1:ba.line1||'',billing_address_2:ba.line2||'',billing_city:ba.city||'',billing_state:ba.state||'',billing_postcode:ba.postal_code||'',billing_country:ba.country||'US',
        ship_to_different:'1',shipping_first_name:sn[0],shipping_last_name:sn[1],shipping_address_1:sa.line1||'',shipping_address_2:sa.line2||'',shipping_city:sa.city||'',shipping_state:sa.state||'',shipping_postcode:sa.postal_code||'',shipping_country:sa.country||'US',
        shipping_method:(e.shippingRate||{}).id||'',payment_method:'card',express:'1',agree_terms:'1'});
      var picked=rates[(e.shippingRate||{}).id]||chosen; fd.set('expected_total',String(cents(totalWith(picked?picked.cost:0))));
      var nsc=document.getElementById('dont-save-card'); if(nsc&&nsc.checked) fd.set('dont_save_card','1');
      S.elements.submit().then(function(sub){
        if(sub.error){e.paymentFailed({reason:'fail'});err.textContent=sub.error.message;return;}
        return fetch('/checkout/place',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},body:fd}).then(function(r){return r.json();}).then(function(res){
          if(!res.success||!res.data.client_secret){e.paymentFailed({reason:'fail'});err.textContent=(res.errors||[udsT('Payment could not be completed.')]).join(' ');return;}
          return S.stripe.confirmPayment({elements:S.elements,clientSecret:res.data.client_secret,redirect:'if_required',confirmParams:{return_url:location.origin+res.data.redirect}})
            .then(function(c){ if(c.error){err.textContent=c.error.message;return;} location.href=res.data.redirect; });
        });
      }).catch(function(){e.paymentFailed({reason:'fail'});err.textContent=udsT('Network error. Please try again.');});
    });
  })();

  freeBar();totals();

  /* mobile sticky bar: hide while the real summary/Place Order is on screen */
  var mbar=document.querySelector('.ck-mbar'), sumEl=document.getElementById('summary');
  if(mbar&&sumEl&&'IntersectionObserver' in window){
    new IntersectionObserver(function(es){es.forEach(function(e){mbar.classList.toggle('hide',e.isIntersecting);});},{threshold:0.15}).observe(sumEl);
  }
})();
</script>
<script src="/js/geo.js?v={{ @filemtime(public_path('js/geo.js')) }}"></script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('uds.gmaps_key') }}&libraries=places&callback=udsInitPlaces" async defer></script>
@includeIf('partials.checkout-recovery')   {{-- abandoned-cart capture (recovery agent) --}}
@endsection
