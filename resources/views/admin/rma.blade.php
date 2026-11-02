@extends('admin.layout')
@section('title','Returns / RMA')
@section('crumb','Support')
@section('body')
<div class="chips"><a href="/admin/rma" class="chip @if(!$status) is-on @endif">All</a>@foreach(['new'=>'New','approved'=>'Approved','completed'=>'Completed','rejected'=>'Rejected'] as $k=>$l)<a href="/admin/rma?status={{ $k }}" class="chip @if($status===$k) is-on @endif">{{ $l }}</a>@endforeach <span style="flex:1"></span>@foreach(['return'=>'Returns','cancel'=>'Cancellations','warranty'=>'Warranty'] as $k=>$l)<a href="/admin/rma?type={{ $k }}" class="chip @if($type===$k) is-on @endif">{{ $l }}</a>@endforeach</div>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>RMA</th><th>Customer</th><th>Order</th><th>Product / reason</th><th>Status</th><th>Received</th></tr></thead><tbody>
  @forelse($rows as $r)
  <tr><td class="primary"><span class="title mono">{{ $r->ref }}</span><small><span class="badge badge--plain tiny">{{ $r->type }}</span></small></td>
    <td data-label="Customer" class="small"><b>{{ $r->name }}</b><br>{{ $r->email }}@if($r->phone) · {{ $r->phone }}@endif</td>
    <td data-label="Order" class="mono small">@php $o = \Illuminate\Support\Facades\DB::table('orders')->where('order_number',$r->order_number)->first(); @endphp @if($o)<a href="/admin/orders/{{ $o->id }}">{{ $r->order_number }}</a>@else{{ $r->order_number }}@endif</td>
    <td data-label="Details" class="small"><b>{{ $r->product }}</b> — {{ $r->reason }}@if($r->details)<details><summary style="cursor:pointer;color:var(--blue)">Details</summary><div class="muted" style="white-space:pre-line">{{ $r->details }}</div></details>@endif</td>
    <td class="status-cell"><form method="post" action="/admin/rma/{{ $r->id }}" class="flex">@csrf<select class="select select--sm" name="status" data-autosubmit style="width:auto">@foreach(['new'=>'New','approved'=>'Approved','rejected'=>'Rejected','completed'=>'Completed'] as $k=>$l)<option value="{{ $k }}" @selected($r->status===$k)>{{ $l }}</option>@endforeach</select></form></td>
    <td data-label="Received" class="small muted nowrap">{{ \Carbon\Carbon::parse($r->created_at)->format('M j, g:ia') }}</td></tr>
  @empty<tr><td colspan="6"><div class="empty">{!! ic('undo') !!}<b>No returns</b></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>
@endsection
