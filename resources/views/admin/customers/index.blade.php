@extends('admin.layout')
@section('title','Customers')
@section('crumb','Sales')
@section('actions')@if(adm_can('exports'))<a href="/admin/customers?{{ http_build_query(array_merge($f, ['export'=>'csv'])) }}" class="btn btn--sm">{!! ic('download') !!} Export</a>@endif<a href="/admin/customers/new" class="btn btn--primary btn--sm">{!! ic('plus') !!} New customer</a>@endsection
@section('body')
<div class="chips">@foreach(['all'=>'Accounts','new'=>'New (30d)','repeat'=>'Repeat buyers','wholesale'=>'Wholesale','pending'=>'Wholesale pending','guests'=>'Guest checkouts'] as $k=>$l)<a href="/admin/customers?tab={{ $k }}" class="chip @if($tab===$k) is-on @endif">{{ $l }}@isset($counts[$k]) <b>{{ $counts[$k] }}</b>@endisset</a>@endforeach</div>
<form class="toolbar" method="get" action="/admin/customers"><input type="hidden" name="tab" value="{{ $tab }}"><div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Name, e-mail, phone, company, tag…"></div>
  @if($tab!=='guests')<select class="select" name="sort" data-autosubmit>@foreach(['newest'=>'Newest','name'=>'Name','spent'=>'Most spent','orders'=>'Most orders','last'=>'Recent order'] as $k=>$l)<option value="{{ $k }}" @selected(($sort ?? 'newest')===$k)>{{ $l }}</option>@endforeach</select>@endif
  <button class="btn btn--sm">{!! ic('filter') !!} Filter</button></form>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Customer</th><th>Contact</th><th class="center">Orders</th><th class="right">Spent</th><th>Last order</th><th>Type</th></tr></thead>
  <tbody>
  @forelse($rows as $u)
    <tr data-href="/admin/customers/{{ $tab==='guests' ? urlencode($u->email) : $u->id }}">
      <td class="primary"><span class="flex gap-2"><span class="avatar" style="width:32px;height:32px;font-size:13px">{{ strtoupper(mb_substr($u->name ?: $u->email,0,1)) }}</span><span><span class="title">{{ $u->name ?: '—' }}</span>@if($u->company)<small>{{ $u->company }}</small>@endif</span></span></td>
      <td data-label="Contact" class="small">{{ $u->email }}@if($u->phone)<br><span class="muted">{{ $u->phone }}</span>@endif</td>
      <td class="center num" data-label="Orders">{{ $u->orders_n ?? 0 }}</td>
      <td class="right num" data-label="Spent"><b>{{ money($u->spent ?? 0) }}</b></td>
      <td data-label="Last" class="small muted">{{ $u->last_order ? \Carbon\Carbon::parse($u->last_order)->format('M j, Y') : '—' }}</td>
      <td class="status-cell">@if($tab==='guests')<span class="badge badge--plain">Guest</span>@elseif($u->wholesale_status==='approved')<span class="badge badge--ok">Wholesale {{ rtrim(rtrim($u->wholesale_discount,'0'),'.') }}%</span>@elseif($u->wholesale_status==='pending')<span class="badge badge--warn">Wholesale pending</span>@else<span class="badge badge--plain">Retail</span>@endif @if(!empty($u->tags))<br><small class="muted">{{ $u->tags }}</small>@endif</td>
    </tr>
  @empty<tr><td colspan="6"><div class="empty">{!! ic('users') !!}<b>No customers match</b></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>
@endsection
