@extends('admin.layout')
@section('title', $p['id'] ? ($p['sku'] ?: 'Product #'.$p['id']) : 'New product')
@section('crumb')<a href="/admin/products">Products</a>@if($p['id']) › #{{ $p['id'] }}@endif @endsection
@if($p['id']) @section('sub'){{ $p['name'] }}@endsection @endif
@section('actions')
  @if($publicUrl)<a href="{{ $publicUrl }}" target="_blank" class="btn btn--sm">{!! ic('eye') !!} View on site</a>@endif
  @if($p['id'])
  <div class="adm-menu" data-menu><button type="button" class="btn btn--sm" data-menu-btn>{!! ic('dots') !!}</button>
    <div class="adm-menu__pop adm-menu__pop--right" hidden>
      <a href="#" data-post="/admin/products/{{ $p['id'] }}/duplicate">{!! ic('copy') !!} Duplicate</a>
      <a href="/admin/products/new?from={{ $p['id'] }}">{!! ic('plus') !!} New product like this</a>
      <a href="/admin/orders?q={{ urlencode($p['sku']) }}">{!! ic('orders') !!} Orders with this SKU</a>
      <div class="adm-menu__sep"></div>
      @if(adm_can('catalog_write'))<a href="#" class="danger" data-post="/admin/products/{{ $p['id'] }}/delete" data-confirm="Delete {{ $p['sku'] }} permanently? Orders keep their line items.">{!! ic('trash') !!} Delete product</a>@endif
    </div></div>
  @endif
