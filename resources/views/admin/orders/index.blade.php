@extends('admin.layout')
@section('title','Orders')
@section('crumb','Sales')
@section('actions')
  @if(adm_can('exports'))<a href="/admin/orders?{{ http_build_query(array_merge($f, ['export'=>'csv'])) }}" class="btn btn--sm">{!! ic('download') !!} Export</a>@endif
  <a href="/admin/orders/new" class="btn btn--primary btn--sm">{!! ic('plus') !!} New order</a>
@endsection
@section('body')
@php $tabs = ['all'=>'All','open'=>'Open','unpaid'=>'Unpaid','invoices'=>'Invoices (Net)','to_ship'=>'To ship','pickup'=>'Pickup','shipped'=>'In transit','completed'=>'Completed','cancelled'=>'Cancelled']; @endphp
<div class="chips">
  @foreach($tabs as $k=>$l)<a href="/admin/orders?tab={{ $k }}{{ !empty($f['q']) ? '&q='.urlencode($f['q']) : '' }}" class="chip @if($tab===$k) is-on @endif">{{ $l }} <b>{{ $counts[$k] ?? 0 }}</b></a>@endforeach
</div>
<form class="toolbar" method="get" action="/admin/orders">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Order #, name, e-mail, phone, SKU, tracking, PO…"></div>
  <select class="select" name="payment" data-autosubmit><option value="">Any payment</option>@foreach(\App\Http\Controllers\Admin\OrdersController::PAY as $k=>$l)<option value="{{ $k }}" @selected(($f['payment'] ?? '')===$k)>{{ $l }}</option>@endforeach</select>
  <select class="select" name="source" data-autosubmit><option value="">Any source</option>@foreach(['web'=>'Website','admin'=>'Admin','phone'=>'Phone','wholesale'=>'Wholesale'] as $k=>$l)<option value="{{ $k }}" @selected(($f['source'] ?? '')===$k)>{{ $l }}</option>@endforeach</select>
  <input class="input" type="date" name="from" value="{{ $f['from'] ?? '' }}" data-autosubmit aria-label="From date">
  <input class="input" type="date" name="to" value="{{ $f['to'] ?? '' }}" data-autosubmit aria-label="To date">
  <select class="select" name="sort" data-autosubmit>@foreach(['newest'=>'Newest first','oldest'=>'Oldest first','total'=>'Highest total','total_asc'=>'Lowest total'] as $k=>$l)<option value="{{ $k }}" @selected($sort===$k)>{{ $l }}</option>@endforeach</select>
  <button class="btn btn--sm" type="submit">{!! ic('filter') !!} Filter</button>
  @if(array_filter(array_intersect_key($f, array_flip(['q','payment','source','from','to','tag']))))<a href="/admin/orders?tab={{ $tab }}" class="btn btn--ghost btn--sm">Clear</a>@endif
</form>

<form method="post" action="/admin/orders/bulk" data-bulk class="card">@csrf
  <input type="hidden" name="ids" data-bulk-ids>
  <div class="bulkbar" data-bulkbar style="margin:10px 12px 0;border-radius:10px">
    <b><span data-bulk-count>0</span> selected</b>
    <select class="select select--sm" name="bulk" style="width:auto" required><option value="">Choose action…</option><option value="processing">Mark processing</option><option value="completed">Mark completed</option><option value="paid" data-warn="This records payment without charging anyone.">Mark paid</option><option value="cancelled" data-warn="Paid orders are skipped — refund those from the order page.">Cancel</option><option value="tag">Add tag…</option><option value="print">Print packing slips</option></select>
    <input class="input input--sm" name="tag" placeholder="tag" style="width:120px">
    <button class="btn btn--sm btn--primary" type="submit" formtarget="_self">Apply</button>
  </div>
  <div class="tbl-wrap"><table class="tbl tbl--cards">
    <thead><tr><th class="w-chk"><input type="checkbox" data-check-all aria-label="Select all"></th><th>Order</th><th>Customer</th><th>Items</th><th>Status</th><th>Payment</th><th>Shipping</th><th class="right">Total</th><th>Placed</th></tr></thead>
    <tbody>
    @forelse($rows as $o)
      <tr data-href="/admin/orders/{{ $o->id }}">
        <td class="w-chk"><input type="checkbox" value="{{ $o->id }}" data-check aria-label="Select {{ $o->order_number }}"></td>
        <td class="primary"><span class="title mono">{{ $o->order_number }}</span>@if($o->source && $o->source!=='web') <span class="badge badge--plain tiny">{{ $o->source }}</span>@endif @if($o->tags)<small>{{ $o->tags }}</small>@endif</td>
        <td data-label="Customer"><b>{{ $o->billing_first_name }} {{ $o->billing_last_name }}</b>@if($o->billing_company)<br><span class="muted small">{{ $o->billing_company }}</span>@endif<br><span class="muted small">{{ $o->email }}</span></td>
        <td data-label="Items" class="num">{{ $itemCounts[$o->id] ?? 0 }}</td>
        <td class="status-cell"><span class="badge badge--{{ $o->status }}">{{ ucfirst($o->status) }}</span></td>
        <td data-label="Payment"><span class="badge badge--{{ $o->payment_status }} badge--plain">{{ ucfirst(str_replace('_',' ',$o->payment_status)) }}</span>@if(($o->payment_method ?? '') === 'net_terms' && $o->payment_status === 'unpaid' && $o->terms_due_at)<br><small class="{{ $o->terms_due_at < now('America/New_York')->toDateString() ? 'text-err' : 'muted' }}" style="{{ $o->terms_due_at < now('America/New_York')->toDateString() ? 'color:var(--err,#c0392b);font-weight:600' : '' }}">Net · due {{ \Carbon\Carbon::parse($o->terms_due_at)->format('M j') }}</small>@endif</td>
        <td data-label="Shipping" class="small">@if($o->shipping_method===\App\Services\CartService::PICKUP_ID)<span class="badge badge--warn badge--plain">Pickup</span>@elseif($o->tracking_number)<span class="badge badge--shipped badge--plain">{{ $o->delivered_at ? 'Delivered' : 'Shipped' }}</span> <span class="mono muted">{{ $o->tracking_carrier }}</span>@else{{ \Illuminate\Support\Str::limit($o->shipping_label, 28) }}@endif</td>
        <td class="right num" data-label="Total"><b>{{ money($o->total) }}</b>@if($o->refund_total>0)<br><span class="tiny" style="color:var(--err)">-{{ money($o->refund_total) }}</span>@endif</td>
        <td data-label="Placed" class="muted small nowrap">{{ \Carbon\Carbon::parse($o->created_at)->format('M j, g:ia') }}</td>
      </tr>
    @empty<tr><td colspan="9"><div class="empty">{!! ic('orders') !!}<b>No orders here</b>Try another tab or clear the filters.</div></td></tr>@endforelse
    </tbody></table></div>
  @include('admin.partials.pager')
</form>
@endsection
