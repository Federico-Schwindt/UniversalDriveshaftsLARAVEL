@extends('admin.layout')
@section('title','Coupons')
@section('crumb','Sales')
@section('actions')<a href="/admin/coupons/new" class="btn btn--primary btn--sm">{!! ic('plus') !!} New coupon</a>@endsection
@section('body')
<div class="chips">@foreach(['active'=>'Active','expired'=>'Expired','inactive'=>'Inactive','all'=>'All'] as $k=>$l)<a href="/admin/coupons?tab={{ $k }}" class="chip @if($tab===$k) is-on @endif">{{ $l }} <b>{{ $counts[$k] }}</b></a>@endforeach</div>
<form class="toolbar" method="get" action="/admin/coupons"><input type="hidden" name="tab" value="{{ $tab }}"><div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Code or description…"></div><button class="btn btn--sm">Search</button></form>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Code</th><th>Discount</th><th>Rules</th><th class="center">Used</th><th class="right">Revenue</th><th>Valid</th><th>Status</th><th></th></tr></thead><tbody>
  @forelse($rows as $c) @php $r = json_decode($c->restrictions ?: '{}', true) ?: []; $u = $usage[strtoupper($c->code)] ?? null; $expired = $c->expires_at && \Carbon\Carbon::parse($c->expires_at)->isPast(); @endphp
  <tr data-href="/admin/coupons/{{ $c->id }}">
    <td class="primary"><span class="title mono">{{ $c->code }}</span>@if($c->description)<small>{{ $c->description }}</small>@endif</td>
    <td data-label="Discount"><b>@if($c->type==='percent'){{ rtrim(rtrim($c->amount,'0'),'.') }}% off @elseif($c->type==='fixed_cart'){{ money($c->amount) }} off @else Free shipping @endif</b>@if($c->free_shipping && $c->type!=='free_shipping') <span class="badge badge--plain tiny">+ free ship</span>@endif</td>
    <td data-label="Rules" class="small muted">@if($c->minimum_amount)min {{ money($c->minimum_amount) }} @endif @if($c->usage_limit)· limit {{ $c->usage_limit }} @endif @if(!empty($r['categories']))· {{ count($r['categories']) }} categor{{ count($r['categories'])==1?'y':'ies' }} @endif @if(!empty($r['skus']))· {{ count($r['skus']) }} SKU(s) @endif @if(!empty($r['wholesale_only']))· wholesale only @endif</td>
    <td class="center num" data-label="Used">{{ $u->n ?? $c->used_count }}</td>
    <td class="right num" data-label="Revenue">{{ money($u->t ?? 0) }} <span class="muted small">(-{{ money($u->d ?? 0) }})</span></td>
    <td data-label="Valid" class="small">{{ $c->starts_at ? \Carbon\Carbon::parse($c->starts_at)->format('M j') . ' → ' : '' }}{{ $c->expires_at ? \Carbon\Carbon::parse($c->expires_at)->format('M j, Y') : 'no expiry' }}</td>
    <td class="status-cell">@if(!$c->active)<span class="badge">Inactive</span>@elseif($expired)<span class="badge badge--err">Expired</span>@else<span class="badge badge--ok">Active</span>@endif</td>
    <td class="actions"><a href="#" data-post="/admin/coupons/{{ $c->id }}/toggle" class="btn btn--xs">{{ $c->active ? 'Deactivate' : 'Activate' }}</a></td>
  </tr>
  @empty<tr><td colspan="8"><div class="empty">{!! ic('ticket') !!}<b>No coupons</b><a href="/admin/coupons/new">Create one</a></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>
@endsection
