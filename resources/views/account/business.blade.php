@extends('account.layout')
@section('active','business')
@section('atitle','Business account')
@section('acct')
<div class="acct-h"><div><h2 class="t">{{ __('Business account') }}</h2><p>{{ __('Your tier discount, what you bought this month and your invoices.') }}</p></div></div>

@if(!$st)
  <div class="apanel"><div class="apanel-b">
    <div class="biz-pitch">
      <p>{{ __('Approved business accounts get 5% off every order from the first day, 8% (Silver) once a month\'s purchases reach $5,500, 10% (Gold) from $7,000, and can pay by invoice on Net 20 terms with a PO number.') }}</p>
      @if(($user->wholesale_status ?? '') === 'pending')
        <span class="chip chip--warn">{{ __('Application under review. We will e-mail you.') }}</span>
      @else
        <a class="btn btn-primary" href="/wholesale">{{ __('Apply for a business account') }} <x-icon name="arrow-right"/></a>
      @endif
    </div>
  </div></div>
@else
@php
  $t = $st['tiers']; $cur = $st['tier']; $order = ['bronze', 'silver', 'gold'];
  $pct = fn ($k) => rtrim(rtrim(number_format($t[$k]['pct'], 2), '0'), '.');
  $max = $t['gold']['min']; $fill = min(100, $max > 0 ? $st['mtd'] / $max * 100 : 0);
@endphp
<div class="apanel">
  <div class="apanel-h"><h2><x-icon name="certificate"/> {{ __('Your tier') }}</h2><span class="biz-badge biz-badge--{{ $cur }}">{{ $t[$cur]['name'] }} · {{ __(':pct% off', ['pct' => $pct($cur)]) }}</span></div>
  <div class="apanel-b">
    <ol class="biz-ladder">
      @foreach($order as $k)
        <li class="{{ $k === $cur ? 'is-cur' : '' }} {{ array_search($k, $order) < array_search($cur, $order) ? 'is-past' : '' }}">
          <b>{{ $t[$k]['name'] }}</b><span class="biz-pct">{{ $pct($k) }}%</span>
          <small>{{ $k === 'bronze' ? __('From approval') : __(':amount+ a month', ['amount' => '$' . number_format($t[$k]['min'])]) }}</small>
        </li>
      @endforeach
    </ol>
    <div class="biz-meter" role="img" aria-label="{{ __(':amount bought in :month', ['amount' => '$' . number_format($st['mtd'], 2), 'month' => __($st['month'])]) }}">
      <div class="biz-meter__track"><i style="width:{{ $fill }}%"></i>
        <span class="biz-meter__tick" style="left:{{ $t['silver']['min'] / $max * 100 }}%"></span>
      </div>
      <div class="biz-meter__lbl"><span>{{ __(':month so far', ['month' => __($st['month'])]) }} <b>${{ number_format($st['mtd'], 2) }}</b></span><span>${{ number_format($max) }}</span></div>
    </div>
    <ul class="biz-facts">
      @if($st['next'])<li>{!! __(':amount more this month reaches :tier (:pct% off), applied the moment you cross it.', ['amount' => '<b>$' . number_format($st['toNext'], 2) . '</b>', 'tier' => '<b>' . $t[$st['next']]['name'] . '</b>', 'pct' => $pct($st['next'])]) !!}</li>@endif
      @if($cur !== 'bronze')
        @if($st['toKeep'] > 0)<li>{!! __('To keep :tier in :next, reach :amount in :month (:left to go).', ['tier' => $t[$cur]['name'], 'next' => __($st['nextMonth']), 'amount' => '$' . number_format($t[$cur]['min']), 'month' => __($st['month']), 'left' => '<b>$' . number_format($st['toKeep'], 2) . '</b>']) !!}</li>
        @else<li>{!! __(':tier is locked in for :next.', ['tier' => '<b>' . $t[$cur]['name'] . '</b>', 'next' => __($st['nextMonth'])]) !!}</li>@endif
      @endif
      <li class="muted">{{ __('Counts merchandise on paid and invoiced orders in a calendar month (Eastern time), after discounts, without shipping or tax. The discount appears as one line in your cart and checkout. It does not stack with a coupon: the larger one applies.') }}</li>
    </ul>
    @if(count($log))
    @php
      // the log stores English (B2bAccounts::evaluate): translate the known shapes, keep the amount
      $tierWhy = function ($w) {
        $w = (string) $w;
        if (preg_match('/^This month reached (\$[\d,.]+)$/', $w, $m)) return __('This month reached :amount', ['amount' => $m[1]]);
        if (preg_match('/^Last month closed at (\$[\d,.]+)$/', $w, $m)) return __('Last month closed at :amount', ['amount' => $m[1]]);
        return __($w);
      };
    @endphp
    <details class="biz-log"><summary>{{ __('Tier history') }}</summary>
      <ul>@foreach($log as $l)<li><span>{{ \Carbon\Carbon::parse($l->created_at)->isoFormat('ll') }}</span> {{ ucfirst($l->from_tier ?: '—') }} → <b>{{ ucfirst($l->to_tier) }}</b> <span class="muted">{{ $tierWhy($l->reason) }}</span></li>@endforeach</ul>
    </details>
    @endif
  </div>
</div>

<div class="apanel">
  <div class="apanel-h"><h2><x-icon name="calendar"/> {{ __('Invoices, Net :days', ['days' => $terms['days']]) }}</h2>
    <span class="chip {{ $terms['ok'] ? 'chip--ok' : 'chip--warn' }}">@if($terms['ok']){{ __('Available at checkout') }}@elseif($terms['why'] === 'overdue'){{ __('Paused: invoice past due') }}@elseif($terms['why'] === 'limit'){{ __('Credit limit reached') }}@else{{ __('Not turned on') }}@endif</span></div>
  <div class="apanel-b">
    <div class="biz-credit">
      <div><small>{{ __('Credit limit') }}</small><b>${{ number_format($terms['limit'], 2) }}</b></div>
      <div><small>{{ __('Open balance') }}</small><b>${{ number_format($terms['open'], 2) }}</b></div>
      <div><small>{{ __('Available') }}</small><b>${{ number_format(max(0, $terms['limit'] - $terms['open']), 2) }}</b></div>
    </div>
    @if(count($invoices))
      <div class="biz-inv">
        @foreach($invoices as $o)
          @php $late = $o->terms_due_at && $o->terms_due_at < now('America/New_York')->toDateString(); @endphp
          <div class="biz-inv__row {{ $late ? 'is-late' : '' }}">
            <div><a href="/account/orders/{{ $o->order_number }}"><b>{{ $o->order_number }}</b></a>@if($o->po_number)<span class="muted"> · PO {{ $o->po_number }}</span>@endif
              <small>@if(!$o->terms_due_at){{ __('Due') }} —@else{{ $late ? __('Past due since :date', ['date' => \Carbon\Carbon::parse($o->terms_due_at)->isoFormat('ll')]) : __('Due :date', ['date' => \Carbon\Carbon::parse($o->terms_due_at)->isoFormat('ll')]) }}@endif</small></div>
            <b class="biz-inv__amt">${{ number_format(\App\Support\PayLink::balance($o), 2) }}</b>
            <a class="btn btn-primary btn-sm" href="{{ \App\Support\PayLink::url($o) }}">{{ __('Pay') }}</a>
          </div>
        @endforeach
      </div>
    @else
      <p class="muted" style="margin:0">{{ __('No open invoices. At checkout choose "Invoice, Net :days" and add your PO number.', ['days' => $terms['days']]) }}</p>
    @endif
  </div>
</div>
@endif
@endsection
