@extends('admin.layout')
@section('title','Abandoned carts')
@section('crumb','Sales')
@section('sub')Carts left with an e-mail get a reminder at 1 h and 24 h. Stock alerts and quote requests from the storefront are listed here too. @endsection
@section('body')
<div class="kpis mb">
  <div class="kpi"><span class="kpi__label">Open carts</span><span class="kpi__value">{{ $stats['open'] }}</span></div>
  <div class="kpi kpi--ok"><span class="kpi__label">Recovered</span><span class="kpi__value">{{ $stats['recovered'] }}</span><span class="kpi__delta">{{ money($stats['recovered_value']) }}</span></div>
  <div class="kpi"><span class="kpi__label">Stock alerts waiting</span><span class="kpi__value">{{ $stats['alerts_open'] }}</span></div>
  <div class="kpi"><span class="kpi__label">Quote requests</span><span class="kpi__value">{{ $quotes->count() }}</span></div>
</div>
<div class="tabs" data-tabs><a href="#carts" data-tab="carts">Carts</a><a href="#alerts" data-tab="alerts">Stock alerts</a><a href="#quotes" data-tab="quotes">Quote requests</a></div>
<div class="tabpanel" data-tabpanel="carts"><div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Customer</th><th>Cart</th><th class="right">Value</th><th>Status</th><th>Last seen</th></tr></thead><tbody>
  @forelse($carts as $c)<tr><td class="primary"><span class="title">{{ $c->email ?: 'anonymous' }}</span>@if($c->phone)<small>{{ $c->phone }}</small>@endif</td><td data-label="Cart" class="small">{{ $c->qty }} item(s): {{ \Illuminate\Support\Str::limit(collect($c->lines)->pluck('sku')->filter()->implode(', '), 60) }}</td><td class="right num" data-label="Value"><b>{{ money($c->subtotal) }}</b></td><td class="status-cell"><span class="badge badge--{{ $c->status==='recovered'?'ok':($c->status==='waiting'||$c->status==='reminded'?'warn':'plain') }}">{{ $c->status }}</span> @if($c->reminders_sent)<span class="muted tiny">{{ $c->reminders_sent }} sent</span>@endif</td><td data-label="Seen" class="small muted">{{ \Carbon\Carbon::parse($c->last_seen_at)->diffForHumans() }}</td></tr>
  @empty<tr><td colspan="5"><div class="empty">{!! ic('cart') !!}<b>No abandoned carts</b></div></td></tr>@endforelse</tbody></table></div></div></div>
<div class="tabpanel" data-tabpanel="alerts" hidden><div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Customer</th><th>Wants</th><th>Status</th><th>Requested</th></tr></thead><tbody>
  @forelse($alerts as $a)<tr><td class="primary"><span class="title">{{ $a->email }}</span>@if($a->phone)<small>{{ $a->phone }}</small>@endif</td><td data-label="Wants"><span class="badge badge--plain tiny">{{ $a->kind }}</span> {{ $a->label }} <span class="mono muted small">{{ $a->ref }}</span>@if($a->vehicle)<br><span class="small muted">{{ $a->vehicle }}</span>@endif</td><td class="status-cell">@if($a->notified_at)<span class="badge badge--ok">Notified</span>@else<span class="badge badge--warn">Waiting</span>@endif</td><td data-label="Requested" class="small muted">{{ \Carbon\Carbon::parse($a->created_at)->format('M j, Y') }}</td></tr>
  @empty<tr><td colspan="4"><div class="empty">No stock alerts.</div></td></tr>@endforelse</tbody></table></div></div></div>
<div class="tabpanel" data-tabpanel="quotes" hidden><div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Customer</th><th>Part / vehicle</th><th>Status</th><th>Requested</th></tr></thead><tbody>
  @forelse($quotes as $q)<tr data-href="/admin/leads/{{ $q->id }}"><td class="primary"><span class="title">{{ $q->name ?: $q->email }}</span><small>{{ $q->email }} {{ $q->phone }}</small></td><td data-label="Part" class="small">{{ $q->d['part'] ?? $q->d['sku'] ?? '' }} {{ $q->d['vehicle'] ?? '' }} {{ \Illuminate\Support\Str::limit($q->message, 60) }}</td><td class="status-cell"><span class="badge badge--{{ $q->status }}">{{ $q->status }}</span></td><td data-label="Requested" class="small muted">{{ \Carbon\Carbon::parse($q->created_at)->format('M j, g:ia') }}</td></tr>
  @empty<tr><td colspan="4"><div class="empty">No quote requests.</div></td></tr>@endforelse</tbody></table></div></div></div>
@endsection
