@extends('admin.layout')
@section('title', $c ? $c->code : 'New coupon')
@section('crumb')<a href="/admin/coupons">Coupons</a>@endsection
@section('actions') @if($c)<a href="#" class="btn btn--danger btn--sm" data-post="/admin/coupons/{{ $c->id }}/delete" data-confirm="Delete coupon {{ $c->code }}?">{!! ic('trash') !!} Delete</a>@endif @endsection
@section('body')
@php $r = $c->r ?? []; $v = fn($k,$d='') => old($k, $c->$k ?? $d); @endphp
<form method="post" action="{{ $c ? '/admin/coupons/'.$c->id : '/admin/coupons' }}" class="grid grid--main" data-savebar="#savebar">@csrf
  <div class="stack">
    <div class="card"><div class="card__body form-grid">
      <div class="field"><label>Code</label><input class="input mono" name="code" value="{{ $v('code') }}" required style="text-transform:uppercase" placeholder="SPRING10"></div>
      <div class="field"><label>Description <span class="muted">(internal)</span></label><input class="input" name="description" value="{{ $v('description') }}"></div>
      <div class="field"><label>Type</label><select class="select" name="type" id="ctype">@foreach(\App\Http\Controllers\Admin\CouponsController::TYPES as $k=>$l)<option value="{{ $k }}" @selected($v('type','percent')===$k)>{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label>Amount</label><div class="input-group"><span class="addon" id="camt-pre">$</span><input class="input num" type="number" step="0.01" min="0" name="amount" value="{{ $v('amount') }}"></div></div>
      <div class="field"><label>Minimum order</label><div class="input-group"><span class="addon">$</span><input class="input num" type="number" step="0.01" name="minimum_amount" value="{{ $v('minimum_amount') }}" placeholder="none"></div></div>
      <div class="field"><label>Maximum discount <span class="muted">(percent coupons)</span></label><div class="input-group"><span class="addon">$</span><input class="input num" type="number" step="0.01" name="maximum_amount" value="{{ $v('maximum_amount') }}" placeholder="no cap"></div></div>
      <div class="field"><label>Starts</label><input class="input" type="date" name="starts_at" value="{{ $c && $c->starts_at ? \Carbon\Carbon::parse($c->starts_at)->toDateString() : old('starts_at') }}"></div>
      <div class="field"><label>Expires</label><input class="input" type="date" name="expires_at" value="{{ $c && $c->expires_at ? \Carbon\Carbon::parse($c->expires_at)->toDateString() : old('expires_at') }}"></div>
      <div class="field"><label>Total uses</label><input class="input num" type="number" min="1" name="usage_limit" value="{{ $v('usage_limit') }}" placeholder="unlimited"></div>
      <div class="field"><label>Uses per customer</label><input class="input num" type="number" min="1" name="per_customer_limit" value="{{ $v('per_customer_limit') }}" placeholder="unlimited"></div>
      <label class="check span-2"><input type="checkbox" name="free_shipping" value="1" @checked($v('free_shipping'))> Also grants free shipping <small>Tubing and complete driveshafts still pay their carrier rate.</small></label>
    </div></div>
    <div class="card"><div class="card__head"><h2>Restrictions</h2></div><div class="card__body form-grid">
      <div class="field span-2"><label>Only these categories</label><div style="max-height:180px;overflow:auto;border:1px solid var(--line);border-radius:10px;padding:8px 10px;columns:2;column-gap:12px">@foreach($categories as $cat)<label class="check" style="break-inside:avoid;gap:6px"><input type="checkbox" name="categories[]" value="{{ $cat['slug'] }}" @checked(in_array($cat['slug'], old('categories', $r['categories'] ?? [])))> {{ $cat['name'] }}</label>@endforeach</div></div>
      <div class="field span-2"><label>Only these SKUs</label><textarea class="input mono" name="skus" rows="2" placeholder="5-1310X, 2-4-1911X…">{{ old('skus', implode(', ', $r['skus'] ?? [])) }}</textarea></div>
      <label class="check"><input type="checkbox" name="exclude_sale" value="1" @checked(old('exclude_sale', $r['exclude_sale'] ?? false))> Exclude sale items</label>
      <label class="check"><input type="checkbox" name="wholesale_only" value="1" @checked(old('wholesale_only', $r['wholesale_only'] ?? false))> Wholesale accounts only</label>
    </div></div>
  </div>
  <div class="stack sticky">
    <div class="card"><div class="card__body stack"><label class="switch"><input type="checkbox" name="active" value="1" @checked($c ? $c->active : true)><i></i> Active</label><button class="btn btn--primary btn--block">{{ $c ? 'Save coupon' : 'Create coupon' }}</button></div></div>
    @if($c && isset($orders) && $orders->count())<div class="card"><div class="card__head"><h2>Recent uses</h2></div><div class="card__body card__body--flush">@foreach($orders as $o)<a href="/admin/orders/{{ $o->id }}" class="stat-row" style="padding:8px 16px;color:inherit;text-decoration:none"><span class="mono">{{ $o->order_number }}</span><span class="small">-{{ money($o->discount_total) }} <span class="muted">· {{ \Carbon\Carbon::parse($o->created_at)->format('M j') }}</span></span></a>@endforeach</div></div>@endif
  </div>
  <div class="savebar" id="savebar" style="grid-column:1/-1"><span>Unsaved changes</span><button class="btn btn--primary btn--sm">Save</button></div>
</form>
@endsection
@push('scripts')<script>(function(){var t=document.getElementById('ctype'),p=document.getElementById('camt-pre');function u(){p.textContent=t.value==='percent'?'%':'$';p.closest('.field').style.opacity=t.value==='free_shipping'?.4:1}t.addEventListener('change',u);u();})();</script>@endpush
