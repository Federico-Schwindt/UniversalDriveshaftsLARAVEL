@extends('account.layout')
@section('active','dashboard')
@section('atitle','Overview')
@section('acct')
@php
  $first = explode(' ', trim($user->name ?: __('there')))[0];
  $open = $orders->filter(fn ($o) => in_array(\App\Http\Controllers\AccountController::orderState($o)[0], ['pending', 'processing', 'shipped']))->count();
  $last = $orders->first();
@endphp

<div class="acct-h"><div><h2 class="t">{{ __('Welcome back, :name', ['name' => $first]) }}</h2><p>{{ __('Your orders, addresses and saved builds in one place.') }}</p></div></div>

@php
  $txOk = (bool) \App\Support\SalesTax::certificate($user);
  $tx = $user->tax_exempt_status;
  $txBadge = $txOk ? ['Tax exempt', 'ok'] : ($tx === 'pending' ? ['Under review', 'warn'] : ($tx === 'approved' ? ['Expired', 'warn'] : ($tx === 'rejected' ? ['Not accepted', 'warn'] : null)));
  $isB2b = ($user->wholesale_status ?? 'none') === 'approved';
  // every section of the account in one place, with what is waiting in each (owner, 2026-11-13: "so every user finds the tools and sections easily")
  $sections = [
    ['/account/orders', 'truck', 'Orders & tracking', 'Status, tracking and invoices', $open ? [trans_choice(':n open|:n open', $open, ['n' => $open]), 'blue'] : null],
    ['/account/buy-again', 'refresh', 'Buy again', 'Reorder parts you bought before', null],
    ['/rma', 'returns', 'Returns and RMA', 'Start a return, exchange or cancellation', null],
    ['/account/addresses', 'pin', 'Addresses', 'Saved ship-to addresses for checkout', $addressCount ? [$addressCount, 'plain'] : null],
    ['/account/designs', 'sliders', 'Driveshaft designs', 'Builds saved in the online builder', $designs->count() ? [$designs->count(), 'plain'] : null],
    ['/account/requests', 'mail', 'Custom requests', 'Your custom driveshaft quotes', $requests->count() ? [$requests->count(), 'plain'] : null],
    ['/account/business', 'storefront', 'Business account', $isB2b ? 'Tier discount, spend and invoices' : 'Shop pricing and Net terms for businesses', $isB2b ? ['Wholesale', 'blue'] : null],
    ['/account/settings#tax', 'certificate', 'Sales tax certificate', 'Upload your resale certificate to buy tax-free', $txBadge],
    ['/account/settings', 'lock', 'Profile & security', 'Name, e-mail, password and saved cards', null],
  ];
@endphp
<nav class="ahub" aria-label="{{ __('My account') }}">
  @foreach($sections as [$href, $icon, $title, $desc, $badge])
    <a class="ahub-i" href="{{ $href }}"><span class="ahub-ic"><x-icon :name="$icon"/></span><span class="ahub-t"><b>{{ __($title) }}</b><span>{{ __($desc) }}</span></span>@if($badge)<span class="ahub-b ahub-b--{{ $badge[1] }}">{{ is_int($badge[0]) ? $badge[0] : __($badge[0]) }}</span>@endif<x-icon name="caret-right"/></a>
  @endforeach
</nav>

<div class="alinks">
  <a class="alink" href="/shop"><x-icon name="cart"/> {{ __('Shop parts') }}</a>
  <a class="alink" href="/online-builder"><x-icon name="wrench"/> {{ __('Online builder') }}</a>
  <a class="alink" href="/quick-order"><x-icon name="gauge"/> {{ __('Quick Order by SKU') }}</a>
  <a class="alink" href="/contact"><x-icon name="headset"/> {{ __('Talk to a tech') }}</a>
</div>