@endsection
@section('body')
<form method="post" action="{{ $p['id'] ? '/admin/products/'.$p['id'] : '/admin/products' }}" data-savebar="#savebar" class="grid grid--main" id="pf">@csrf
  <div class="stack">
    <div class="card"><div class="card__body form-grid">
      <div class="field span-2"><label>Product name</label><input class="input" name="name" value="{{ old('name', $p['name']) }}" required placeholder="1310 Series U-Joint, Greaseable"></div>
      <div class="field"><label>SKU <span class="muted">(part number)</span></label><input class="input mono" name="sku" value="{{ old('sku', $p['sku']) }}" required></div>
      <div class="field"><label>URL slug</label><input class="input mono" name="slug" value="{{ old('slug', $p['slug']) }}" placeholder="auto from name"></div>
      <div class="field span-2"><label>Description</label><textarea class="input" name="description" rows="6">{{ old('description', $p['description']) }}</textarea><span class="hint">Plain text or simple HTML. Shown on the product page; also used for search.</span></div>
      <div class="field span-2"><label>Short description <span class="muted">(category tables, feeds)</span></label><input class="input" name="short" value="{{ old('short', $p['short']) }}"></div>
    </div></div>

    <div class="card">
      <div class="card__head"><h2>Images</h2><span class="muted small">First image is the main one — drag to reorder</span></div>
      <div class="card__body stack" data-uploader data-url="/admin/media/upload" data-name="images[]">
        <div class="media-grid" data-grid>@foreach(old('images', $p['images']) as $u)<div class="media-item" draggable="true"><img src="{{ media_url($u, 320) }}" alt=""><input type="hidden" name="images[]" value="{{ $u }}"><button type="button" class="rm" aria-label="Remove">{!! ic('x','ic--s') !!}</button></div>@endforeach</div>
        <label class="dropzone">{!! ic('upload') !!}<div><b>Drop images here</b> or tap to choose · JPG, PNG, WebP up to 12 MB · shrunk to 1600px</div><input type="file" accept="image/*" multiple></label>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Pricing &amp; stock</h2></div>
      <div class="card__body form-grid form-grid--3">
        <div class="field"><label>Regular price</label><div class="input-group"><span class="addon">$</span><input class="input num" type="number" step="0.01" min="0" name="regular_price" value="{{ old('regular_price', $p['regular_price']) }}"></div></div>
        <div class="field"><label>Sale price</label><div class="input-group"><span class="addon">$</span><input class="input num" type="number" step="0.01" min="0" name="sale_price" value="{{ old('sale_price', $p['sale_price']) }}"></div></div>
        <div class="field"><label>Cost <span class="muted">(private, for margin)</span></label><div class="input-group"><span class="addon">$</span><input class="input num" type="number" step="0.01" min="0" name="cost" value="{{ old('cost', $p['cost']) }}"></div></div>
        <div class="field"><label class="switch"><input type="checkbox" name="manage_stock" value="1" @checked(old('manage_stock', $p['manage_stock']))><i></i> Track stock quantity</label></div>
        <div class="field"><label>Stock on hand</label><input class="input num" type="number" name="stock" value="{{ old('stock', $p['stock']) }}">@if($inv && $inv->source==='auto')<span class="hint">Synced automatically — edits here are overwritten.</span>@endif</div>
        @if($pti && $pti->stock_synced_at)
        @php $stores = ['12' => 'NV', '2' => 'VA']; $by = json_decode($pti->stock_by_store ?: '[]', true) ?: []; @endphp
        <div class="field"><label>Powertrain Industries stock</label>
          <div class="input num" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--paper-2)"><b>{{ $pti->stock_total }}</b> on hand
            @foreach($by as $sid => $q)<span class="muted small">{{ $stores[$sid] ?? 'Store '.$sid }} {{ $q }}</span>@endforeach
            @if($pti->can_build)<span class="muted small">+{{ $pti->can_build }} can build</span>@endif</div>
          <span class="hint">PTI part {{ $pti->part_number }} · updated {{ \Carbon\Carbon::parse($pti->stock_synced_at)->diffForHumans() }}@if($pti->match === 'created') · this product's stock follows PTI @endif</span></div>
        @endif
        <div class="field"><label>Backorders</label><select class="select" name="backorders">@foreach(['no'=>'Do not allow','notify'=>'Allow, notify customer','yes'=>'Allow'] as $k=>$l)<option value="{{ $k }}" @selected(old('backorders', $p['backorders'])===$k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Stock status <span class="muted">(when not tracking qty)</span></label><select class="select" name="stock_status">@foreach(['instock'=>'In stock','outofstock'=>'Out of stock','onbackorder'=>'On backorder'] as $k=>$l)<option value="{{ $k }}" @selected(old('stock_status', $p['stock_status'])===$k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field span-2"><label class="check"><input type="checkbox" name="sold_by_foot" value="1" @checked(old('sold_by_foot', $p['sold_by_foot']))> Sold by the foot <small>Tubing priced per 1" — shows the by-the-foot popup on the product page.</small></label></div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Shipping</h2></div>
      <div class="card__body form-grid form-grid--3">
        <div class="field"><label>Weight (lb)</label><input class="input num" type="number" step="0.001" min="0" name="weight" value="{{ old('weight', $p['weight']) }}"><span class="hint">Drives live UPS rates and box packing (≥35 lb ships alone).</span></div>
        <div class="field"><label>Length (in)</label><input class="input num" type="number" step="0.01" min="0" name="length" value="{{ old('length', $p['length']) }}"></div>
        <div class="field"><label>Width × Height (in)</label><div class="input-group"><input class="input num" type="number" step="0.01" min="0" name="width" value="{{ old('width', $p['width']) }}" placeholder="W"><input class="input num" type="number" step="0.01" min="0" name="height" value="{{ old('height', $p['height']) }}" placeholder="H"></div></div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Attributes &amp; measurements</h2><span class="muted small">These drive the category table columns, filters and “alternative parts”</span></div>
      <div class="card__body">
        @php $vals = old('attrs', array_map(fn($v)=>implode('|',$v), $p['attrs'])); @endphp
        <div id="attrs">
        @foreach($order as $tax)
          @php $info = $allAttrs[$tax] ?? ['label'=>ucwords(str_replace(['pa_','-'],['',' '],$tax))]; @endphp
          <div class="attr-row">
            <span class="lab" title="{{ $tax }}">{{ $info['label'] }}</span>
            <input class="input input--sm" name="attrs[{{ $tax }}]" value="{{ $vals[$tax] ?? '' }}" list="dl-{{ $tax }}" autocomplete="off" data-tax="{{ $tax }}" placeholder="—">
            <datalist id="dl-{{ $tax }}"></datalist>
            <button type="button" class="btn btn--xs btn--icon" data-rm-attr title="Clear">{!! ic('x','ic--s') !!}</button>
          </div>
        @endforeach
        </div>
        <div id="attrs-new"></div>
        <div class="flex flex-wrap mt">
          <select class="select select--sm" id="attr-pick" style="width:auto;max-width:260px"><option value="">Add attribute…</option>@foreach($allAttrs as $tax=>$i) @continue(in_array($tax,$order))<option value="{{ $tax }}">{{ $i['label'] }} <span class="muted">({{ $i['products'] }})</span></option>@endforeach<option value="__new">+ New attribute…</option></select>
          <span class="hint">Multiple values: separate with <code>|</code>. Values are shared across products, so keep spelling consistent (datalist suggests existing ones).</span>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card__head"><h2>Cross-reference &amp; SEO</h2></div>
      <div class="card__body form-grid">
        <div class="field span-2"><label>Interchange / OEM numbers</label><input class="input mono" name="interchange" value="{{ old('interchange', $p['interchange']) }}" placeholder="5-153X, 1310, GMB 210-0153, …"><span class="hint">Comma-separated. Searchable on the storefront (cross-reference search).</span></div>
        <div class="field span-2"><label>SEO title <span class="muted">(blank = automatic)</span></label><input class="input" name="seo_title" value="{{ old('seo_title', $p['seo_title']) }}" maxlength="160"></div>
        <div class="field span-2"><label>Meta description</label><textarea class="input" name="seo_desc" rows="2" maxlength="320">{{ old('seo_desc', $p['seo_desc']) }}</textarea></div>
      </div>
    </div>
  </div>

  <div class="stack sticky">
    <div class="card">
      <div class="card__head"><h2>Visibility</h2></div>
      <div class="card__body stack">
        <div class="field"><label>Status</label><select class="select" name="status">@foreach(['publish'=>'Published — visible in the store','draft'=>'Draft — hidden','private'=>'Private — hidden, keeps URL'] as $k=>$l)<option value="{{ $k }}" @selected(old('status', $p['status'])===$k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Categories</label>
          <div style="max-height:260px;overflow:auto;border:1px solid var(--line);border-radius:10px;padding:8px 10px" class="stack" >
            @php $sel = old('categories', $p['categories']); @endphp
            @foreach($categories as $c)<label class="check" style="gap:7px"><input type="checkbox" name="categories[]" value="{{ $c['slug'] }}" @checked(in_array($c['slug'],$sel))> {{ $c['name'] }} <span class="muted small">{{ $c['count'] }}</span></label>@endforeach
          </div>
          <span class="hint">First checked category is used for the storefront URL and inventory grouping.</span></div>
        <button class="btn btn--primary btn--block" type="submit">{{ $p['id'] ? 'Save product' : 'Create product' }}</button>
      </div>
    </div>
    @if($p['id'])
    <div class="card">
      <div class="card__head"><h2>Performance</h2></div>
      <div class="card__body">
        <dl class="dl dl--tight">
          <dt>Orders</dt><dd>{{ $stats->n ?? 0 }}</dd><dt>Units sold</dt><dd>{{ $stats->q ?? 0 }}</dd><dt>Revenue</dt><dd>{{ money($stats->t ?? 0) }}</dd><dt>Last sold</dt><dd>{{ $stats && $stats->last ? \Carbon\Carbon::parse($stats->last)->format('M j, Y') : '—' }}</dd>
          @if($p['cost'] !== '' && $p['regular_price'] !== '')<dt>Margin</dt><dd>{{ $p['regular_price'] > 0 ? number_format((($p['regular_price']-$p['cost'])/$p['regular_price'])*100, 0) : 0 }}%</dd>@endif
          @if($inv)<dt>Inventory</dt><dd>{{ $inv->stock }} on hand · reorder at {{ $inv->reorder_at ?? '—' }} <a href="/admin/inventory?q={{ urlencode($p['sku']) }}" class="small">manage</a></dd>@endif
        </dl>
        @if($orders->count())<div class="mt small">@foreach($orders as $o)<a href="/admin/orders/{{ $o->id }}" class="pill" style="margin:0 4px 4px 0">{{ $o->order_number }} <span class="muted">×{{ $o->qty }}</span></a>@endforeach</div>@endif
      </div>
    </div>
    @if($activity->count())<div class="card"><div class="card__head"><h2>History</h2></div><div class="card__body"><ul class="timeline">@foreach($activity as $a)<li class="done"><b>{{ $a->label }}</b><small>{{ $a->user_name ?: 'System' }} · {{ \Carbon\Carbon::parse($a->created_at)->format('M j, g:ia') }}</small></li>@endforeach</ul></div></div>@endif
    @endif
  </div>
  <div class="savebar" id="savebar" style="grid-column:1/-1"><span>Unsaved changes</span><button type="submit" class="btn btn--primary btn--sm">Save</button></div>
</form>
@endsection
@push('scripts')
<script>
(function () {
  var labels = {{ \Illuminate\Support\Js::from(array_map(fn($a)=>$a['label'], $allAttrs)) }};
  function wireDatalist(inp) {
    var dl = document.getElementById('dl-' + inp.dataset.tax); if (!dl) return;
    var loaded = false;
    inp.addEventListener('focus', function () { if (loaded) return; loaded = true; fetch('/admin/api/terms?tax=' + encodeURIComponent(inp.dataset.tax), { headers: { Accept: 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) { dl.innerHTML = (d.items || []).map(function (v) { return '<option value="' + admEsc(v) + '">'; }).join(''); }); });
  }
  document.querySelectorAll('#attrs input[data-tax]').forEach(wireDatalist);
  document.getElementById('attrs').addEventListener('click', function (e) { var b = e.target.closest('[data-rm-attr]'); if (b) { var i = b.parentNode.querySelector('input'); i.value = ''; i.dispatchEvent(new Event('input', { bubbles: true })); } });
  document.getElementById('attr-pick').addEventListener('change', function () {
    var tax = this.value; if (!tax) return; this.value = '';
    var box = document.getElementById('attrs-new'), row = document.createElement('div'); row.className = 'attr-row';
    if (tax === '__new') {
      row.innerHTML = '<input class="input input--sm" name="attr_new_key[]" placeholder="Attribute name (e.g. Cap diameter)"><input class="input input--sm" name="attr_new_val[]" placeholder="Value"><button type="button" class="btn btn--xs btn--icon" onclick="this.parentNode.remove()"><svg class="ic ic--s"><use href="#i-x"/></svg></button>';
    } else {
      row.innerHTML = '<span class="lab">' + admEsc(labels[tax] || tax) + '</span><input class="input input--sm" name="attrs[' + tax + ']" list="dl-' + tax + '" data-tax="' + tax + '" autocomplete="off"><datalist id="dl-' + tax + '"></datalist><button type="button" class="btn btn--xs btn--icon" onclick="this.parentNode.remove()"><svg class="ic ic--s"><use href="#i-x"/></svg></button>';
    }
    box.appendChild(row); var inp = row.querySelector('input[data-tax]'); if (inp) wireDatalist(inp); row.querySelector('input').focus();
    document.getElementById('pf').dispatchEvent(new Event('dirty'));
  });
})();
</script>
@endpush
