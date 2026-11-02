@extends('admin.layout')
@section('title','Products')
@section('crumb','Catalog')
@section('actions')
  @if(adm_can('catalog_write'))<button type="button" class="btn btn--sm" data-modal="import">{!! ic('upload') !!} Import CSV</button>@endif
  @if(adm_can('exports'))<a href="/admin/products?{{ http_build_query(array_merge($f, ['export'=>'csv'])) }}" class="btn btn--sm">{!! ic('download') !!} Export</a>@endif
  <a href="/admin/products/new" class="btn btn--primary btn--sm">{!! ic('plus') !!} New product</a>
@endsection
@section('body')
@php $tabs = ['all'=>'All','published'=>'Published','draft'=>'Drafts / hidden','out'=>'Out of stock','edited'=>'Edited here','nosku'=>'No SKU','noimage'=>'No image']; @endphp
<div class="chips">@foreach($tabs as $k=>$l)<a href="/admin/products?tab={{ $k }}{{ !empty($f['q']) ? '&q='.urlencode($f['q']) : '' }}{{ !empty($f['cat']) ? '&cat='.urlencode($f['cat']) : '' }}" class="chip @if($tab===$k) is-on @endif">{{ $l }}@isset($counts[$k]) <b>{{ $counts[$k] }}</b>@endisset</a>@endforeach</div>
<form class="toolbar" method="get" action="/admin/products">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="SKU, name or ID…"></div>
  <select class="select" name="cat" data-autosubmit style="max-width:240px"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c['slug'] }}" @selected(($f['cat'] ?? '')===$c['slug'])>{{ $c['name'] }} ({{ $c['count'] }})</option>@endforeach</select>
  <select class="select" name="sort" data-autosubmit>@foreach(['newest'=>'Newest','modified'=>'Recently edited','name'=>'Name A–Z','sku'=>'SKU','price'=>'Price ↑','price_desc'=>'Price ↓','stock'=>'Stock ↑'] as $k=>$l)<option value="{{ $k }}" @selected($sort===$k)>{{ $l }}</option>@endforeach</select>
  <button class="btn btn--sm">{!! ic('filter') !!} Filter</button>
  @if(!empty($f['q']) || !empty($f['cat']))<a href="/admin/products?tab={{ $tab }}" class="btn btn--ghost btn--sm">Clear</a>@endif
