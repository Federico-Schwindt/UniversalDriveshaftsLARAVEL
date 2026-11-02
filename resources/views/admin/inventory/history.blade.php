@extends('admin.layout')
@section('title', $row->sku)
@section('crumb')<a href="/admin/inventory">Inventory</a> › history @endsection
@section('sub'){{ $row->name }} · {{ $row->stock }} on hand · source {{ $row->source }}@endsection
@section('body')
<div class="grid grid--2">
  <div class="card"><div class="card__head"><h2>Adjustments</h2></div><div class="tbl-wrap"><table class="tbl tbl--compact"><thead><tr><th>When</th><th>Who</th><th>Change</th><th>Result</th><th>Reason</th></tr></thead><tbody>@forelse($adj as $a)<tr><td class="small nowrap">{{ \Carbon\Carbon::parse($a->created_at)->format('M j, Y g:ia') }}</td><td class="small">{{ $a->user_name ?: 'System' }}</td><td class="num" style="color:{{ $a->delta>=0?'var(--ok)':'var(--err)' }}">{{ $a->delta>=0?'+':'' }}{{ $a->delta }}</td><td class="num">{{ $a->before }} → <b>{{ $a->after }}</b></td><td class="small">{{ $a->reason }}</td></tr>@empty<tr><td colspan="5" class="muted" style="padding:16px">No adjustments recorded.</td></tr>@endforelse</tbody></table></div></div>
  <div class="card"><div class="card__head"><h2>Sold</h2></div><div class="tbl-wrap"><table class="tbl tbl--compact"><thead><tr><th>Order</th><th>Date</th><th class="right">Qty</th></tr></thead><tbody>@forelse($sold as $s)<tr data-href="/admin/orders/{{ $s->id }}"><td class="mono">{{ $s->order_number }}</td><td class="small">{{ \Carbon\Carbon::parse($s->created_at)->format('M j, Y') }}</td><td class="right num">{{ $s->qty }}</td></tr>@empty<tr><td colspan="3" class="muted" style="padding:16px">Never sold on the new store.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
