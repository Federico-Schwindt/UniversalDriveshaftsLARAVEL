@extends('admin.layout')
@section('title','Activity log')
@section('crumb','Insights')
@section('sub')Everything admins changed, newest first. @endsection
@section('body')
<form class="toolbar" method="get" action="/admin/activity"><div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Order #, SKU, text…"></div><select class="select" name="user" data-autosubmit><option value="">Anyone</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(($f['user'] ?? '')==$u->id)>{{ $u->name }}</option>@endforeach</select><select class="select" name="action" data-autosubmit><option value="">All actions</option>@foreach($actions as $a)<option value="{{ $a }}" @selected(($f['action'] ?? '')===$a)>{{ ucfirst($a) }}</option>@endforeach</select><button class="btn btn--sm">Filter</button></form>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards tbl--compact"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>What</th></tr></thead><tbody>
@forelse($rows as $a) @php $link = match($a->subject_type){ 'order'=>'/admin/orders/'.$a->subject_id, 'product'=>'/admin/products/'.$a->subject_id, 'driveshaft'=>'/admin/driveshafts/'.rawurlencode((string)$a->subject_id), 'customer'=>'/admin/customers/'.$a->subject_id, 'coupon'=>'/admin/coupons/'.$a->subject_id, 'lead'=>'/admin/leads/'.$a->subject_id, 'category'=>'/admin/categories/'.$a->subject_id, default=>null }; @endphp
<tr @if($link && $a->subject_id) data-href="{{ $link }}" @endif><td class="small nowrap muted" data-label="When">{{ \Carbon\Carbon::parse($a->created_at)->format('M j, g:ia') }}</td><td data-label="Who" class="small">{{ $a->user_name ?: 'System' }}</td><td data-label="Action"><span class="badge badge--plain tiny">{{ $a->action }}</span></td><td class="primary">{{ $a->label }}</td></tr>
@empty<tr><td colspan="4"><div class="empty">Nothing logged yet.</div></td></tr>@endforelse
</tbody></table></div>@include('admin.partials.pager')</div>
@endsection
