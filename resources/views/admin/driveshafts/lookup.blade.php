@extends('admin.layout')
@section('title','Lookup by vehicle')
@section('crumb')<a href="/admin/driveshafts">Complete driveshafts</a>@endsection
@section('sub')An order came in? Pick the vehicle and see every matching shaft with its build spec, ready to print for the shop floor. @endsection
@section('body')
<div class="card mb">
  <form class="card__body form-grid form-grid--3" method="get" action="/admin/driveshafts/lookup">
    <div class="field"><label>Make</label><select class="select" name="make" onchange="this.form.year.value='';this.form.model.value='';this.form.submit()"><option value="">Choose…</option>@foreach($makes as $m)<option @selected($make===$m)>{{ $m }}</option>@endforeach</select></div>
    <div class="field"><label>Year</label><select class="select" name="year" onchange="this.form.model.value='';this.form.submit()" @disabled(!$make)><option value="">Choose…</option>@foreach($years as $y)<option @selected($year===(int)$y)>{{ $y }}</option>@endforeach</select></div>
    <div class="field"><label>Model</label><select class="select" name="model" onchange="this.form.submit()" @disabled(!$year)><option value="">Choose…</option>@foreach($models as $m)<option @selected($model===$m)>{{ $m }}</option>@endforeach</select></div>
  </form>
</div>
@if($make && $year && $model)
  <div class="card mb">
    <div class="card__head"><h2>{{ $year }} {{ $make }} {{ $model }} <span class="muted">· {{ $parts->count() }} shaft{{ $parts->count()==1?'':'s' }}</span></h2><a href="/catalog/driveshafts?make={{ urlencode($make) }}&year={{ $year }}&model={{ urlencode($model) }}" target="_blank" class="btn btn--ghost btn--sm">Customer view ↗</a></div>
    <div class="tbl-wrap"><table class="tbl tbl--cards">
      <thead><tr><th>Part</th><th>Position / drive</th><th>Configuration</th><th>Build spec</th><th class="right">Price</th><th></th></tr></thead>
      <tbody>
      @foreach($fits->groupBy('part_number') as $pn=>$g)
        @php $p = $parts[$pn] ?? null; $f0 = $g->first(); @endphp
        <tr data-href="/admin/driveshafts/{{ rawurlencode($pn) }}">
          <td class="primary"><span class="title mono">U{{ $pn }}</span><small>{{ $p->description ?? '' }}</small></td>
          <td data-label="Position" class="small">{{ $g->pluck('loc')->filter()->unique()->implode(', ') ?: '—' }} @if($g->pluck('drive')->filter()->unique()->count())<span class="muted">· {{ $g->pluck('drive')->filter()->unique()->implode('/') }}</span>@endif</td>
          <td data-label="Config" class="small muted">{{ collect([$f0->engine, $f0->trans, $f0->cab, $f0->bed, $f0->transfer_case])->filter()->implode(' · ') ?: '—' }}@if($g->count()>1) <span class="pill">+{{ $g->count()-1 }} variants</span>@endif</td>
          <td class="status-cell">@if(($p->status ?? null)==='verified')<span class="badge badge--ok">Verified</span>@elseif($p && $p->status)<span class="badge badge--warn">Draft</span>@else<span class="badge badge--err">No spec</span>@endif</td>
          <td class="right num" data-label="Price">{{ $p && $p->price !== null ? money($p->price) : 'Call' }}</td>
          <td class="actions"><a href="/admin/driveshafts/{{ rawurlencode($pn) }}/sheet" target="_blank" class="btn btn--xs">{!! ic('print','ic--s') !!} Sheet</a></td>
        </tr>
      @endforeach
      </tbody></table></div>
  </div>
  @foreach($fits->groupBy('part_number') as $pn=>$g) @php $p = $parts[$pn] ?? null; @endphp
    @if($p && $p->status)
    <div class="card mb"><div class="card__head"><h2>U{{ $pn }} — build spec <span class="badge badge--{{ $p->status==='verified'?'ok':'warn' }}">{{ $p->status }}</span></h2><a href="/admin/driveshafts/{{ rawurlencode($pn) }}" class="btn btn--ghost btn--sm">Edit</a></div>
      <div class="card__body"><div class="spec-view">
        @foreach(['series_front'=>'Front series','series_center'=>'Center series','series_rear'=>'Rear series','u_joint_front'=>'Front u-joint','u_joint_rear'=>'Rear u-joint','tube_od'=>'Tube OD','tube_wall'=>'Wall','tube_material'=>'Material','length_cc'=>'Center to center','length_overall'=>'Overall','length_compressed'=>'Compressed','front_end'=>'Front end','rear_end'=>'Rear end','slip_yoke'=>'Slip yoke','slip_spline'=>'Spline','center_bearing'=>'Center bearing','pieces'=>'Pieces','balance_rpm'=>'Balance'] as $k=>$l)
          <div><small>{{ $l }}</small><b class="{{ $p->$k ? '' : 'empty-v' }}">{{ $p->$k ?: '—' }}</b></div>
        @endforeach
      </div>@if($p->build_notes)<div class="note note--internal mt"><b>Shop notes:</b> {!! nl2br(e($p->build_notes)) !!}</div>@endif</div></div>
    @endif
  @endforeach
