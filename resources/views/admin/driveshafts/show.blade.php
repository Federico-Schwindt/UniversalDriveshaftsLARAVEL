@extends('admin.layout')
@section('title', $part->sku)
@section('crumb')<a href="/admin/driveshafts">Complete driveshafts</a> › {{ $part->brand ?: 'Finder part' }}@endsection
@section('sub'){{ $part->description }} @if($spec)<span class="badge badge--{{ $spec->status==='verified'?'ok':'warn' }}">Spec {{ $spec->status }}</span>@else<span class="badge badge--err">No build spec</span>@endif @if(!$part->is_active)<span class="badge badge--err">Inactive</span>@endif @endsection
@section('actions')
  <a href="/admin/driveshafts/{{ rawurlencode($pn) }}/sheet" target="_blank" class="btn btn--sm">{!! ic('print') !!} Build sheet</a>
  <a href="/catalog/driveshafts/part/{{ rawurlencode($pn) }}" target="_blank" class="btn btn--sm">{!! ic('eye') !!} Customer page</a>
  <a href="/admin/orders/new" class="btn btn--primary btn--sm" onclick="sessionStorage.setItem('ob-prefill','{{ $part->sku }}')">{!! ic('plus') !!} New order</a>
@endsection
@section('body')
<div class="grid grid--main">
  <div class="stack">
    {{-- SPEC --}}
    <form method="post" action="/admin/driveshafts/{{ rawurlencode($pn) }}/spec" data-savebar="#savebar" id="specf" class="card">@csrf
      <div class="card__head"><h2>{!! ic('ruler') !!} Build spec</h2>
        <div class="flex">
          @if($updatedBy || $spec)<span class="muted small">Updated {{ $spec ? \Carbon\Carbon::parse($spec->updated_at)->diffForHumans() : '' }}{{ $updatedBy ? ' by '.$updatedBy : '' }}</span>@endif
          @if($siblings->count())<div class="adm-menu" data-menu><button type="button" class="btn btn--sm" data-menu-btn>{!! ic('copy') !!} Copy from…</button><div class="adm-menu__pop adm-menu__pop--right" hidden>@foreach($siblings as $s)<a href="#" data-post="/admin/driveshafts/{{ rawurlencode($pn) }}/spec/copy" data-fields='{"from":"{{ $s->part_number }}"}' data-confirm="Copy the spec from U{{ $s->part_number }} onto {{ $part->sku }}? Current values are replaced (history is kept)."><span><b class="mono">U{{ $s->part_number }}</b> <span class="badge badge--{{ $s->status==='verified'?'ok':'warn' }} tiny">{{ $s->status }}</span><br><small class="muted">{{ \Illuminate\Support\Str::limit($s->description,48) }} · {{ $s->series_front }} {{ $s->tube_od }} {{ $s->length_cc }}</small></span></a>@endforeach</div></div>@endif
        </div>
      </div>
      <div class="card__body">
        <div class="spec-grid">
          <div class="spec-sec"><h3>{!! ic('settings','ic--s') !!} U-joints</h3>
            @foreach(['series_front'=>['Front series','1310, 1330, 1350, 1410, 3R…'],'u_joint_front'=>['Front u-joint part #','5-153X'],'series_center'=>['Center series (2-pc)',''],'u_joint_center'=>['Center u-joint part #',''],'series_rear'=>['Rear series',''],'u_joint_rear'=>['Rear u-joint part #','']] as $k=>[$l,$ph])
              <div class="field"><label>{{ $l }}</label><input class="input input--sm" name="{{ $k }}" value="{{ old($k, $specArr[$k]) }}" placeholder="{{ $ph }}" list="dl-series"></div>
            @endforeach
            <datalist id="dl-series">@foreach(['1310','1330','1350','1410','1480','1550','1610','1710','1760','1810','3R','7260','7290','Toyota','Nissan','Mitsubishi','Mazda','Isuzu','GM 3R'] as $s)<option value="{{ $s }}">@endforeach</datalist>
          </div>
          <div class="spec-sec"><h3>{!! ic('layers','ic--s') !!} Tube</h3>
            <div class="field"><label>Outside diameter</label><input class="input input--sm" name="tube_od" value="{{ old('tube_od', $specArr['tube_od']) }}" placeholder='3.0"' list="dl-od"><datalist id="dl-od">@foreach(['2.0"','2.5"','2.75"','3.0"','3.5"','4.0"','4.5"','5.0"'] as $s)<option value="{{ $s }}">@endforeach</datalist></div>
            <div class="field"><label>Wall</label><input class="input input--sm" name="tube_wall" value="{{ old('tube_wall', $specArr['tube_wall']) }}" placeholder='.083"' list="dl-wall"><datalist id="dl-wall">@foreach(['.065"','.083"','.095"','.120"','.134"','.188"'] as $s)<option value="{{ $s }}">@endforeach</datalist></div>
            <div class="field"><label>Material</label><select class="select select--sm" name="tube_material">@foreach(['','Steel (DOM)','Aluminum 6061','Chromoly','Carbon fiber'] as $m)<option value="{{ $m }}" @selected(old('tube_material', $specArr['tube_material'])===$m)>{{ $m ?: '—' }}</option>@endforeach</select></div>
            <div class="field"><label>Pieces</label><select class="select select--sm" name="pieces">@foreach(['','1','2','3'] as $m)<option value="{{ $m }}" @selected((string)old('pieces', $specArr['pieces'])===$m)>{{ $m ?: '—' }}</option>@endforeach</select></div>
            <div class="field"><label>Center bearing</label><input class="input input--sm" name="center_bearing" value="{{ old('center_bearing', $specArr['center_bearing']) }}" placeholder="part # / bracket"></div>
          </div>
          <div class="spec-sec"><h3>{!! ic('ruler','ic--s') !!} Lengths</h3>
            @foreach(['length_cc'=>['Center to center','44.25"'],'length_overall'=>['Overall (installed)',''],'length_compressed'=>['Compressed',''],'length_extended'=>['Extended','']] as $k=>[$l,$ph])
              <div class="field"><label>{{ $l }}</label><input class="input input--sm" name="{{ $k }}" value="{{ old($k, $specArr[$k]) }}" placeholder="{{ $ph }}"></div>
            @endforeach
            <div class="field"><label>Balance</label><input class="input input--sm" name="balance_rpm" value="{{ old('balance_rpm', $specArr['balance_rpm']) }}" placeholder="3,500 rpm"></div>
            <div class="field"><label>Weight (lb)</label><input class="input input--sm" name="weight_lb" value="{{ old('weight_lb', $specArr['weight_lb']) }}"></div>
          </div>
          <div class="spec-sec"><h3>{!! ic('shaft','ic--s') !!} Ends</h3>
            @foreach(['front_end'=>['Front end','Slip yoke / flange / weld yoke…'],'slip_yoke'=>['Slip yoke part #',''],'slip_spline'=>['Spline','27 × 1.5"'],'flange_front'=>['Front flange / CV',''],'rear_end'=>['Rear end',''],'flange_rear'=>['Rear flange / end yoke',''],'cv_type'=>['CV type','Double cardan / Rzeppa / none']] as $k=>[$l,$ph])
              <div class="field"><label>{{ $l }}</label><input class="input input--sm" name="{{ $k }}" value="{{ old($k, $specArr[$k]) }}" placeholder="{{ $ph }}"></div>
            @endforeach
          </div>
          <div class="spec-sec" style="grid-column:1/-1"><h3>{!! ic('box','ic--s') !!} Components to pull</h3>
            <div class="combo"><input class="input input--sm" placeholder="Search parts to add (u-joints, yokes, tubing…)" id="comp-search" autocomplete="off"></div>
            <table class="tbl tbl--compact mt" id="comps"><thead><tr><th>SKU</th><th>Part</th><th style="width:80px">Qty</th><th></th></tr></thead><tbody>
              @foreach(old('components', $components) as $i=>$c)<tr><td><input type="hidden" name="components[{{ $i }}][id]" value="{{ $c['id'] ?? '' }}"><input class="input input--sm mono" name="components[{{ $i }}][sku]" value="{{ $c['sku'] ?? '' }}"></td><td><input class="input input--sm" name="components[{{ $i }}][name]" value="{{ $c['name'] ?? '' }}"></td><td><input class="input input--sm num" type="number" min="1" name="components[{{ $i }}][qty]" value="{{ $c['qty'] ?? 1 }}"></td><td><button type="button" class="btn btn--xs btn--icon" onclick="this.closest('tr').remove();document.getElementById('specf').dispatchEvent(new Event('dirty'))">{!! ic('x','ic--s') !!}</button></td></tr>@endforeach
            </tbody></table>
            <button type="button" class="btn btn--xs mt" id="comp-add">{!! ic('plus','ic--s') !!} Add blank line</button>
          </div>
          <div class="spec-sec" style="grid-column:1/-1"><h3>{!! ic('image','ic--s') !!} Shop photos &amp; drawings</h3>
            <div data-uploader data-url="/admin/media/upload" data-name="photos[]" class="stack">
              <div class="media-grid" data-grid>@foreach(old('photos', $photos) as $u)<div class="media-item" draggable="true"><img src="{{ media_url($u,320) }}" alt=""><input type="hidden" name="photos[]" value="{{ $u }}"><button type="button" class="rm">{!! ic('x','ic--s') !!}</button></div>@endforeach</div>
              <label class="dropzone">{!! ic('upload') !!}<div><b>Drop photos of the finished shaft, measurements, sketches</b> — internal only</div><input type="file" accept="image/*" multiple></label>
            </div>
            <input type="hidden" name="_folder" value="driveshafts">
          </div>
          <div class="spec-sec" style="grid-column:1/-1"><h3>{!! ic('pen','ic--s') !!} Notes</h3>
            <div class="form-grid">
              <div class="field"><label>Shop / build notes <span class="muted">(internal)</span></label><textarea class="input" name="build_notes" rows="4" placeholder="Measure from flange face; customer's 2-pc needs new carrier bracket; balance at 3500…">{{ old('build_notes', $specArr['build_notes']) }}</textarea></div>
              <div class="field"><label>Customer-facing notes</label><textarea class="input" name="customer_notes" rows="4" placeholder="Ask the customer to confirm transmission & cab/bed before shipping…">{{ old('customer_notes', $specArr['customer_notes']) }}</textarea></div>
              <div class="field"><label>Build cost (parts + labor)</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" name="build_cost" value="{{ old('build_cost', $specArr['build_cost']) }}"></div></div>
              <div class="field"><label>Build time (minutes)</label><input class="input" type="number" name="build_minutes" value="{{ old('build_minutes', $specArr['build_minutes']) }}"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="card__foot">
        <label class="switch"><input type="checkbox" name="status" value="verified" @checked(old('status', $specArr['status'])==='verified')><i></i> Verified — built and confirmed on a vehicle</label>
        <button class="btn btn--primary">{!! ic('check') !!} Save spec</button>
      </div>
      <div class="savebar" id="savebar"><span>Unsaved spec changes</span><button type="submit" class="btn btn--primary btn--sm">Save spec</button></div>
    </form>

    {{-- FITMENTS --}}
    <div class="card">
      <div class="card__head"><h2>Fits <span class="muted">({{ $fitRows->count() }} fitments)</span></h2>@if($page)<span class="muted small">{{ $page['year_min'] }}–{{ $page['year_max'] }} · {{ implode(', ', $page['makes']) }}</span>@endif</div>
      @if($page && $page['applications'])
      <div class="card__body" style="padding-bottom:6px">@foreach($page['applications'] as $a)<a class="veh-badge" style="margin:0 6px 6px 0" href="/admin/driveshafts/lookup?make={{ urlencode($a['make']) }}&year={{ explode('-',$a['years'])[0] }}&model={{ urlencode($a['model']) }}"><b>{{ $a['years'] }}</b> {{ $a['make'] }} {{ $a['model'] }}@if($a['drive']) <span class="muted">{{ $a['drive'] }}</span>@endif</a>@endforeach</div>
      @endif
      @if($page && $page['configs'])
      <div class="tbl-wrap"><table class="tbl tbl--compact"><thead><tr><th>Vehicle</th>@foreach($page['config_cols'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead><tbody>@foreach(array_slice($page['configs'],0,40) as $c)<tr><td><b>{{ $c['vehicle'] }}</b>@if($c['drive']) <span class="muted small">{{ $c['drive'] }}</span>@endif</td>@foreach($c['values'] as $v)<td class="small">{{ $v ?: '—' }}</td>@endforeach</tr>@endforeach</tbody></table></div>
      @if($page['config_total']>40)<div class="card__foot muted small">Showing 40 of {{ $page['config_total'] }} configurations.</div>@endif
      @endif
    </div>

    {{-- PART DETAILS --}}
    <form method="post" action="/admin/driveshafts/{{ rawurlencode($pn) }}" class="card" data-savebar="#savebar2">@csrf
      <div class="card__head"><h2>Part details &amp; pricing</h2><span class="muted small">Supplier: {{ $part->supplier ?: '—' }} · {{ $part->brand }}</span></div>
      <div class="card__body form-grid form-grid--3">
        <div class="field span-3"><label>Description</label><input class="input" name="description" value="{{ old('description', $part->description) }}"></div>
        <div class="field"><label>Series</label><input class="input" name="series" value="{{ old('series', $part->series) }}"></div>
        <div class="field"><label>Condition</label><input class="input" name="condition" value="{{ old('condition', $part->condition) }}"></div>
        <div class="field"><label>Vehicle class</label><input class="input" name="vehicle_class" value="{{ old('vehicle_class', $part->vehicle_class) }}"></div>
        <div class="field"><label>List price</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" name="price_list" value="{{ $part->price_list }}"></div></div>
        <div class="field"><label>Our cost</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" name="price_cost" value="{{ $part->price_cost }}"></div></div>
        <div class="field"><label>Retail override <span class="muted">(auto: {{ $part->price_auto !== null ? money($part->price_auto) : '—' }})</span></label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" name="price_retail" value="{{ $part->price_retail }}" placeholder="blank = automatic"></div></div>
        <div class="field"><label>Core charge</label><div class="input-group"><span class="addon">$</span><input class="input" type="number" step="0.01" name="core_price" value="{{ $part->core_price }}"></div></div>
        <div class="field"><label>Weight (lb)</label><input class="input" type="number" step="0.01" name="weight" value="{{ $part->weight }}"></div>
        <div class="field"><label>Box L × W × H (in)</label><div class="input-group"><input class="input" type="number" step="0.1" name="box_length" value="{{ $part->box_length }}" placeholder="L"><input class="input" type="number" step="0.1" name="box_width" value="{{ $part->box_width }}" placeholder="W"><input class="input" type="number" step="0.1" name="box_height" value="{{ $part->box_height }}" placeholder="H"></div></div>
        <div class="field"><label>UPC</label><input class="input mono" name="upc" value="{{ $part->upc }}"></div>
        <div class="field span-2"><label>Catalog notes <span class="muted">(shown to customers)</span></label><textarea class="input" name="notes" rows="2">{{ old('notes', $part->notes) }}</textarea></div>
        <div class="field span-3 flex gap-3 flex-wrap"><label class="switch"><input type="checkbox" name="is_active" value="1" @checked($part->is_active)><i></i> Active (sellable)</label><label class="switch"><input type="checkbox" name="is_buildable" value="1" @checked($part->is_buildable)><i></i> Buildable in-house</label></div>
        <div class="field span-3"><label>Product photos <span class="muted">(customer-facing)</span></label>
          <div data-uploader data-url="/admin/media/upload" data-name="images[]" class="stack"><div class="media-grid" data-grid>@foreach($part->imageList as $u)<div class="media-item" draggable="true"><img src="{{ media_url($u,320) }}" alt=""><input type="hidden" name="images[]" value="{{ $u }}"><button type="button" class="rm">{!! ic('x','ic--s') !!}</button></div>@endforeach</div><label class="dropzone">{!! ic('upload') !!}<div><b>Drop product photos</b></div><input type="file" accept="image/*" multiple></label></div>
        </div>
      </div>
      <div class="card__foot"><span class="muted small">Customer price now: <b>{{ $part->price !== null ? money($part->price) : 'Call for price' }}</b></span><button class="btn btn--dark">Save part</button></div>
      <div class="savebar" id="savebar2"><span>Unsaved part changes</span><button type="submit" class="btn btn--primary btn--sm">Save part</button></div>
    </form>
  </div>

  <div class="stack">
    <div class="card"><div class="card__body"><div class="product-head">@if($part->main_image)<img src="{{ media_url($part->main_image,320) }}" alt="">@endif<div><div class="mono" style="font-size:18px;font-weight:700">{{ $part->sku }}</div><div class="muted small">Supplier # {{ $part->part_number }}</div><div style="font-size:20px;font-weight:700;margin-top:6px">{{ $part->price !== null ? money($part->price) : 'Call' }}</div>@if($part->price_cost)<div class="muted small">cost {{ money($part->price_cost) }} · margin {{ $part->price ? number_format((($part->price-$part->price_cost)/$part->price)*100,0) : 0 }}%</div>@endif</div></div></div></div>
    <div class="card"><div class="card__head"><h2>Orders <span class="muted">({{ $orders->count() }})</span></h2></div>
      <div class="card__body card__body--flush">@forelse($orders as $o) @php $m = json_decode($o->meta ?: '{}', true) ?: []; @endphp<a href="/admin/orders/{{ $o->id }}" class="stat-row" style="padding:9px 16px;color:inherit;text-decoration:none"><span><b class="mono">{{ $o->order_number }}</b> <span class="badge badge--{{ $o->status }} tiny">{{ $o->status }}</span><br><span class="muted small">{{ $o->billing_first_name }} {{ $o->billing_last_name }}@if(!empty($m['vehicle'])) · {{ is_array($m['vehicle']) ? implode(' ', $m['vehicle']) : $m['vehicle'] }}@endif</span></span><span class="right small">×{{ $o->qty }} {{ money($o->price) }}<br><span class="muted">{{ \Carbon\Carbon::parse($o->created_at)->format('M j, Y') }}</span></span></a>@empty<div class="empty">Never ordered yet.</div>@endforelse</div></div>
    @if($notes->count())<div class="card"><div class="card__head"><h2>Vehicle notes</h2><a href="/admin/driveshafts/lookup?make={{ urlencode($notes->first()->make) }}" class="btn btn--ghost btn--sm">Manage</a></div><div class="card__body stack">@foreach($notes as $n)<div class="note"><div class="note__meta"><span>{{ $n->make }} {{ $n->model }} {{ $n->year_from }}@if($n->year_to)–{{ $n->year_to }}@endif</span></div>{!! nl2br(e($n->notes)) !!}</div>@endforeach</div></div>@endif
    <div class="card"><div class="card__head"><h2>Spec history</h2></div><div class="card__body"><ul class="timeline">@forelse($history as $h) @php $s = json_decode($h->snapshot, true) ?: []; @endphp<li class="done"><b>{{ ($s['status'] ?? 'draft')==='verified' ? 'Verified' : 'Saved' }}@if(!empty($s['copied_from'])) (copied from U{{ $s['copied_from'] }}) @endif</b><small>{{ $h->user_name ?: 'Admin' }} · {{ \Carbon\Carbon::parse($h->created_at)->format('M j, Y g:ia') }}</small><div class="small muted">{{ collect([$s['series_front'] ?? null, $s['tube_od'] ?? null, $s['length_cc'] ?? null])->filter()->implode(' · ') }}</div></li>@empty<li>No history yet — save the spec to start it.</li>@endforelse</ul></div></div>
  </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
  var tb = document.querySelector('#comps tbody'), f = document.getElementById('specf');
  function addRow(c) { var i = tb.querySelectorAll('tr').length; var tr = document.createElement('tr'); tr.innerHTML = '<td><input type="hidden" name="components[' + i + '][id]" value="' + admEsc(c.id || '') + '"><input class="input input--sm mono" name="components[' + i + '][sku]" value="' + admEsc(c.sku || '') + '"></td><td><input class="input input--sm" name="components[' + i + '][name]" value="' + admEsc(c.name || '') + '"></td><td><input class="input input--sm num" type="number" min="1" name="components[' + i + '][qty]" value="' + (c.qty || 1) + '"></td><td><button type="button" class="btn btn--xs btn--icon" onclick="this.closest(\'tr\').remove();document.getElementById(\'specf\').dispatchEvent(new Event(\'dirty\'))"><svg class="ic ic--s"><use href="#i-x"/></svg></button></td>'; tb.appendChild(tr); f.dispatchEvent(new Event('dirty')); }
  document.getElementById('comp-add').addEventListener('click', function () { addRow({}); });
  admCombo(document.getElementById('comp-search'), { url: '/admin/api/products?products_only=1&q=', render: function (it) { return (it.image ? '<img src="' + admEsc(it.image) + '" alt="">' : '<svg class="ic"><use href="#i-box"/></svg>') + '<span class="t"><b>' + admEsc(it.sku) + '</b><small>' + admEsc(it.name) + '</small></span><span class="p">$' + Number(it.price).toFixed(2) + '</span>'; }, onPick: function (it) { addRow({ id: it.id, sku: it.sku, name: it.name, qty: 1 }); } });
  // uploader on the spec form should store under /uploads/driveshafts
  document.querySelectorAll('#specf [data-uploader], form[action$="{{ rawurlencode($pn) }}"] [data-uploader]').forEach(function (u) { u.dataset.url = '/admin/media/upload?folder=driveshafts'; });
})();
</script>
@endpush
