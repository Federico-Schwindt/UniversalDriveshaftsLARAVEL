@extends('layouts.app')
@section('robots','noindex,nofollow')
@section('title', __('Order :n status | Universal Driveshafts', ['n' => $order->order_number]))
@section('desc', __('Live status of Universal Driveshafts order :n: payment, build, shipping and delivery.', ['n' => $order->order_number]))
@php
  $state = $order->status === 'cancelled' ? 'cancelled'
    : ($order->delivered_at ? 'delivered'
    : ($order->tracking_number ? 'shipped'
    : ($isPickup && $order->status === 'completed' ? 'ready'
    : ($order->payment_status === 'paid' || in_array($order->payment_method, ['pay_at_pickup', 'net_terms'], true) ? 'production' : 'awaiting'))));
  $headline = ['cancelled' => 'This order was cancelled', 'delivered' => 'Your order was delivered', 'shipped' => 'Your order is on its way',
    'ready' => 'Your parts are ready for pickup', 'production' => $isPickup ? 'Your order is being prepared' : 'Your order is in production',
    'awaiting' => 'We have your order'][$state];
  $badge = ['cancelled' => 'Cancelled', 'delivered' => 'Delivered', 'shipped' => 'In transit', 'ready' => 'Ready for pickup',
    'production' => $isPickup ? 'Being prepared' : 'In production', 'awaiting' => 'Awaiting payment'][$state];
  $tone = $order->status === 'cancelled' ? 'err' : ($order->delivered_at || ($isPickup && $order->status==='completed') ? 'ok' : ($order->tracking_number ? 'blue' : 'warn'));
  $fmt = fn($c) => $c ? $c->timezone(config('app.timezone'))->isoFormat('ll · LT') : null;
  $get = fn(string $k) => trim((string) (($order->{'shipping_'.$k} ?? null) ?: ($order->{'billing_'.$k} ?? '')));
  // label services arrive raw ("UPS Groundsaver Greater Than1lb"); show what a person would say
  $svc = $order->tracking_service ? trim(preg_replace(['/^(UPS|FedEx|USPS|DHL)\s+/i', '/Groundsaver Greater Than\s*1lb|Groundsaver Less Than Or Equal To\s*1lb/i'], ['', 'Ground Saver'], $order->tracking_service)) : null;
  $addr = array_filter([trim($get('first_name').' '.$get('last_name')), $order->billing_company && !$order->ship_to_different ? $order->billing_company : null, $get('address_1'), $get('address_2'), trim($get('city').', '.$get('state').' '.$get('postcode'), ', '), $get('country')]);
