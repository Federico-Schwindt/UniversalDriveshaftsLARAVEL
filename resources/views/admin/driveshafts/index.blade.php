@extends('admin.layout')
@section('title','Complete driveshafts')
@section('crumb','Catalog')
@section('sub')Every part number from the vehicle finder. Record what the shop needs to build each one — u-joint series, tube, lengths, ends — so the next order for that vehicle is ready to go. @endsection
@section('actions')
  <a href="/admin/driveshafts/lookup" class="btn btn--primary btn--sm">{!! ic('car') !!} Lookup by vehicle</a>
  <a href="/admin/driveshafts?{{ http_build_query(array_merge($f, ['export'=>'csv'])) }}" class="btn btn--sm">{!! ic('download') !!} Export specs</a>
@endsection
@section('body')
@php $tabs = ['all'=>'All parts','nospec'=>'No spec yet','spec'=>'Has spec','verified'=>'Verified','ordered'=>'Ordered before','buildable'=>'Buildable','inactive'=>'Inactive']; @endphp
<div class="chips">@foreach($tabs as $k=>$l)<a href="/admin/driveshafts?tab={{ $k }}{{ !empty($f['q']) ? '&q='.urlencode($f['q']) : '' }}{{ $make ? '&make='.urlencode($make) : '' }}" class="chip @if($tab===$k) is-on @endif">{{ $l }} <b>{{ $counts[$k] ?? 0 }}</b></a>@endforeach</div>
<form class="toolbar" method="get" action="/admin/driveshafts">
  <input type="hidden" name="tab" value="{{ $tab }}">
  <div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Part number (U2594-173 or 2594-173), description…"></div>
  <select class="select" name="make" data-autosubmit><option value="">Any make</option>@foreach($makes as $m)<option @selected($make===$m)>{{ $m }}</option>@endforeach</select>
  @if($make)<select class="select" name="year" data-autosubmit><option value="">Any year</option>@foreach($years as $y)<option @selected($year===(int)$y)>{{ $y }}</option>@endforeach</select>
  <select class="select" name="model" data-autosubmit><option value="">Any model</option>@foreach($models as $m)<option @selected($model===$m)>{{ $m }}</option>@endforeach</select>@endif
  <select class="select" name="series" data-autosubmit><option value="">Any series</option>@foreach($series as $s)<option @selected(($f['series'] ?? '')===$s)>{{ $s }}</option>@endforeach</select>
  <select class="select" name="sort" data-autosubmit>@foreach(['part_asc'=>'Part # A–Z','part_desc'=>'Part # Z–A','updated'=>'Recently specced','price'=>'Price ↑','price_desc'=>'Price ↓'] as $k=>$l)<option value="{{ $k }}" @selected($sort===$k)>{{ $l }}</option>@endforeach</select>
  <button class="btn btn--sm">{!! ic('filter') !!} Filter</button>
  @if(!empty($f['q']) || $make || !empty($f['series']))<a href="/admin/driveshafts?tab={{ $tab }}" class="btn btn--ghost btn--sm">Clear</a>@endif
</form>
<div class="card">
  <div class="tbl-wrap"><table class="tbl tbl--cards">
    <thead><tr><th class="w-img"></th><th>Part</th><th>Fits</th><th>Series</th><th>Build spec</th><th class="right">Price</th><th class="center">Sold</th></tr></thead>
    <tbody>
    @forelse($rows as $p)
      <tr data-href="/admin/driveshafts/{{ rawurlencode($p->part_number) }}">
        <td class="w-img">@if($p->main_image)<img src="{{ media_url($p->main_image,160) }}" class="thumb" alt="" loading="lazy">@endif</td>
        <td class="primary"><span class="title mono">{{ $p->sku }}</span><small>{{ \Illuminate\Support\Str::limit($p->description, 64) }}@if(!$p->is_active) <span class="badge badge--err badge--plain tiny">inactive</span>@endif @if($p->is_buildable) <span class="badge badge--info badge--plain tiny">buildable</span>@endif</small></td>
        <td data-label="Fits" class="small">@if($p->fit){{ $p->fit->y0 }}@if($p->fit->y1!=$p->fit->y0)–{{ $p->fit->y1 }}@endif {{ $p->fit->makes>1 ? $p->fit->makes.' makes' : $p->fit->make.' '.$p->fit->model }} <span class="muted">· {{ $p->fit->n }} fitment{{ $p->fit->n==1?'':'s' }}</span>@else<span class="muted">—</span>@endif</td>
        <td data-label="Series" class="small">{{ $p->series ?: '—' }}@if($p->series_front || $p->series_rear)<br><span class="muted">spec: {{ $p->series_front }}@if($p->series_rear && $p->series_rear!==$p->series_front) / {{ $p->series_rear }}@endif @if($p->tube_od)· {{ $p->tube_od }} tube @endif @if($p->length_cc)· {{ $p->length_cc }} c/c @endif</span>@endif</td>
        <td class="status-cell">@if($p->spec_status==='verified')<span class="badge badge--ok">Verified</span>@elseif($p->spec_status)<span class="badge badge--warn">Draft</span>@else<span class="badge">No spec</span>@endif</td>
        <td class="right num" data-label="Price">{{ $p->price !== null ? money($p->price) : 'Call' }}</td>
        <td class="center num" data-label="Sold">{{ $p->ordered ?: '—' }}</td>
      </tr>
    @empty<tr><td colspan="7"><div class="empty">{!! ic('shaft') !!}<b>No driveshafts match</b></div></td></tr>@endforelse
    </tbody></table></div>
  @include('admin.partials.pager')
</div>
@endsection
