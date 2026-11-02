@extends('admin.layout')
@section('title','Inventory')
@section('crumb','Catalog')
@section('sub')Stock on hand per SKU, edited here. @endsection
@section('actions')@if(adm_can('exports'))<a href="/admin/inventory?{{ http_build_query(array_merge($f, ['export'=>'csv'])) }}" class="btn btn--sm">{!! ic('download') !!} Export</a>@endif @endsection
@section('body')
<div class="chips">
  @foreach(['' => ['All tracked', $counts['tracked']], 'out'=>['Out of stock',$counts['out']], 'critical'=>['At / below reorder',$counts['critical']], 'low'=>['Getting low',$counts['low']], 'ok'=>['Healthy',null], 'untracked'=>['Not tracked',$counts['untracked']]] as $k=>[$l,$n])
    <a href="/admin/inventory?status={{ $k }}{{ !empty($f['q']) ? '&q='.urlencode($f['q']) : '' }}" class="chip @if($filter===$k) is-on @endif">{{ $l }}@if($n!==null) <b>{{ $n }}</b>@endif</a>
  @endforeach
</div>
<form class="toolbar" method="get" action="/admin/inventory">
  <input type="hidden" name="status" value="{{ $filter }}">
  <div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="SKU or name…"></div>
  <select class="select" name="cat" data-autosubmit><option value="">All categories</option>@foreach($cats as $c)<option @selected(($f['cat'] ?? '')===$c)>{{ $c }}</option>@endforeach</select>
  <select class="select" name="source" data-autosubmit><option value="">Any source</option><option value="manual" @selected(($f['source'] ?? '')==='manual')>Manual</option><option value="auto" @selected(($f['source'] ?? '')==='auto')>Auto</option></select>
  <select class="select" name="sort" data-autosubmit>@foreach(['urgent'=>'Most urgent','sku'=>'SKU','name'=>'Name','stock_desc'=>'Most stock'] as $k=>$l)<option value="{{ $k }}" @selected($sort===$k)>{{ $l }}</option>@endforeach</select>
  <button class="btn btn--sm">{!! ic('filter') !!} Filter</button>
</form>
<form method="post" action="/admin/inventory/bulk" data-bulk class="card">@csrf
  <input type="hidden" name="ids" data-bulk-ids>
  <div class="bulkbar" data-bulkbar style="margin:10px 12px 0;border-radius:10px"><b><span data-bulk-count>0</span> selected</b><select class="select select--sm" name="bulk" style="width:auto" required><option value="">Choose action…</option><option value="reorder">Set reorder point…</option><option value="track">Track</option><option value="untrack">Stop tracking</option></select><input class="input input--sm" name="value" type="number" placeholder="qty" style="width:90px"><button class="btn btn--sm btn--primary">Apply</button></div>
  <div class="tbl-wrap"><table class="tbl tbl--cards">
    <thead><tr><th class="w-chk"><input type="checkbox" data-check-all></th><th class="w-img"></th><th>Item</th><th class="center">On hand</th><th class="center">Reorder at</th><th class="center">Target</th><th>Source</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($rows as $i)
      <tr data-inv="{{ $i->id }}">
        <td class="w-chk"><input type="checkbox" value="{{ $i->id }}" data-check></td>
        <td class="w-img">@if($i->image)<img src="{{ $i->image }}" class="thumb" alt="" loading="lazy">@endif</td>
        <td class="primary"><a href="/admin/products/{{ $i->wp_product_id }}" class="title mono" style="color:inherit">{{ $i->sku }}</a><small>{{ \Illuminate\Support\Str::limit($i->name,60) }} @if($i->category)<span class="muted">· {{ $i->category }}</span>@endif @if($i->price) · {{ money($i->price) }} @endif</small></td>
        <td class="center" data-label="On hand">@if($i->source==='manual')<input class="inline-input num center" type="number" min="0" value="{{ $i->stock }}" data-f="stock" style="width:74px">@else<b class="num">{{ $i->stock }}</b>@endif</td>
        <td class="center" data-label="Reorder"><input class="inline-input num center" type="number" min="0" value="{{ $i->reorder_at }}" data-f="reorder_at" style="width:70px" placeholder="—"></td>
        <td class="center" data-label="Target"><input class="inline-input num center" type="number" min="0" value="{{ $i->target_qty }}" data-f="target_qty" style="width:70px" placeholder="—"></td>
        <td data-label="Source"><select class="select select--sm" data-f="source" style="width:auto"><option value="manual" @selected($i->source==='manual')>Manual</option><option value="auto" @selected($i->source==='auto')>Auto</option></select></td>
        <td class="status-cell"><span class="badge badge--{{ ['out'=>'err','critical'=>'err','low'=>'warn','ok'=>'ok','off'=>'plain'][$i->st] }}" data-st>{{ ['out'=>'Out','critical'=>'Reorder','low'=>'Low','ok'=>'OK','off'=>'Off'][$i->st] }}</span></td>
        <td class="actions"><button type="button" class="btn btn--xs" data-modal="adj-{{ $i->id }}">± Adjust</button> <a href="/admin/inventory/{{ $i->id }}/history" class="btn btn--xs btn--icon" title="History">{!! ic('history','ic--s') !!}</a>
          <template data-modal-src="adj-{{ $i->id }}"><form method="post" action="/admin/inventory/{{ $i->id }}/adjust">@csrf<div class="modal__head"><h3>Adjust {{ $i->sku }}</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close>{!! ic('x') !!}</button></div><div class="modal__body form-grid"><div class="field"><label>Change <span class="muted">(now {{ $i->stock }})</span></label><input class="input" type="number" name="delta" placeholder="+10 received / -2 damaged" required></div><div class="field"><label>Reason</label><input class="input" name="reason" list="reasons" required><datalist id="reasons"><option value="Received from supplier"><option value="Cycle count"><option value="Damaged / scrapped"><option value="Used in build"><option value="Returned by customer"><option value="Sold at counter"></datalist></div></div><div class="modal__foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn--primary">Apply</button></div></form></template>
        </td>
      </tr>
    @empty<tr><td colspan="9"><div class="empty">{!! ic('layers') !!}<b>Nothing here</b></div></td></tr>@endforelse
    </tbody></table></div>
  @include('admin.partials.pager')
</form>
@endsection
@push('scripts')
<script>
document.querySelectorAll('tr[data-inv] [data-f]').forEach(function (el) {
  el.addEventListener('change', function () {
    var tr = el.closest('tr'), body = {}; body[el.dataset.f] = el.value === '' ? null : el.value;
    el.disabled = true;
    admPost('/admin/inventory/' + tr.dataset.inv, body).then(function (d) { el.disabled = false; var map = { out: ['err', 'Out'], critical: ['err', 'Reorder'], low: ['warn', 'Low'], ok: ['ok', 'OK'], off: ['plain', 'Off'] }; var b = tr.querySelector('[data-st]'); b.className = 'badge badge--' + map[d.status][0]; b.textContent = map[d.status][1]; admToast('Saved', 'ok'); }).catch(function () { el.disabled = false; admToast('Could not save', 'err'); });
  });
});
</script>
@endpush