@endphp
@push('head')
<style>
.ost{--ost-ok:var(--ok);--ost-ok-bg:var(--ok-bg)}
.ost-hero{background:linear-gradient(180deg,var(--paper-2),var(--paper));border-bottom:1px solid var(--line);padding:clamp(1.6rem,1rem + 2.5vw,3rem) 0 clamp(1.4rem,1rem + 2vw,2.4rem)}
.ost-hero__row{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:1rem 2rem;margin-top:.5rem}
.ost-hero h1{font-size:clamp(1.55rem,1.2rem + 1.6vw,2.4rem);margin:.15rem 0 .35rem;line-height:1.15}
.ost-num{font-family:var(--font-m);font-weight:600;color:var(--blue);font-size:clamp(1rem,.9rem + .5vw,1.2rem);letter-spacing:.01em;word-break:break-all}
.ost-meta{color:var(--ink-2);font-size:.95rem;margin:0}
.ost-badge{display:inline-flex;align-items:center;gap:.5rem;padding:.55rem .95rem;border-radius:var(--r-pill);font-family:var(--font-h);font-weight:600;font-size:.92rem;border:1px solid transparent;white-space:nowrap}
.ost-badge::before{content:"";width:.6rem;height:.6rem;border-radius:50%;background:currentColor;box-shadow:0 0 0 4px color-mix(in srgb,currentColor 18%,transparent)}
.ost-badge--ok{background:var(--ok-bg);color:var(--ok);border-color:var(--ok-line)}
.ost-badge--blue{background:var(--blue-050);color:var(--blue);border-color:var(--blue-200)}
.ost-badge--warn{background:var(--warn-bg);color:var(--warn);border-color:#ead9b5}
.ost-badge--err{background:var(--err-bg);color:var(--err);border-color:var(--err-line)}
.ost-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,.9fr);gap:clamp(1.25rem,1rem + 1.5vw,2.5rem);align-items:start;padding-block:clamp(1.6rem,1rem + 2.5vw,3rem)}
.ost-card{background:#fff;border:1px solid var(--line);border-radius:var(--r-lg);box-shadow:var(--sh-1);padding:clamp(1.1rem,.9rem + 1vw,1.75rem)}
.ost-card + .ost-card{margin-top:1.25rem}
.ost-rma + .ost-rma{margin-top:.9rem;padding-top:.9rem;border-top:1px solid var(--line)}
.ost-rma p{margin:.35rem 0 0;color:var(--ink-2);font-size:.92rem}
.ost-card h2{font-size:1.08rem;margin:0 0 1rem;display:flex;align-items:center;gap:.55rem}
.ost-card h2 .i{color:var(--blue);font-size:1.15em}
.ost-tl{list-style:none;margin:0;padding:0;display:grid;gap:0}
.ost-tl li{display:grid;grid-template-columns:2.25rem minmax(0,1fr);gap:0 .9rem;position:relative;padding-bottom:1.35rem}
.ost-tl li:last-child{padding-bottom:0}
.ost-tl li::before{content:"";position:absolute;left:calc(1.125rem - 1px);top:1.75rem;bottom:-.1rem;width:2px;background:var(--line-strong);border-radius:1px}
.ost-tl li:last-child::before{display:none}
.ost-tl li.is-done::before{background:var(--ok)}
.ost-dot{width:2.25rem;height:2.25rem;border-radius:50%;display:grid;place-items:center;border:2px solid var(--line-strong);background:#fff;color:var(--ink-faint);font-family:var(--font-h);font-weight:600;font-size:.85rem;position:relative;z-index:1}
.is-done .ost-dot{background:var(--ok);border-color:var(--ok);color:#fff}
.is-current .ost-dot{border-color:var(--blue);color:var(--blue);box-shadow:0 0 0 5px var(--blue-100)}
.is-current .ost-dot::after{content:"";position:absolute;inset:-2px;border-radius:50%;border:2px solid var(--blue);animation:ost-pulse 1.8s var(--ease) infinite}
@keyframes ost-pulse{0%{transform:scale(1);opacity:.7}100%{transform:scale(1.65);opacity:0}}
@media (prefers-reduced-motion:reduce){.is-current .ost-dot::after{animation:none;display:none}}
.is-skipped .ost-dot{border-style:dashed;opacity:.6}
.is-cancelled .ost-dot{background:var(--err);border-color:var(--err);color:#fff}
.ost-tl .t{display:flex;flex-wrap:wrap;align-items:baseline;gap:.2rem .75rem;padding-top:.3rem}
.ost-tl .t b{font-family:var(--font-h);font-weight:600;font-size:1.02rem;color:var(--ink)}
.is-todo .t b,.is-skipped .t b{color:var(--ink-3);font-weight:500}
.ost-tl time{font-family:var(--font-m);font-size:.78rem;color:var(--ink-faint);white-space:nowrap}
.ost-tl p{margin:.2rem 0 0;color:var(--ink-2);font-size:.92rem;line-height:1.5;grid-column:2}
.is-todo p{color:var(--ink-3)}
.ost-ship{display:grid;grid-template-columns:1fr auto;gap:1rem;align-items:center}
.ost-ship__lab{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-faint);font-family:var(--font-h);font-weight:600}
.ost-ship__num{font-family:var(--font-m);font-weight:600;font-size:clamp(1rem,.9rem + .6vw,1.25rem);color:var(--ink);word-break:break-all;margin:.15rem 0 .1rem}
.ost-ship__svc{color:var(--ink-2);font-size:.9rem;margin:0}
.ost-ship .btn{white-space:nowrap}
@media (max-width:560px){.ost-ship{grid-template-columns:1fr}.ost-ship .btn{justify-content:center}}
.ost-copy{display:inline-flex;align-items:center;gap:.3rem;background:none;border:1px solid var(--line-strong);border-radius:var(--r-xs);padding:.2rem .55rem;font:inherit;font-size:.78rem;color:var(--ink-2);cursor:pointer;margin-left:.5rem;vertical-align:middle}
.ost-copy:hover{border-color:var(--blue);color:var(--blue)}
.ost-items{margin:0;padding:0;list-style:none;border-top:1px solid var(--line-2)}
.ost-items li{display:flex;justify-content:space-between;gap:1rem;padding:.75rem 0;border-bottom:1px solid var(--line-2);font-size:.95rem}
.ost-items li .n b{display:block;font-weight:600;color:var(--ink)}
.ost-items li .n small{color:var(--ink-faint);display:block;margin-top:.1rem}
.ost-items li .n .sku{font-family:var(--font-m);font-size:.76rem;color:var(--ink-faint)}
.ost-items li .p{font-family:var(--font-m);font-weight:600;white-space:nowrap}
.ost-tot{display:grid;gap:.3rem;margin-top:.75rem;font-size:.92rem;color:var(--ink-2)}
.ost-tot div{display:flex;justify-content:space-between;gap:1rem}
.ost-tot .grand{font-family:var(--font-h);font-weight:600;color:var(--ink);font-size:1.05rem;border-top:1px solid var(--line);padding-top:.5rem;margin-top:.2rem}
.ost-tot .grand span:last-child{font-family:var(--font-m);color:var(--blue)}
.ost-side .ost-card{padding:1.2rem 1.3rem}
.ost-side h3{font-size:.98rem;margin:0 0 .5rem;font-family:var(--font-h)}
.ost-side p{margin:0 0 .6rem;color:var(--ink-2);font-size:.92rem;line-height:1.55}
.ost-side address{font-style:normal;line-height:1.55;color:var(--ink);font-size:.95rem}
.ost-side .textlink{display:inline-flex;align-items:center;gap:.4rem}
.ost-kv{display:grid;grid-template-columns:auto 1fr;gap:.35rem 1rem;font-size:.9rem;margin:0}
.ost-kv dt{color:var(--ink-faint)}.ost-kv dd{margin:0;color:var(--ink);font-weight:500}
.ost-pickup{background:var(--warn-bg);border:1px solid #ead9b5;border-radius:var(--r);padding:1rem 1.1rem;font-size:.92rem;color:var(--ink-2)}
.ost-pickup b{color:var(--ink);font-family:var(--font-h)}
.ost-sms{display:flex;gap:.6rem;align-items:flex-start;font-size:.86rem;color:var(--ink-2);background:var(--paper-2);border-radius:var(--r);padding:.75rem .9rem}
.ost-sms .i{color:var(--blue);flex:none;margin-top:.1rem}
.ost-acts{display:flex;flex-wrap:wrap;gap:.6rem;margin-top:1.15rem}
.ost-acts .btn{display:inline-flex;align-items:center;gap:.45rem}
.ost-actlist{display:grid;gap:.45rem;margin:0;padding:0;list-style:none}
.ost-actlist a{display:flex;align-items:center;gap:.7rem;padding:.7rem .85rem;border:1px solid var(--line);border-radius:var(--r);color:var(--ink);text-decoration:none;font-size:.92rem;font-weight:500;transition:border-color var(--dur-1),background var(--dur-1)}
.ost-actlist a:hover{border-color:var(--blue-200);background:var(--blue-050)}
.ost-actlist a:focus-visible{outline:2px solid var(--blue);outline-offset:2px}
.ost-actlist a .i{display:grid;place-items:center;width:2rem;height:2rem;border-radius:var(--r-sm);background:var(--paper-2);color:var(--blue);flex:none}
.ost-actlist a small{display:block;color:var(--ink-faint);font-size:.78rem;font-weight:400;margin-top:.05rem}
.ost-actlist a.warn .i{color:var(--warn);background:var(--warn-bg)}
.ost-track{background:linear-gradient(135deg,var(--blue-050),#fff 70%);border-color:var(--blue-100)}
@media (max-width:560px){.ost-acts .btn{flex:1 1 auto;justify-content:center}}
@media (max-width:900px){.ost-grid{grid-template-columns:1fr}}
@media (max-width:560px){.ost-tl li{grid-template-columns:2rem 1fr;gap:0 .7rem}.ost-dot{width:2rem;height:2rem;font-size:.78rem}.ost-tl li::before{left:calc(1rem - 1px)}.ost-tl .t{flex-direction:column;gap:.05rem}}

/* carrier scan history */
.trk{margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--blue-100)}
.trk-bar{list-style:none;margin:0 0 1rem;padding:0;display:grid;grid-template-columns:repeat(4,1fr);gap:.35rem;counter-reset:s}
.trk-bar li{position:relative;padding-top:.85rem;font-size:.78rem;font-family:var(--font-h);font-weight:600;color:var(--ink-3);line-height:1.25}
.trk-bar li::before{content:"";position:absolute;top:0;left:0;right:0;height:6px;border-radius:999px;background:var(--line-strong)}
.trk-bar li.is-done::before{background:var(--ok)}
.trk-bar li.is-now::before{background:linear-gradient(90deg,var(--blue) 55%,var(--blue-100) 55%)}
.trk-bar li.is-done,.trk-bar li.is-now{color:var(--ink)}
.trk-bar.is-trouble li.is-now::before{background:var(--warn)}
.trk-est{display:flex;align-items:center;gap:.45rem;margin:0 0 1rem;font-size:.92rem;color:var(--ink-2)}
.trk-est b{color:var(--ink)}
.trk-h{display:flex;align-items:baseline;gap:.6rem;font-size:.98rem;margin:0 0 .6rem;font-family:var(--font-h)}
.trk-h small{font-family:var(--font-b);font-weight:400;color:var(--ink-faint);font-size:.82rem}
.trk-day h4{margin:.9rem 0 .35rem;font-size:.76rem;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-faint);font-family:var(--font-h);font-weight:600}
.trk-day:first-child h4{margin-top:0}
.trk-day ul{list-style:none;margin:0;padding:0}
.trk-ev{display:grid;grid-template-columns:4.6rem minmax(0,1fr);gap:.8rem;position:relative;padding:.1rem 0 .85rem}
.trk-ev::before{content:"";position:absolute;left:5.05rem;top:.45rem;width:9px;height:9px;border-radius:50%;background:#fff;border:2px solid var(--line-strong)}
.trk-ev::after{content:"";position:absolute;left:calc(5.05rem + 4.5px);top:1.15rem;bottom:-.1rem;width:1px;background:var(--line)}
.trk-day:last-child li:last-child::after,.trk-more:not([open]) ~ * .trk-ev::after{display:none}
.trk-ev > div{padding-left:1.1rem;display:grid;gap:.1rem}
.trk-ev time{font-family:var(--font-m);font-size:.8rem;color:var(--ink-3);text-align:right;padding-top:.15rem;white-space:nowrap}
.trk-ev b{font-family:var(--font-h);font-weight:600;font-size:.94rem;color:var(--ink)}
.trk-msg{font-size:.9rem;color:var(--ink-2)}
.trk-place{display:inline-flex;align-items:center;gap:.3rem;font-size:.82rem;color:var(--ink-3)}
.trk-place svg{width:13px;height:13px}
.trk-ev.is-latest::before{border-color:var(--blue);background:var(--blue);box-shadow:0 0 0 4px var(--blue-100)}
.trk-ev--delivered.is-latest::before{border-color:var(--ok);background:var(--ok);box-shadow:0 0 0 4px var(--ok-bg)}
.trk-ev--failure::before,.trk-ev--return_to_sender::before{border-color:var(--warn)}
.trk-more summary{cursor:pointer;color:var(--blue);font-weight:600;font-size:.9rem;padding:.3rem 0 .6rem;list-style:none}
.trk-more summary::-webkit-details-marker{display:none}
.trk-more summary::after{content:" ▾"}.trk-more[open] summary::after{content:" ▴"}
.trk-upd{margin:.4rem 0 0;font-size:.78rem;color:var(--ink-faint)}
@media (max-width:560px){.trk-bar li{font-size:.7rem}.trk-ev{grid-template-columns:3.9rem minmax(0,1fr);gap:.6rem}.trk-ev::before{left:4.3rem}.trk-ev::after{left:calc(4.3rem + 4.5px)}}
</style>
@endpush
@section('content')
<div class="ost">
<section class="ost-hero"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><a href="/track-order">{{ __('Track order') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ $order->order_number }}</span></nav>
  <div class="ost-hero__row">
    <div>
      <span class="kicker">{{ __('Order status') }}</span>
      <h1>{{ __($headline) }}</h1>
      <div class="ost-num">{{ $order->order_number }}</div>
      <p class="ost-meta">{{ __('Placed :date', ['date' => \Carbon\Carbon::parse($order->created_at)->timezone(config('app.timezone'))->isoFormat('LL')]) }} · {{ $isPickup ? __('Local pickup, Florida') : __($order->shipping_label ?: 'Shipping') }} · ${{ number_format($order->total,2) }}</p>
      <div class="ost-acts">
        @if($carrierUrl)<a class="btn btn-primary" href="{{ $carrierUrl }}" target="_blank" rel="noopener"><x-icon name="truck"/> {{ __('Track package') }}</a>@endif
        <a class="btn btn-ghost" href="{{ $actions['invoice'] }}"><x-icon name="download"/> {{ __('Invoice (PDF)') }}</a>
        @if($actions['return'])<a class="btn btn-ghost" href="{{ $actions['return'] }}"><x-icon name="returns"/> {{ __('Return or exchange') }}</a>@endif
        @if($actions['cancel'])<a class="btn btn-ghost" href="{{ $actions['cancel'] }}"><x-icon name="x"/> {{ __('Cancel order') }}</a>@endif
      </div>
    </div>
    <span class="ost-badge ost-badge--{{ $tone }}">{{ __($badge) }}</span>
  </div>
</div></section>

<div class="wrap"><div class="ost-grid">
  <div class="ost-main">
    @if($order->tracking_number)
    <section class="ost-card ost-track" aria-labelledby="sh-h">
      <h2 id="sh-h"><x-icon name="truck"/>{{ __('Shipment') }}</h2>
      <div class="ost-ship">
        <div>
          <div class="ost-ship__lab">{{ __(':carrier tracking number', ['carrier' => $order->tracking_carrier ?: __('Carrier')]) }}</div>
          <div class="ost-ship__num"><span id="trk-num">{{ $order->tracking_number }}</span><button type="button" class="ost-copy" data-copy="{{ $order->tracking_number }}" aria-label="{{ __('Copy tracking number') }}">{{ __('Copy') }}</button></div>
          <p class="ost-ship__svc">@if($svc){{ $svc }} · @endif {{ __('Shipped :date', ['date' => $order->shipped_at ? \Carbon\Carbon::parse($order->shipped_at)->timezone(config('app.timezone'))->isoFormat('LL') : '']) }}@if($order->delivered_at) · {{ __('Delivered :date', ['date' => \Carbon\Carbon::parse($order->delivered_at)->timezone(config('app.timezone'))->isoFormat('LL')]) }}@elseif($order->last_tracking_status) · {{ __($order->last_tracking_status) }}@endif</p>
        </div>
        @if($carrierUrl)<a class="btn btn-primary" href="{{ $carrierUrl }}" target="_blank" rel="noopener">{{ __('Track with :carrier', ['carrier' => $order->tracking_carrier ?: __('carrier')]) }} <x-icon name="arrow-up-right"/></a>@endif
      </div>
      @include('partials.tracking-scans')
    </section>
    @endif
    <section class="ost-card" aria-labelledby="tl-h">
      <h2 id="tl-h"><x-icon name="clock"/>{{ __('Progress') }}</h2>
      <ol class="ost-tl">
        @foreach($steps as $i => $s)
          <li class="is-{{ $s['state'] }}">
            <span class="ost-dot" aria-hidden="true">@if($s['state']==='done')<x-icon name="check"/>@elseif($s['state']==='cancelled')<x-icon name="x"/>@else{{ $i+1 }}@endif</span>
            <div class="t"><b>{{ __($s['title']) }}</b>@if($s['at'])<time datetime="{{ $s['at']->toIso8601String() }}">{{ $fmt($s['at']) }}</time>@elseif($s['state']==='current')<time>{{ __('in progress') }}</time>@endif</div>
            @if($s['sub'])<p>{{ __($s['sub']) }}</p>@endif
          </li>
        @endforeach
      </ol>
    </section>


    @if(($rmas ?? collect())->count())
    <section class="ost-card" aria-labelledby="rma-h">
      <h2 id="rma-h"><x-icon name="returns"/>{{ __('Returns and exchanges') }}</h2>
      @foreach($rmas as $rq)
        @php [$rqState, $rqLine] = \App\Support\Rma::customerState($rq); @endphp
        <div class="ost-rma"><b>{{ __(\App\Support\Rma::typeLabel($rq->type)) }} <span class="mono">{{ $rq->ref }}</span></b> <span class="pill">{{ $rqState }}</span>
          <p>{{ $rqLine }} <span class="muted">{{ __('Filed :date', ['date' => \Carbon\Carbon::parse($rq->created_at)->isoFormat('ll')]) }}</span></p></div>
      @endforeach
    </section>
    @endif
    <section class="ost-card" aria-labelledby="it-h">
      <h2 id="it-h"><x-icon name="package"/>{{ __('What you ordered') }}</h2>
      <ul class="ost-items">
        @foreach($items as $it)
          @php $meta = json_decode($it->meta ?? '{}', true) ?: []; @endphp
          <li><div class="n"><b>{{ $it->name }} × {{ $it->qty }}</b>@if(!empty($meta['summary']))<small>{{ $meta['summary'] }}</small>@endif @if($it->sku)<span class="sku">{{ __('Part #') }} {{ $it->sku }}</span>@endif</div><span class="p">${{ number_format($it->line_total,2) }}</span></li>
        @endforeach
      </ul>
      <div class="ost-tot">
        <div><span>{{ __('Subtotal') }}</span><span>${{ number_format($order->subtotal,2) }}</span></div>
        @if($order->discount_total>0)<div><span>{{ __('Discount') }}{{ $order->coupon_code ? ' ('.$order->coupon_code.')' : (($order->tier_discount ?? 0) > 0 ? ' (' . \App\Services\B2bAccounts::label($order->b2b_tier) . ')' : '') }}</span><span>−${{ number_format($order->discount_total,2) }}</span></div>@endif
        <div><span>{{ __($isPickup ? 'Local pickup' : 'Shipping') }}</span><span>{{ $order->shipping_total>0 ? '$'.number_format($order->shipping_total,2) : __('Free') }}</span></div>
        @if(($order->tax_total ?? 0) > 0)<div><span>{{ __('Sales tax') }}</span><span>${{ number_format($order->tax_total,2) }}</span></div>@endif
        <div class="grand"><span>{{ __('Total') }}</span><span>${{ number_format($order->total,2) }}</span></div>
      </div>
    </section>
  </div>

  <aside class="ost-side">
    <div class="ost-card">
      <h3>{{ __('Manage this order') }}</h3>
      <ul class="ost-actlist">
        <li><a href="{{ $actions['invoice'] }}"><span class="i"><x-icon name="download"/></span><span>{{ __('Download invoice') }}<small>{{ __('PDF, for your records or accounts payable') }}</small></span></a></li>
        @if($actions['return'])<li><a href="{{ $actions['return'] }}"><span class="i"><x-icon name="returns"/></span><span>{{ __('Return a part') }}<small>@if($actions['returnUntil']){{ __('Unused stock parts, until :date', ['date' => $actions['returnUntil']->isoFormat('LL')]) }}@else{{ __('Unused stock parts, within 14 days of delivery') }}@endif</small></span></a></li>@endif
        @if($actions['returnClosed'])<li><a href="/contact"><span class="i"><x-icon name="returns"/></span><span>{{ __('Returns closed on :date', ['date' => $actions['returnClosed']->isoFormat('LL')]) }}<small>{{ __('14 days after delivery. Defective part? Contact us about the warranty.') }}</small></span></a></li>@endif
        @if($actions['exchange'])<li><a href="{{ $actions['exchange'] }}"><span class="i"><x-icon name="returns"/></span><span>{{ __('Exchange a part') }}<small>{{ __('Wrong size or series') }}</small></span></a></li>@endif
        @if($actions['damage'])<li><a class="warn" href="{{ $actions['damage'] }}"><span class="i"><x-icon name="warning"/></span><span>{{ __('Report damage or a wrong item') }}<small>{{ __('Within 5 business days of delivery — we fix it at our cost') }}</small></span></a></li>@endif
        @if($actions['cancel'])<li><a href="{{ $actions['cancel'] }}"><span class="i"><x-icon name="x"/></span><span>{{ __('Cancel this order') }}<small>{{ __('Possible until it ships; custom builds excepted') }}</small></span></a></li>@endif
      </ul>
    </div>

    @if($isPickup)
    <div class="ost-card">
      <h3>{{ __('Pickup') }}</h3>
      <div class="ost-pickup">
        <b>{{ config('uds.pickup.address') }}</b><br>
        {{ __(config('uds.pickup.hours')) }}.<br>
        {!! __('Need another time? Call :phone first so your parts are waiting.', ['phone' => '<a href="'.e(config('uds.pickup.phone_href')).'">'.e(config('uds.pickup.phone')).'</a>']) !!}
        @if($order->payment_method==='pay_at_pickup' && $order->payment_status!=='paid')<br><br><b>{{ __('Payment due at the counter:') }}</b> ${{ number_format($order->total,2) }}. {{ __('Bring your order number.') }}@endif
        <br><a class="textlink" href="{{ config('uds.pickup.maps') }}" target="_blank" rel="noopener"><x-icon name="pin"/> {{ __('Open in Maps') }}</a>
      </div>
    </div>
    @else
    <div class="ost-card">
      <h3>{{ __('Shipping to') }}</h3>
      <address>{!! implode('<br>', array_map('e', $addr)) !!}</address>
      @if($order->signature_required)<p style="margin-top:.6rem">{!! __('<b>Signature required</b> on delivery.') !!}</p>@endif
      @if($order->ship_instructions)<p style="margin-top:.4rem"><span style="color:var(--ink-faint)">{{ __('Delivery instructions:') }}</span> {{ $order->ship_instructions }}</p>@endif
    </div>
    @endif

    <div class="ost-card">
      <h3>{{ __('Details') }}</h3>
      <dl class="ost-kv">
        <dt>{{ __('E-mail') }}</dt><dd>{{ $order->email }}</dd>
        <dt>{{ __('Phone') }}</dt><dd>{{ $order->phone }}</dd>
        @if($order->po_number)<dt>{{ __('PO #') }}</dt><dd>{{ $order->po_number }}</dd>@endif
        <dt>{{ __('Payment') }}</dt><dd>@if($order->payment_status==='paid') {{ __('Paid') }} @elseif($order->payment_method==='pay_at_pickup') {{ __('Due at pickup') }} @elseif($order->payment_method==='net_terms') {{ $order->terms_due_at ? __('Net :d invoice, due :date', ['d' => \App\Services\B2bAccounts::termsDays(), 'date' => \Carbon\Carbon::parse($order->terms_due_at)->translatedFormat('F j, Y')]) : __('Net :d invoice', ['d' => \App\Services\B2bAccounts::termsDays()]) }} @if(\App\Support\PayLink::payable($order)) — <a href="{{ \App\Support\PayLink::url($order) }}"><b>{{ __('Pay $:amount online now', ['amount' => number_format(\App\Support\PayLink::balance($order),2)]) }}</b></a>@endif @elseif($order->payment_status==='refunded') {{ __('Refunded') }} @else {{ __('Pending') }} @if(\App\Support\PayLink::payable($order)) — <a href="{{ \App\Support\PayLink::url($order) }}"><b>{{ __('Pay $:amount online now', ['amount' => number_format(\App\Support\PayLink::balance($order),2)]) }}</b></a>@endif @endif</dd>
      </dl>
      @if(\App\Services\SmsService::on())<div class="ost-sms" style="margin-top:.9rem"><x-icon name="phone"/><span>@if($order->sms_opt_in){!! __('Text updates are <b>on</b>: you will get a text when this order ships and when it is delivered.') !!}@else {{ __('Text updates are off for this order; shipping and delivery updates go to your e-mail.') }}@endif</span></div>@endif
    </div>

    <div class="ost-card">
      <h3>{{ __('Need a hand?') }}</h3>
      <p>{{ __('Quote the order number and we will read you the status, change an address before it ships, or help with a return.') }}</p>
      <a class="textlink" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a><br>
      <a class="textlink" href="mailto:Inquiry@universaldriveshafts.com?subject={{ rawurlencode(__('Order :n', ['n' => $order->order_number])) }}"><x-icon name="mail"/> Inquiry@universaldriveshafts.com</a>
      <p style="margin-top:.8rem"><a class="textlink" href="{{ auth()->check() ? '/account/orders' : '/track-order' }}">{{ __(auth()->check() ? 'All my orders' : 'Look up another order') }} <x-icon name="arrow-right"/></a></p>
    </div>
  </aside>
</div></div>
</div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('.ost-copy').forEach(function(b){b.addEventListener('click',function(){
  var t=b.getAttribute('data-copy');
  (navigator.clipboard?navigator.clipboard.writeText(t):Promise.reject()).then(function(){b.textContent=@json(__('Copied'));setTimeout(function(){b.textContent=@json(__('Copy'))},1600)},function(){var r=document.createRange();r.selectNodeContents(document.getElementById('trk-num'));var s=getSelection();s.removeAllRanges();s.addRange(r)});
});});
</script>
@endpush
