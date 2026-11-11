@extends('admin.layout')
@section('title','Returns / RMA')
@section('crumb','Support')
@section('body')
@php
  $tabs = ['open' => 'Open'] + array_map(fn ($s) => $s[0], \App\Support\Rma::STATUSES) + ['all' => 'All'];
  $qs = fn (array $over) => '/admin/rma?' . http_build_query(array_filter($over + ['status' => $status, 'type' => $type, 'q' => $search], fn ($v) => $v !== null && $v !== ''));
@endphp
<div class="chips">
  @foreach($tabs as $k => $l)
    <a href="{{ $qs(['status' => $k]) }}" class="chip @if($status === $k) is-on @endif">{{ $l }}@if($k !== 'all' && ($counts[$k] ?? 0)) <b>{{ $counts[$k] }}</b>@endif</a>
  @endforeach
  <span style="flex:1"></span>
  @foreach(['' => 'All types', 'return' => 'Returns', 'exchange' => 'Exchanges', 'cancel' => 'Cancellations'] as $k => $l)
    <a href="{{ $qs(['type' => $k]) }}" class="chip @if((string) $type === $k) is-on @endif">{{ $l }}</a>
  @endforeach
</div>
<form method="get" action="/admin/rma" class="flex mb" style="max-width:520px">
  <input type="hidden" name="status" value="{{ $status }}">@if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
  <input class="input grow" name="q" value="{{ $search }}" placeholder="RMA, order number, name, e-mail or part">
  <button class="btn btn--sm">{!! ic('search') !!} Search</button>
</form>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>RMA</th><th>Customer</th><th>Order</th><th>Part / reason</th><th>Status</th><th>Filed</th></tr></thead><tbody>
  @forelse($rows as $r)
  <tr data-href="/admin/rma/{{ $r->id }}">
    <td class="primary"><a class="title mono" href="/admin/rma/{{ $r->id }}">{{ $r->ref }}</a><small><span class="badge badge--plain tiny">{{ \App\Support\Rma::typeLabel($r->type) }}</span></small></td>
    <td data-label="Customer" class="small"><b>{{ $r->name }}</b><br>{{ $r->email }}</td>
    <td data-label="Order" class="mono small">@if($r->order_id)<a href="/admin/orders/{{ $r->order_id }}">{{ $r->order_number }}</a><br><span class="muted">{{ $r->order_status }} · {{ money($r->order_total) }}</span>@else{{ $r->order_number ?: '—' }}@endif</td>
    <td data-label="Part / reason" class="small">@if($r->product)<b>{{ \Illuminate\Support\Str::limit($r->product, 60) }}</b><br>@endif{{ $r->reason }}</td>
    <td data-label="Status"><span class="{{ \App\Support\Rma::badge($r->status) }}">{{ \App\Support\Rma::label($r->status) }}</span>@if($r->resolution)<br><small class="muted">{{ \App\Support\Rma::RESOLUTIONS[$r->resolution] ?? '' }}</small>@endif</td>
    <td data-label="Filed" class="small muted nowrap">{{ \Carbon\Carbon::parse($r->created_at)->format('M j, g:ia') }}<br>{{ \Carbon\Carbon::parse($r->created_at)->diffForHumans(null, true) }} ago</td>
  </tr>
  @empty<tr><td colspan="6"><div class="empty">{!! ic('undo') !!}<b>No requests here</b></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>
@endsection
