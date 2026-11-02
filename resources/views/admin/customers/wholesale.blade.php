@extends('admin.layout')
@section('title','Wholesale')
@section('crumb','Sales')
@section('sub')Approve shop and fleet accounts; the discount applies automatically at checkout. @endsection
@section('body')
<div class="chips">@foreach(['pending'=>'Pending','approved'=>'Approved','all'=>'All'] as $k=>$l)<a href="/admin/wholesale?tab={{ $k }}" class="chip @if($tab===$k) is-on @endif">{{ $l }}@isset($counts[$k]) <b>{{ $counts[$k] }}</b>@endisset</a>@endforeach</div>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Customer</th><th>Orders</th><th>Group</th><th class="center">Discount</th><th>Status</th><th></th></tr></thead><tbody>
  @forelse($rows as $u)
  <tr><td class="primary"><a href="/admin/customers/{{ $u->id }}" class="title" style="color:inherit">{{ $u->name }}</a><small>{{ $u->email }}@if($u->company) · {{ $u->company }}@endif @if($u->phone) · {{ $u->phone }}@endif</small></td>
    <td data-label="Orders" class="small">{{ $stats[$u->email]->n ?? 0 }} · {{ money($stats[$u->email]->t ?? 0) }}</td>
    <form method="post" action="/admin/customers/{{ $u->id }}">@csrf<input type="hidden" name="name" value="{{ $u->name }}"><input type="hidden" name="email" value="{{ $u->email }}"><input type="hidden" name="phone" value="{{ $u->phone }}"><input type="hidden" name="company" value="{{ $u->company }}"><input type="hidden" name="tags" value="{{ $u->tags }}">
    <td data-label="Group"><input class="input input--sm" name="wholesale_group" value="{{ $u->wholesale_group }}" list="grp" style="width:130px"></td>
    <td class="center" data-label="Discount"><div class="input-group" style="width:110px"><input class="input input--sm num" type="number" step="0.5" min="0" max="90" name="wholesale_discount" value="{{ $u->wholesale_discount }}"><span class="addon">%</span></div></td>
    <td class="status-cell"><select class="select select--sm" name="wholesale_status" style="width:auto"><option value="pending" @selected($u->wholesale_status==='pending')>Pending</option><option value="approved" @selected($u->wholesale_status==='approved')>Approved</option><option value="none" @selected($u->wholesale_status==='none')>Declined / retail</option></select></td>
    <td class="actions"><button class="btn btn--sm btn--primary">Save</button></td></form>
  </tr>
  @empty<tr><td colspan="6"><div class="empty">{!! ic('handshake') !!}<b>No wholesale accounts here</b></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>
<datalist id="grp">@foreach($groups as $g)<option value="{{ $g }}">@endforeach</datalist>
@endsection