@endif
<div class="grid grid--2">
  <div class="card">
    <div class="card__head"><h2>Vehicle notes @if($make)<span class="muted">· {{ $make }}@if($model) {{ $model }}@endif</span>@endif</h2></div>
    <div class="card__body stack">
      @foreach($notes as $n)<div class="note"><div class="note__meta"><span>{{ $n->make }} {{ $n->model }} @if($n->year_from || $n->year_to){{ $n->year_from ?: '…' }}–{{ $n->year_to ?: '…' }}@endif</span><form method="post" action="/admin/driveshafts/vehicle-note/{{ $n->id }}/delete" data-confirm="Delete this note?">@csrf<button class="btn btn--xs btn--icon">{!! ic('x','ic--s') !!}</button></form></div>{!! nl2br(e($n->notes)) !!}</div>@endforeach
      <form method="post" action="/admin/driveshafts/vehicle-note" class="stack" style="gap:8px">@csrf
        <div class="form-grid form-grid--3"><div class="field"><input class="input input--sm" name="make" value="{{ $make }}" placeholder="Make" required list="mk"><datalist id="mk">@foreach($makes as $m)<option value="{{ $m }}">@endforeach</datalist></div><div class="field"><input class="input input--sm" name="model" value="{{ $model }}" placeholder="Model (blank = all)"></div><div class="field"><div class="input-group"><input class="input input--sm" name="year_from" placeholder="from" type="number" value="{{ $year ?: '' }}"><input class="input input--sm" name="year_to" placeholder="to" type="number" value="{{ $year ?: '' }}"></div></div></div>
        <textarea class="input" name="notes" rows="2" placeholder="Things to remember for this vehicle — e.g. customer must confirm cab/bed, 2-pc needs center bearing bracket, measure flange to flange…" required></textarea>
        <button class="btn btn--sm btn--primary" style="align-self:flex-start">Save note</button>
      </form>
    </div>
  </div>
  <div class="card">
    <div class="card__head"><h2>Recently specced</h2></div>
    <div class="card__body card__body--flush">@forelse($recent as $r)<a href="/admin/driveshafts/{{ rawurlencode($r->part_number) }}" class="stat-row" style="padding:9px 16px;color:inherit;text-decoration:none"><span><b class="mono">U{{ $r->part_number }}</b><br><span class="muted small">{{ \Illuminate\Support\Str::limit($r->description,50) }}</span></span><span class="muted small right">{{ $r->user_name ?: 'Admin' }}<br>{{ \Carbon\Carbon::parse($r->created_at)->diffForHumans() }}</span></a>@empty<div class="empty">No specs saved yet.</div>@endforelse</div>
  </div>
</div>
@endsection