@if($last)
@php [$sk, $sl, $reached] = \App\Http\Controllers\AccountController::orderState($last); $stopped = in_array($sk, ['cancelled', 'refunded']); @endphp
<div class="apanel">
  <div class="apanel-h"><h2><x-icon name="package"/> {{ __('Latest order') }}</h2><a class="more" href="{{ \App\Support\OrderEvents::statusUrl($last, false) }}">{{ __('Details, invoice & returns') }} <x-icon name="arrow-right"/></a></div>
  <div class="apanel-b">
    <div class="alast">
      <div><div class="no">{{ $last->order_number }}</div><div class="sub">{{ \Carbon\Carbon::parse($last->created_at)->isoFormat('ll') }} · <span class="pill pill-{{ $sk }}">{{ __($sl) }}</span></div></div>
      <div class="tot">${{ number_format($last->total, 2) }}</div>
      @unless($stopped)
      <ol class="asteps" aria-label="{{ __('Order progress') }}">
        @foreach(['Placed', 'Paid', 'Shipped', 'Delivered'] as $i => $step)
          <li class="{{ $reached > $i ? 'done' : '' }}" @if($reached === $i + 1) aria-current="step" @endif>{{ __($step) }}</li>
        @endforeach
      </ol>
      @endunless
    </div>
    @if($last->tracking_number)
      <p style="margin:14px 0 0;font-size:.88rem;color:var(--ink-3)">{{ __('Tracking') }}: <a href="{{ \App\Support\OrderEvents::carrierUrl($last->tracking_carrier, $last->tracking_number) }}" target="_blank" rel="noopener" style="color:var(--blue);font-weight:600">{{ strtoupper((string) $last->tracking_carrier) }} {{ $last->tracking_number }}</a></p>
    @endif
  </div>
</div>
@endif

<div class="apanel">
  <div class="apanel-h"><h2><x-icon name="clock"/> {{ __('Recent orders') }}</h2>@if($orders->count() > 1)<a class="more" href="/account/orders">{{ __('View all') }} <x-icon name="arrow-right"/></a>@endif</div>
  <div class="apanel-b">
    @if($orders->count())
      <div class="ohead" aria-hidden="true"><span>{{ __('Order #') }}</span><span>{{ __('PO') }}</span><span>{{ __('Date') }}</span><span>{{ __('Status') }}</span><span>{{ __('Tracking') }}</span><span>{{ __('Total') }}</span></div>
      <div class="olist">@foreach($orders->take(5) as $o) @include('account.partials.order-row', ['o' => $o]) @endforeach</div>
    @else
      <div class="aempty"><span class="ei"><x-icon name="package"/></span>{{ __("You haven't placed any orders yet.") }}<br><a href="/shop">{{ __('Browse the catalog') }} <x-icon name="arrow-right"/></a></div>
    @endif
  </div>
</div>

<div class="acol">
  <div class="apanel">
    <div class="apanel-h"><h2><x-icon name="pin"/> {{ __('Default shipping address') }}</h2><a class="more" href="/account/addresses">{{ __('Manage') }} <x-icon name="arrow-right"/></a></div>
    <div class="apanel-b">
      @if($defaultAddress)
        <div style="line-height:1.55;font-size:.93rem">
          <b>{{ $defaultAddress->name ?: $defaultAddress->company }}</b>@if($defaultAddress->name && $defaultAddress->company)<br>{{ $defaultAddress->company }}@endif<br>
          {{ $defaultAddress->street }}@if($defaultAddress->unit), {{ $defaultAddress->unit }}@endif<br>
          {{ $defaultAddress->city }}@if($defaultAddress->state), {{ $defaultAddress->state }}@endif {{ $defaultAddress->postcode }} · {{ $defaultAddress->country }}
        </div>
      @else
        <div class="aempty"><span class="ei"><x-icon name="pin"/></span>{{ __('Save an address and checkout fills it in for you.') }}<br><a href="/account/addresses?edit=new">{{ __('Add an address') }} <x-icon name="arrow-right"/></a></div>
      @endif
    </div>
  </div>

  @if($designs->count())
  <div class="apanel">
    <div class="apanel-h"><h2><x-icon name="sliders"/> {{ __('Saved designs') }}</h2>@if($designs->count())<a class="more" href="/account/designs">{{ __('All') }} <x-icon name="arrow-right"/></a>@endif</div>
    <div class="apanel-b">
      @if($designs->count())
        <div class="mini">
          @foreach($designs->take(3) as $d)@php $cfg = json_decode($d->config, true) ?: []; @endphp
            <div class="mini-row">
              <div class="mt"><b>{{ $d->title }}</b><span>{{ \Illuminate\Support\Str::limit($cfg['summary'] ?? '', 44) }}</span></div>
              <span class="mprice">${{ number_format($d->price, 2) }}</span>
              <a href="/account/designs/{{ $d->ref }}/load" class="btn btn-primary btn-sm">{{ __('Open') }}</a>
            </div>
          @endforeach
        </div>
      @else
        <div class="aempty"><span class="ei"><x-icon name="sliders"/></span>{{ __('No saved builds yet.') }}<br><a href="/online-builder">{{ __('Online builder') }} <x-icon name="arrow-right"/></a></div>
      @endif
    </div>
  </div>
  @endif
</div>
@endsection
