@extends('layouts.app')
@section('robots','noindex,follow')
@php
  $titles = ['return' => __('Return a part'), 'exchange' => __('Exchange a part'), 'cancel' => __('Cancel an order')];
  $leads = [
    'return' => __('Unused stock parts can come back within 14 days of delivery. Tell us what and why, and we will e-mail an RMA number with the return address.'),
    'exchange' => __('Wrong size or series? Tell us what you have and what you need, and we will send RMA instructions for the swap.'),
    'cancel' => __('Stock orders can be cancelled until they ship. Custom and made-to-order builds cannot be cancelled once ordered.'),
  ];
@endphp
@section('title', $titles[$type] . ' | Universal Driveshafts')
@section('desc', __('Request a return, exchange or cancellation for your Universal Driveshafts order.'))
@push('head')
<style>
.rma2{padding-block:clamp(1.5rem,1rem + 2.5vw,3rem)}
.rma2__grid{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(260px,.85fr);gap:clamp(1.25rem,1rem + 1.5vw,2.5rem);align-items:start}
@media (max-width:900px){.rma2__grid{grid-template-columns:1fr}}
.rma2-seg{display:inline-flex;gap:4px;padding:4px;border-radius:var(--r-pill);background:var(--paper-3);border:1px solid var(--line);margin-bottom:1.25rem;flex-wrap:wrap}
.rma2-seg a{display:inline-flex;align-items:center;gap:.45rem;padding:.55rem 1rem;border-radius:var(--r-pill);font:600 .9rem/1 var(--font-h);color:var(--ink-2);text-decoration:none;transition:background var(--dur-1),color var(--dur-1)}
.rma2-seg a:hover{color:var(--blue)}
.rma2-seg a[aria-current="page"]{background:#fff;color:var(--blue);box-shadow:var(--sh-1)}
.rma2-seg a:focus-visible{outline:2px solid var(--blue);outline-offset:2px}
.rma2-card{background:#fff;border:1px solid var(--line);border-radius:var(--r-lg);box-shadow:var(--sh-1);padding:clamp(1.1rem,.9rem + 1vw,1.75rem)}
.rma2-sec + .rma2-sec{margin-top:1.6rem;padding-top:1.4rem;border-top:1px solid var(--line-2)}
.rma2-sec h2{display:flex;align-items:center;gap:.6rem;margin:0 0 .9rem;font-size:1.05rem}
.rma2-sec h2 .n{display:grid;place-items:center;width:1.7rem;height:1.7rem;border-radius:50%;background:var(--blue-050);color:var(--blue);font:600 .82rem/1 var(--font-m)}
.rma2-f{display:grid;grid-template-columns:1fr 1fr;gap:.9rem 1rem}
@media (max-width:560px){.rma2-f{grid-template-columns:1fr}}
.rma2-f .full{grid-column:1/-1}
.rma2-f label,.rma2-lab{display:block;font-size:.84rem;font-weight:600;color:var(--ink-2);margin-bottom:.35rem}
.rma2-f input,.rma2-f select,.rma2-f textarea{width:100%;border:1px solid var(--line-strong);border-radius:var(--r-sm);padding:.7rem .85rem;font:inherit;font-size:.95rem;color:var(--ink);background:#fff;transition:border-color var(--dur-1),box-shadow var(--dur-1)}
.rma2-f textarea{min-height:110px;resize:vertical}
.rma2-f input:focus,.rma2-f select:focus,.rma2-f textarea:focus{outline:0;border-color:var(--blue);box-shadow:var(--sh-focus)}
.rma2-f input[readonly]{background:var(--paper-2);color:var(--ink-2)}
.rma2-hint{font-size:.8rem;color:var(--ink-faint);margin-top:.3rem}
.rma2-order{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.4rem 1rem;padding:.85rem 1rem;border-radius:var(--r);background:var(--paper-2);border:1px solid var(--line)}
.rma2-order b{font-family:var(--font-m);color:var(--blue)}
.rma2-items{display:grid;gap:.55rem;margin:0;padding:0;list-style:none}
.rma2-item{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:.8rem;align-items:center;padding:.75rem .9rem;border:1px solid var(--line);border-radius:var(--r);background:#fff;transition:border-color var(--dur-1),box-shadow var(--dur-1)}
.rma2-item:has(input[type=checkbox]:checked){border-color:var(--blue);box-shadow:var(--sh-focus)}
.rma2-item input[type=checkbox]{width:1.1rem;height:1.1rem;accent-color:var(--blue)}
.rma2-item .t b{display:block;font-weight:600;color:var(--ink);font-size:.93rem;line-height:1.35}
.rma2-item .t small{font-family:var(--font-m);font-size:.75rem;color:var(--ink-faint)}
.rma2-item.is-custom{opacity:.62;background:var(--paper-2)}
.rma2-item.is-custom .t small::after{content:" · made to order — not returnable";font-family:var(--font-b);color:var(--warn)}
.rma2-qty{display:inline-flex;align-items:center;border:1px solid var(--line-strong);border-radius:var(--r-sm);overflow:hidden}
.rma2-qty button{width:2rem;height:2rem;border:0;background:var(--paper-2);color:var(--ink-2);font-size:1rem;cursor:pointer}
.rma2-qty button:hover{background:var(--paper-3)}
.rma2-qty input{width:2.6rem;height:2rem;border:0;text-align:center;font:600 .9rem/1 var(--font-m);-moz-appearance:textfield}
.rma2-qty input::-webkit-inner-spin-button{-webkit-appearance:none}
.rma2-chips{display:flex;flex-wrap:wrap;gap:.5rem}
.rma2-chip{position:relative}
.rma2-chip input{position:absolute;opacity:0;pointer-events:none}
.rma2-chip span{display:inline-block;padding:.6rem .95rem;border:1px solid var(--line-strong);border-radius:var(--r-pill);font-size:.88rem;color:var(--ink-2);cursor:pointer;background:#fff;transition:all var(--dur-1)}
.rma2-chip input:checked + span{border-color:var(--blue);background:var(--blue-050);color:var(--blue-700);font-weight:600}
.rma2-chip input:focus-visible + span{outline:2px solid var(--blue);outline-offset:2px}
.rma2-warn{margin-top:.7rem;padding:.7rem .9rem;border-radius:var(--r-sm);background:var(--warn-bg);border:1px solid #ead9b5;color:var(--ink-2);font-size:.86rem}
.rma2-err{margin-top:1rem;padding:.75rem .9rem;border-radius:var(--r-sm);background:var(--err-bg);border:1px solid var(--err-line);color:var(--err);font-size:.88rem}
.rma2-submit{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-top:1.5rem}
.rma2-submit p{margin:0;font-size:.82rem;color:var(--ink-faint);max-width:42ch}
.rma2__side{position:sticky;top:96px}
@media (max-width:900px){.rma2__side{position:static}}
.rma2-glance{display:grid;gap:.65rem;margin:0;padding:0;list-style:none}
.rma2-glance li{display:grid;grid-template-columns:3.4rem 1fr;gap:.8rem;align-items:center;font-size:.9rem;color:var(--ink-2);line-height:1.45}
.rma2-glance .k{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;min-height:2.9rem;padding:4px 2px;border-radius:var(--r-sm);background:var(--blue-050);color:var(--blue-700);text-align:center;white-space:nowrap}
.rma2-glance .k b{font:700 1rem/1 var(--font-h);letter-spacing:-.01em}
.rma2-glance .k small{font:600 .56rem/1 var(--font-m);letter-spacing:.08em;text-transform:uppercase;opacity:.8}
.rma2-glance .k svg{width:16px;height:16px}
.rma2-glance li.no .k{background:var(--err-bg);color:var(--err)}
.rma2-side-card + .rma2-side-card{margin-top:1rem}
.rma2-side-card h3{margin:0 0 .8rem;font-size:1rem}
.rma2-side-card p{margin:.7rem 0 0;font-size:.88rem;color:var(--ink-2)}
.rma2-done{text-align:center;padding:clamp(1.5rem,1rem + 2vw,2.5rem) 1rem}
.rma2-done .ok{display:grid;place-items:center;width:3.4rem;height:3.4rem;margin:0 auto 1rem;border-radius:50%;background:var(--ok-bg);color:var(--ok)}
.rma2-done h2{margin:0 0 .4rem}
.rma2-done .ref{font:600 1.15rem/1.2 var(--font-m);color:var(--blue);margin:.2rem 0 1rem}
.rma2-done ol{text-align:left;max-width:440px;margin:1rem auto 0;padding-left:1.2rem;color:var(--ink-2);font-size:.92rem;line-height:1.6}
.rma2-closed h2{margin:0 0 .6rem;font-size:1.15rem}
.rma2-closed p{margin:.5rem 0 0;color:var(--ink-2);max-width:60ch}
.rma2-closed .acts{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1.2rem}
</style>
@endpush
@section('content')
<section class="phero"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><a href="/return-policy">{{ __('Returns & warranty') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ $titles[$type] }}</span></nav>
  <span class="kicker">{{ __('Returns, exchanges & cancellations') }}</span>
  <h1>{{ $titles[$type] }}</h1>
  <p class="lead">{{ $leads[$type] }}</p>
</div></section>

<section class="rma2"><div class="wrap"><div class="rma2__grid">
  <div>
    @php $qs = $order ? '?order=' . rawurlencode($order->order_number) . '&e=' . rawurlencode($order->email) : ''; @endphp
    <nav class="rma2-seg" aria-label="{{ __('Request type') }}">
      <a href="/return-request-form{{ $qs }}" @if($type==='return') aria-current="page" @endif><x-icon name="arrow-left"/> {{ __('Return') }}</a>
      <a href="/exchange-request-form{{ $qs }}" @if($type==='exchange') aria-current="page" @endif><x-icon name="returns"/> {{ __('Exchange') }}</a>
      <a href="/product-cancel-request-form{{ $qs }}" @if($type==='cancel') aria-current="page" @endif><x-icon name="x"/> {{ __('Cancel') }}</a>
    </nav>

    <div class="rma2-card" id="rma-box">
      <div id="rma-done" class="rma2-done" hidden>
        <div class="ok"><x-icon name="check"/></div>
        <h2>{{ __('Request received') }}</h2>
        <div class="ref" id="rma-ref"></div>
        <p style="margin:0;color:var(--ink-2)">{!! __('We sent a confirmation to <b id="rma-to"></b>. A person reviews every request, usually within one business day.') !!}</p>
        <ol id="rma-next"></ol>
        <p style="margin-top:1.4rem"><a class="btn btn-ghost" href="{{ $order ? \App\Support\OrderEvents::statusUrl($order, false) : '/track-order' }}">{{ __($order ? 'Back to my order' : 'Back to order tracking') }} <x-icon name="arrow-right"/></a></p>
      </div>

      @if($closedOn)
      <div class="rma2-closed" role="status">
        <h2>{{ __('The return window for this order has closed') }}</h2>
        <p>{{ __('Returns and exchanges can be requested within :days days of delivery. For order :n that time ended on :date.', ['days' => \App\Support\Rma::WINDOW_DAYS, 'n' => $order->order_number, 'date' => $closedOn->isoFormat('LL')]) }}</p>
        <p>{{ __('Defective part? UDS-brand parts carry a 12-month warranty. Call us, or send photos and your order number through the contact page.') }}</p>
        <p class="acts"><a class="btn btn-primary" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a><a class="btn btn-ghost" href="/contact">{{ __('Contact us') }}</a></p>
      </div>
      @else
      <form id="rma-form" novalidate onsubmit="return false">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">

        <div class="rma2-sec">
          <h2><span class="n">1</span>{{ __('Your order') }}</h2>
          @if($order)
            <div class="rma2-order"><span>{!! __('Order <b>:n</b> · placed :date', ['n' => e($order->order_number), 'date' => e(\Carbon\Carbon::parse($order->created_at)->isoFormat('ll'))]) !!}</span><a class="textlink" href="/{{ ['return'=>'return-request-form','exchange'=>'exchange-request-form','cancel'=>'product-cancel-request-form'][$type] }}">{{ __('Different order') }}</a></div>
            <input type="hidden" name="order_number" value="{{ $order->order_number }}">
          @endif
          <div class="rma2-f" style="margin-top:{{ $order ? '.9rem' : '0' }}">
            @unless($order)
            <div class="full"><label for="r-order">{{ __('Order number') }} *</label><input id="r-order" type="text" name="order_number" value="{{ $orderNumber }}" placeholder="UDS-260910-W0GM" autocomplete="off" required><div class="rma2-hint">{{ __('In the subject line of your confirmation e-mail.') }} {{ __('No order number? Call us at :phone.', ['phone' => config('uds.pickup.phone')]) }}</div></div>
            @endunless
            <div><label for="r-name">{{ __('Your name *') }}</label><input id="r-name" type="text" name="name" value="{{ $prefillName }}" autocomplete="name" required></div>
            <div><label for="r-email">{{ __('E-mail *') }}</label><input id="r-email" type="email" name="email" value="{{ $prefillEmail }}" autocomplete="email" required></div>
            <div class="full"><label for="r-phone">{{ __('Phone') }}</label><input id="r-phone" type="tel" name="phone" value="{{ $prefillPhone }}" autocomplete="tel"></div>
          </div>
        </div>

        <div class="rma2-sec">
          <h2><span class="n">2</span>{{ __($type === 'cancel' ? 'What to cancel' : 'Which parts') }}</h2>
          @php $returnable = collect($items)->where('custom', false)->count(); @endphp
          @if($items && !$returnable)
            <div class="rma2-warn" style="margin-top:0">{!! __($type === 'cancel' ? 'Everything on this order was built to order, so it can\'t be cancelled under our policy. If a part arrived damaged or wrong, or failed in service, tell us below — damage and wrong items are fixed at our cost, and failures are covered by the <a href="/return-policy#warranty">warranty</a>.' : 'Everything on this order was built to order, so it can\'t be returned or exchanged under our policy. If a part arrived damaged or wrong, or failed in service, tell us below — damage and wrong items are fixed at our cost, and failures are covered by the <a href="/return-policy#warranty">warranty</a>.') !!}</div>
            <div class="rma2-f" style="margin-top:.9rem"><div class="full"><label for="r-product">{{ __('Which part') }}</label><input id="r-product" type="text" name="product_free" value="{{ collect($items)->map(fn ($i) => ($i['sku'] ?: $i['name']))->implode(', ') }}" maxlength="190"></div></div>
          @elseif($items)
            <ul class="rma2-items" id="rma-items">
              @foreach($items as $i => $it)
                <li class="rma2-item {{ $it['custom'] ? 'is-custom' : '' }}">
                  <input type="checkbox" id="it{{ $i }}" data-sku="{{ $it['sku'] }}" data-name="{{ $it['name'] }}" @disabled($it['custom']) @checked(count($items) === 1 && !$it['custom']) aria-label="{{ __('Include :name', ['name' => $it['name']]) }}">
                  <label class="t" for="it{{ $i }}"><b>{{ $it['name'] }}</b><small>{{ $it['sku'] ? __('Part #') . ' ' . $it['sku'] : __('No part number') }} · {{ __('ordered :n', ['n' => $it['qty']]) }}</small></label>
                  @if($it['qty'] > 1 && !$it['custom'])
                    <span class="rma2-qty"><button type="button" data-step="-1" aria-label="{{ __('Fewer') }}">−</button><input type="number" min="1" max="{{ $it['qty'] }}" value="{{ $it['qty'] }}" aria-label="{{ __('Quantity') }}"><button type="button" data-step="1" aria-label="{{ __('More') }}">+</button></span>
                  @else<span></span>@endif
                </li>
              @endforeach
            </ul>
            @if($type === 'cancel')<p class="rma2-hint" style="margin-top:.6rem">{{ __('Tick everything to cancel the whole order.') }}</p>@endif
          @else
            <div class="rma2-f"><div class="full"><label for="r-product">{{ __('Part number(s) and quantity') }}</label><input id="r-product" type="text" name="product_free" placeholder="{{ __('e.g. 2× 5-1310X u-joint, 1× 3102-80 flange yoke') }}" maxlength="190"></div></div>
          @endif
        </div>

        <div class="rma2-sec">
          <h2><span class="n">3</span>{{ __($type === 'cancel' ? 'Why' : 'Why and in what condition') }}</h2>
          <div class="rma2-f">
            <div class="full"><label for="r-reason">{{ __('Reason') }}</label><select id="r-reason" name="reason">@foreach($reasons as $rs)<option value="{{ $rs }}" @selected($prefillReason === $rs)>{{ __($rs) }}</option>@endforeach</select></div>
            @if($type !== 'cancel')
            <div class="full">
              <span class="rma2-lab">{{ __('Condition of the part') }}</span>
              <div class="rma2-chips" role="radiogroup" aria-label="{{ __('Condition') }}">
                @foreach(['Unopened, in the original packaging','Opened, never installed','Installed or used'] as $c)
                  <label class="rma2-chip"><input type="radio" name="condition" value="{{ $c }}" @checked($loop->first)><span>{{ __($c) }}</span></label>
                @endforeach
              </div>
              <div class="rma2-warn" id="rma-installed" hidden>{!! __('Installed or used parts can\'t be returned for a refund. If it failed in service, this is a <a href="/return-policy#warranty">warranty claim</a> — keep the part and tell us what happened below.') !!}</div>
            </div>
            @endif
            @if($type === 'exchange')
            <div class="full"><label for="r-want">{{ __('What do you need instead?') }}</label><input id="r-want" type="text" name="want" placeholder="{{ __('Part number, or series and measurements (e.g. 1350 series, 3.5" tube)') }}"></div>
            @endif
            <div class="full"><label for="r-details">{{ __('Anything else we should know') }}</label><textarea id="r-details" name="details_free" rows="4" maxlength="1500" placeholder="{{ __($type === 'cancel' ? 'Optional.' : 'What happened, what you measured, photos you can e-mail us…') }}"></textarea></div>
          </div>
        </div>

        <input type="hidden" name="product">
        <input type="hidden" name="details">
        <div class="rma2-err" id="rma-err" hidden role="alert"></div>
        <div class="rma2-submit">
          <p>@if($type==='cancel'){{ __("Orders that have already shipped can't be cancelled — use a return instead.") }}@else {{ __("Please don't ship anything back until you receive the RMA number and address.") }}@endif</p>
          <button type="button" class="btn btn-primary btn-lg" id="rma-submit">{{ __(['return'=>'Request return','exchange'=>'Request exchange','cancel'=>'Request cancellation'][$type]) }} <x-icon name="arrow-right"/></button>
        </div>
      </form>
      @endif
    </div>
  </div>

  <aside class="rma2__side">
    <div class="rma2-card rma2-side-card">
      <h3>{{ __('The policy at a glance') }}</h3>
      <ul class="rma2-glance">
        <li><span class="k"><b>{{ \App\Support\Rma::WINDOW_DAYS }}</b><small>{{ __('days') }}</small></span><span>{{ __('To return new, uninstalled stock parts, with an RMA number.') }}</span></li>
        <li><span class="k"><b>20%</b><small>{{ __('fee') }}</small></span><span>{{ __('Restocking fee on stock-part returns. Original shipping isn\'t refunded.') }}</span></li>
        <li class="no"><span class="k"><x-icon name="x"/></span><span>{{ __('Custom builds, cut-to-length and made-to-order items can\'t be returned.') }}</span></li>
        <li><span class="k"><b>5</b><small>{{ __('days') }}</small></span><span>{{ __('To report damage, a shortage or a wrong item — we fix those at our cost.') }}</span></li>
        <li><span class="k"><b>12</b><small>{{ __('months') }}</small></span><span>{{ __('Limited warranty on UDS-brand products.') }}</span></li>
      </ul>
      <p><a class="textlink" href="/return-policy">{{ __('Read the full policy') }} <x-icon name="arrow-right"/></a></p>
    </div>
    <div class="rma2-card rma2-side-card">
      <h3>{{ __('Rather talk it through?') }}</h3>
      <p style="margin-top:0">{{ __('Call with your order number and we\'ll sort it out on the phone.') }}</p>
      <p><a class="textlink" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a></p>
    </div>
  </aside>
</div></div></section>
@endsection
@php $i18nT = ['tick'=>__('Tick at least one part.'),'which'=>__('Tell us which part — the part number is on the box and the invoice.'),'name'=>__('Please enter your name.'),'email'=>__('Please enter a valid e-mail.'),'order'=>__('The order number is needed for this request.'),'sending'=>__('Sending…'),'oops'=>__('Something went wrong. Call (813) 524-6491.'),'net'=>__('Network error — check your connection and try again.'),'cancelSteps'=>[__('We check whether the order has left the shop.'),__('If it hasn’t, we cancel it and confirm the refund by e-mail.'),__('You get an e-mail either way.')],'returnSteps'=>[__('We review the request and e-mail you an RMA number and the return address.'),__('Pack the part in its original packaging and write the RMA number on the outside of the box.'),__('Ship it back prepaid and insured. Once it’s inspected we process the refund or exchange as the return policy describes.')]]; @endphp
@push('scripts')
<script>
(function(){
  var form=document.getElementById('rma-form'); if(!form) return;
  var CSRF=document.querySelector('meta[name="csrf-token"]').content;
  var TYPE=@json($type);
  var T=@json($i18nT);

  form.addEventListener('click',function(e){
    var b=e.target.closest('[data-step]'); if(!b) return;
    var i=b.parentNode.querySelector('input'); var v=Math.min(+i.max||99,Math.max(1,(+i.value||1)+(+b.getAttribute('data-step'))));
    i.value=v; var cb=b.closest('.rma2-item').querySelector('input[type=checkbox]'); if(cb&&!cb.disabled) cb.checked=true;
  });
  var warn=document.getElementById('rma-installed');
  form.querySelectorAll('[name=condition]').forEach(function(r){r.addEventListener('change',function(){ if(warn) warn.hidden=!(TYPE==='return'&&/Installed/.test(r.value)&&r.checked); });});

  function compose(){
    var lines=[], picked=[];
    form.querySelectorAll('#rma-items li').forEach(function(li){
      var cb=li.querySelector('input[type=checkbox]'); if(!cb||!cb.checked) return;
      var q=li.querySelector('.rma2-qty input'); var n=q?q.value:1;
      picked.push(n+'× '+(cb.getAttribute('data-sku')||cb.getAttribute('data-name')));
    });
    var free=form.querySelector('[name=product_free]');
    var product=picked.length?picked.join(', '):(free?free.value.trim():'');
    var cond=form.querySelector('[name=condition]:checked'), want=form.querySelector('[name=want]'), extra=form.querySelector('[name=details_free]').value.trim();
    if(picked.length) lines.push('Items: '+picked.join(', '));
    if(cond) lines.push('Condition: '+cond.value);
    if(want&&want.value.trim()) lines.push('Exchange for: '+want.value.trim());
    if(extra) lines.push('', extra);
    form.product.value=product.slice(0,190);
    form.details.value=lines.join('\n').slice(0,2000);
    return {picked:picked.length, hasItems:!!form.querySelector('#rma-items'), product:product};
  }

  function err(m){var e=document.getElementById('rma-err'); e.textContent=m; e.hidden=!m; if(m) e.scrollIntoView({block:'center',behavior:'smooth'});}

  document.getElementById('rma-submit').onclick=function(){
    var btn=this; err('');
    var c=compose();
    if(c.hasItems&&!c.picked) return err(T.tick);
    if(!c.hasItems&&TYPE!=='cancel'&&!c.product) return err(T.which);
    if(!form.name.value.trim()) return err(T.name), form.name.focus();
    if(!/^\S+@\S+\.\S+$/.test(form.email.value.trim())) return err(T.email), form.email.focus();
    var on=form.querySelector('[name=order_number]'); if(on&&!on.value.trim()) return err(T.order), on.focus();
    btn.disabled=true; var label=btn.innerHTML; btn.textContent=T.sending;
    var fd=new FormData(form); ['product_free','details_free','want','condition'].forEach(function(k){fd.delete(k);});
    fetch('/rma',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:new URLSearchParams(fd)})
      .then(function(r){return r.json().then(function(d){d._s=r.status;return d;});}).then(function(d){
        if(!d.success){btn.disabled=false;btn.innerHTML=label;return err(d.errors?Object.values(d.errors).flat().join(' '):(d.message||T.oops));}
        form.hidden=true;
        document.getElementById('rma-ref').textContent=d.ref;
        document.getElementById('rma-to').textContent=form.email.value.trim();
        var steps=TYPE==='cancel'
          ?T.cancelSteps
          :T.returnSteps;
        document.getElementById('rma-next').innerHTML=steps.map(function(s){return '<li>'+s+'</li>';}).join('');
        document.getElementById('rma-done').hidden=false;
        window.scrollTo({top:document.getElementById('rma-box').getBoundingClientRect().top+scrollY-110,behavior:'smooth'});
      }).catch(function(){btn.disabled=false;btn.innerHTML=label;err(T.net);});
  };
})();
</script>
@endpush
