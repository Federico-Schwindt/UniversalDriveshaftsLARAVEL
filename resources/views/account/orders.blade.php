@extends('account.layout')
@section('active','orders')
@section('atitle','Orders')
@section('acct')
<div class="acct-h"><div><h2 class="t">{{ __('Your Orders') }}</h2><p>{{ __('Open any order for tracking, the invoice PDF, and returns or exchanges.') }}</p></div></div>

<form class="osearch" method="get" action="/account/orders" role="search">
  <x-icon name="search"/>
  <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('Search by order #, PO or part number') }}" aria-label="{{ __('Search your orders') }}" autocomplete="off">
  @if($q !== '')<a href="/account/orders" class="osearch-x">{{ __('Clear') }}</a>@endif
  <button class="btn btn-primary btn-sm">{{ __('Search') }}</button>
</form>
@if($q !== '')<p class="osearch-n">{{ trans_choice(':count order matches ":q"|:count orders match ":q"', $orders->total(), ['q' => $q]) }}</p>@endif

<div class="apanel">
  <div class="apanel-b">
    @if($orders->count())
      <div class="ohead" aria-hidden="true"><span>{{ __('Order #') }}</span><span>{{ __('PO') }}</span><span>{{ __('Date') }}</span><span>{{ __('Status') }}</span><span>{{ __('Tracking') }}</span><span>{{ __('Total') }}</span></div>
      <div class="olist">@foreach($orders as $o) @include('account.partials.order-row', ['o' => $o, 'parts' => $lines[$o->id] ?? collect(), 'q' => $q, 'rma' => ($rmas[$o->id] ?? collect())->first()]) @endforeach</div>
      @if($orders->hasPages())
        <nav class="opager" aria-label="{{ __('Order pages') }}" style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:16px;font-size:.9rem;color:var(--ink-3)">
          @if($orders->onFirstPage())<span></span>@else<a class="btn btn-ghost btn-sm" href="{{ $orders->previousPageUrl() }}" rel="prev">{{ __('Newer orders') }}</a>@endif
          <span>{{ __('Page :page of :pages', ['page' => $orders->currentPage(), 'pages' => $orders->lastPage()]) }}</span>
          @if($orders->hasMorePages())<a class="btn btn-ghost btn-sm" href="{{ $orders->nextPageUrl() }}" rel="next">{{ __('Older orders') }}</a>@else<span></span>@endif
        </nav>
      @endif
    @elseif($q !== '')
      <div class="aempty"><span class="ei"><x-icon name="search"/></span>{{ __('No order matches ":q".', ['q' => $q]) }}<br><a href="/account/orders">{{ __('Show all orders') }} <x-icon name="arrow-right"/></a></div>
    @else
      <div class="aempty"><span class="ei"><x-icon name="package"/></span>{{ __("You haven't placed any orders yet.") }}<br><a href="/shop">{{ __('Browse the catalog') }} <x-icon name="arrow-right"/></a></div>
    @endif
  </div>
</div>
@if(empty($user->email_verified_at))<p style="margin:0;font-size:.86rem;color:var(--ink-3)">{{ __('Missing an order? Orders placed with your e-mail before you signed up show here once you confirm your e-mail.') }}</p>@endif
@endsection