</form>
<form method="post" action="/admin/products/bulk" data-bulk class="card">@csrf
  <input type="hidden" name="ids" data-bulk-ids>
  @if(adm_can('catalog_write'))
  <div class="bulkbar" data-bulkbar style="margin:10px 12px 0;border-radius:10px">
    <b><span data-bulk-count>0</span> selected</b>
    <select class="select select--sm" name="bulk" id="pb-act" style="width:auto" required><option value="">Choose action…</option><option value="publish">Publish</option><option value="draft">Hide (draft)</option><option value="price" data-warn="Prices change on the live store right away.">Change price…</option><option value="stock">Set stock…</option><option value="category">Add to category…</option><option value="delete" data-warn="Deleted products cannot be restored.">Delete</option></select>
    <select class="select select--sm" name="mode" style="width:auto"><option value="pct">by %</option><option value="add">by $ amount</option><option value="set">set to $</option><option value="replace">replace categories</option></select>
    <input class="input input--sm" name="value" placeholder="value / slug" style="width:150px" list="pb-cats"><datalist id="pb-cats">@foreach($categories as $c)<option value="{{ $c['slug'] }}">{{ $c['name'] }}</option>@endforeach</datalist>
    <button class="btn btn--sm btn--primary" type="submit">Apply</button>
  </div>
  @endif
  <div class="tbl-wrap"><table class="tbl tbl--cards">
    <thead><tr><th class="w-chk"><input type="checkbox" data-check-all aria-label="Select all"></th><th class="w-img"></th><th>Product</th><th>Category</th><th class="right">Price</th><th class="center">Stock</th><th>Status</th><th class="hide-m">Edited</th></tr></thead>
    <tbody>
    @forelse($rows as $p)
      <tr data-href="/admin/products/{{ $p->ID }}">
        <td class="w-chk"><input type="checkbox" value="{{ $p->ID }}" data-check></td>
        <td class="w-img">@if($p->image)<img src="{{ $p->image }}" class="thumb" alt="" loading="lazy">@else<span class="thumb" style="display:grid;place-items:center;color:var(--ink-4)">{!! ic('image','ic--s') !!}</span>@endif</td>
        <td class="primary"><span class="title mono">{{ $p->sku ?: '—' }}</span><small>{{ \Illuminate\Support\Str::limit(html_entity_decode($p->post_title), 70) }}</small>@if($p->locked) <span class="badge badge--info badge--plain tiny" title="Edited in this admin; the live-store sync leaves it alone">edited</span>@endif</td>
        <td data-label="Category" class="small">{{ implode(', ', $p->cats) ?: '—' }}</td>
        <td class="right num" data-label="Price"><input class="inline-input num right" type="number" step="0.01" min="0" value="{{ $p->price }}" data-quick="regular_price" data-id="{{ $p->ID }}" aria-label="Price" data-stop></td>
        <td class="center num" data-label="Stock">@if($p->stock !== null)<input class="inline-input num center" type="number" min="0" value="{{ (int) $p->stock }}" data-quick="stock" data-id="{{ $p->ID }}" aria-label="Stock" data-stop style="width:70px">@else<span class="badge badge--{{ $p->stock_status==='outofstock'?'err':'ok' }} badge--plain">{{ $p->stock_status==='outofstock'?'Out':'In stock' }}</span>@endif @if($p->pti_stock !== null)<small class="muted" style="display:block" title="Powertrain Industries on hand">PTI {{ $p->pti_stock }}</small>@endif</td>
        <td class="status-cell"><span class="badge badge--{{ $p->post_status==='publish'?'ok':'warn' }}">{{ $p->post_status==='publish'?'Published':ucfirst($p->post_status) }}</span></td>
        <td class="hide-m muted small nowrap">{{ \Carbon\Carbon::parse($p->post_modified)->diffForHumans(null, true) }} ago</td>
      </tr>
    @empty<tr><td colspan="8"><div class="empty">{!! ic('box') !!}<b>No products match</b>Change the filters or create a product.</div></td></tr>@endforelse
    </tbody></table></div>
  @include('admin.partials.pager')
</form>
<template data-modal-src="import">
  <form method="post" action="/admin/products/import" enctype="multipart/form-data">@csrf
    <div class="modal__head"><h3>Import / update from CSV</h3><button type="button" class="btn btn--xs btn--icon" data-modal-close>{!! ic('x') !!}</button></div>
    <div class="modal__body stack">
      <p class="small">Rows are matched by <b>sku</b>. Existing products are updated with the columns present; unknown SKUs are created (name required). Columns: <code class="mono">sku, name, regular_price, sale_price, cost, stock, weight, status, description, categories</code> (slugs separated by <code>|</code>) and any attribute as <code class="mono">pa_series, pa_style…</code></p>
      <input class="input" type="file" name="file" accept=".csv,text/csv" required>
    </div>
    <div class="modal__foot"><button type="button" class="btn" data-modal-close>Cancel</button><button class="btn btn--primary">Import</button></div>
  </form>
</template>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-quick]').forEach(function (i) {
  var orig = i.value;
  i.addEventListener('change', function () {
    if (i.value === orig) return;
    i.disabled = true;
    admPost('/admin/products/' + i.dataset.id + '/quick', { field: i.dataset.quick, value: i.value }).then(function () { orig = i.value; i.disabled = false; admToast('Saved', 'ok'); }).catch(function (e) { i.disabled = false; i.value = orig; admToast((e && e.message) || 'Could not save', 'err'); });
  });
});
</script>
@endpush
