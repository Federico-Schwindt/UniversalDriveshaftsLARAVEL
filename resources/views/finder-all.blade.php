@extends('layouts.app')
@section('title', __('Complete driveshafts by vehicle') . ' | Universal Driveshafts')
@section('desc', __('Every complete driveshaft we stock, listed by UDS part number with price, description and the vehicles it fits. Or find yours by year, make and model.'))
@section('canonical','/shop/complete-driveshafts/by-vehicle')
@if($q !== '' || $data['page'] > 1)@section('robots','noindex,follow')@endif
@push('scripts')
@include('shop.partials.js-i18n')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
@endpush
@push('schema')
@include('partials.breadcrumb-schema', ['crumbs' => [
  ['Home', rtrim(config('app.url'),'/').'/'], ['Catalog', rtrim(config('app.url'),'/').'/shop'],
  [$group['label'], rtrim(config('app.url'),'/').'/shop/'.$group['slug']], ['Complete Driveshafts', url()->current()],
]])
@endpush
@section('content')
@include('shop.partials.style')
@php
  $money = fn ($n) => '$' . number_format((float) $n, 2);
  $qs = fn ($pg) => '?' . http_build_query(array_filter(['q' => $q, 'sort' => $sort, 'make' => $vehicle['make'], 'year' => $vehicle['year'] ?: null, 'model' => $vehicle['model'], 'page' => $pg]));
  $vLabel = trim(implode(' ', array_filter([$vehicle['year'] ?: null, $vehicle['make'], $vehicle['model']])));
  $ds = '<svg viewBox="0 0 64 40" width="26" height="26" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linejoin="round"><circle cx="12" cy="20" r="7"/><circle cx="52" cy="20" r="7"/><rect x="19" y="16.5" width="26" height="7" rx="2"/></svg>';
  $pieces = function ($d) { preg_match('/(\d)\s*PC/i', (string) $d, $m); return isset($m[1]) ? $m[1] . '-piece' : (stripos((string) $d, 'boat') !== false ? 'Boat' : '-'); };
@endphp
<header class="ucathead">
  <div class="wrap">
    <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
      <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
      <a href="/shop">{{ __('Catalog') }}</a><x-icon name="caret-right"/>
      <a href="/shop/{{ $group['slug'] }}">{{ __($group['label']) }}</a><x-icon name="caret-right"/>
      <span aria-current="page">{{ __('Complete Driveshafts') }}</span>
    </nav>
    <h1>{{ __('Complete Driveshafts') }}</h1>
    <p class="ucathead__blurb">{{ __('Assembled, balanced and ready to install. Every part number here is a complete shaft for a specific vehicle — the fastest way to the right one is the vehicle finder.') }}</p>
    <div class="btn-row"><a class="btn btn-primary" href="/catalog/driveshafts"><x-icon name="crosshair"/> {{ __('Find by vehicle') }}</a><a class="btn btn-ghost" href="/online-builder">{{ __('Build one to spec') }}</a></div>
    @if(count($siblings) > 1)
      <div class="usiblings" role="list" aria-label="{{ __('Related categories') }}">
        @foreach($siblings as $s)
          @if($s['slug'] !== 'by-vehicle')
            <a class="usib" role="listitem" href="/shop/{{ $group['slug'] }}/{{ $s['slug'] }}"><span>@if($s['image'])<img src="{{ $s['image'] }}" alt="" loading="lazy">@else<x-icon name="package"/>@endif</span><b>{{ \App\Support\PartGlossary::t($s['name']) }}</b></a>
          @endif
        @endforeach
      </div>
    @endif
  </div>
</header>

<section class="section" style="padding-top:0">
  <div class="wrap">
    <form class="shop-controls vfilter" id="shop-controls" method="get" action="/shop/complete-driveshafts/by-vehicle" data-vfilter>
      <div class="vfilter__veh" role="group" aria-label="{{ __('Filter by vehicle') }}">
        <label class="vsel"><span>{{ __('Make') }}</span>
          <select name="make" data-v="make" aria-label="{{ __('Make') }}"><option value="">{{ __('Any make') }}</option>@foreach($options['makes'] as $m)<option value="{{ $m }}" @selected($vehicle['make']===$m)>{{ $m }}</option>@endforeach</select></label>
        <label class="vsel"><span>{{ __('Year') }}</span>
          <select name="year" data-v="year" aria-label="{{ __('Year') }}" @disabled(!$vehicle['make'])><option value="">{{ $vehicle['make'] ? __('Any year') : __('Pick a make first') }}</option>@foreach($options['years'] as $y)<option value="{{ $y }}" @selected((string)$vehicle['year']===$y)>{{ $y }}</option>@endforeach</select></label>
        <label class="vsel"><span>{{ __('Model') }}</span>
          <select name="model" data-v="model" aria-label="{{ __('Model') }}" @disabled(!$vehicle['year'])><option value="">{{ $vehicle['year'] ? __('Any model') : __('Pick a year first') }}</option>@foreach($options['models'] as $m)<option value="{{ $m }}" @selected($vehicle['model']===$m)>{{ $m }}</option>@endforeach</select></label>
        @if($vehicle['make'])<a class="vfilter__clear" href="/shop/complete-driveshafts/by-vehicle{{ $q ? '?q='.rawurlencode($q) : '' }}"><x-icon name="x"/> {{ __('Clear vehicle') }}</a>@endif
      </div>
      <div class="shop-search">
        <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Part number, description, make or model') }}" autocomplete="off" aria-label="{{ __('Search complete driveshafts') }}">
        <button type="submit" aria-label="{{ __('Search') }}"><x-icon name="search"/></button>
      </div>
      <select class="sortsel" name="sort" aria-label="{{ __('Sort results') }}" style="margin-left:auto" onchange="this.form.submit()">
        <option value="part_asc" @selected($sort==='part_asc')>{{ __('Part number: A to Z') }}</option>
        <option value="part_desc" @selected($sort==='part_desc')>{{ __('Part number: Z to A') }}</option>
        <option value="price_asc" @selected($sort==='price_asc')>{{ __('Price: low to high') }}</option>
        <option value="price_desc" @selected($sort==='price_desc')>{{ __('Price: high to low') }}</option>
      </select>
    </form>

    <div class="shop-count">{{ __($data['total']==1 ? ':n complete driveshaft' : ':n complete driveshafts', ['n' => number_format($data['total'])]) }}{{ $vLabel ? __(' for :vehicle', ['vehicle' => $vLabel]) : '' }}{{ $q ? __(' matching ":q"', ['q' => $q]) : '' }}</div>
    @if(count($data['items']))
    <div class="ptbl-wrap"><table class="ptbl">
      <thead><tr><th>{{ __('Add') }}</th><th>{{ __('Pic') }}</th><th>{{ __('Part #') }}</th><th>{{ __('Price') }}</th><th class="l">{{ __('Description') }}</th><th>{{ __('Type') }}</th><th class="l">{{ __('Fits') }}</th><th>{{ __('Weight') }}</th></tr></thead>
      <tbody>
      @foreach($data['items'] as $d)
        <tr>
          <td data-label="Add"><span class="p-add">@if($d['can_buy'])<button class="p-plus" data-ds-add="{{ $d['part_number'] }}" aria-label="Add {{ $d['sku'] }} to cart" title="{{ __('Add to cart') }}"><x-icon name="plus"/></button>@else<a class="btn btn-line btn-sm" href="tel:+18135246491">{{ __('Call') }}</a>@endif</span></td>
          <td data-label="Pic">@if($d['image'])<img class="p-pic" width="44" height="44" src="{{ $d['image'] }}" data-zoom="{{ $d['image'] }}" alt="{{ $d['sku'] }}" loading="lazy">@else<span class="p-pic-ph">{!! $ds !!}</span>@endif</td>
          <td data-label="Part #"><a class="p-pn" href="{{ $d['url'] }}">{{ $d['sku'] }}<x-icon name="arrow-up-right"/></a></td>
          <td data-label="Price"><div class="p-price">@if($d['can_buy'])<span class="now">{{ $money($d['price']) }}</span>@if($d['core_price'])<span class="save">+ {{ $money($d['core_price']) }} {{ __('core') }}</span>@endif @else<span class="p-muted">{{ __('Call for price') }}</span>@endif</div></td>
          <td class="l" data-label="Description">{{ $d['description'] ?: __('Complete driveshaft') }}@if($d['condition'] && $d['condition'] !== 'new') <span class="p-muted">({{ $d['condition'] }})</span>@endif</td>
          <td data-label="Type">{{ $pieces($d['description']) }}</td>
          <td class="l" data-label="Fits">@if(!empty($d['fits']))<span class="p-notes">{{ implode(' · ', array_slice($d['fits'], 0, 2)) }}@if(count($d['fits']) > 2) <a href="{{ $d['url'] }}">+{{ count($d['fits']) - 2 }} {{ __('more') }}</a>@endif</span>@else<span class="p-muted">-</span>@endif</td>
          <td data-label="Weight">{{ $d['weight'] ? number_format($d['weight'], 1) . ' lb' : '-' }}</td>
        </tr>
      @endforeach
      </tbody>
    </table></div>
    @if($data['pages'] > 1)
      <div class="shop-pg">
        @if($data['page']>1)<a href="{{ $qs($data['page']-1) }}" aria-label="{{ __('Previous page') }}"><x-icon name="arrow-left"/></a>@else<span class="dis" aria-hidden="true"><x-icon name="arrow-left"/></span>@endif
        @php $start=max(1,$data['page']-2); $end=min($data['pages'],$start+4); $start=max(1,$end-4); @endphp
        @if($start>1)<a href="{{ $qs(1) }}">1</a>@if($start>2)<span class="dis">{{ __('…') }}</span>@endif @endif
        @for($i=$start;$i<=$end;$i++)@if($i==$data['page'])<span class="cur">{{ $i }}</span>@else<a href="{{ $qs($i) }}">{{ $i }}</a>@endif @endfor
        @if($end<$data['pages'])@if($end<$data['pages']-1)<span class="dis">{{ __('…') }}</span>@endif<a href="{{ $qs($data['pages']) }}">{{ $data['pages'] }}</a>@endif
        @if($data['page']<$data['pages'])<a href="{{ $qs($data['page']+1) }}" aria-label="{{ __('Next page') }}"><x-icon name="arrow-right"/></a>@else<span class="dis" aria-hidden="true"><x-icon name="arrow-right"/></span>@endif
      </div>
    @endif
    @else
      <div class="aempty"><b>{{ __('No complete driveshafts match') }} {{ $vLabel ? $vLabel : '' }}{{ $q ? ' “'.$q.'”' : '' }}</b>{{ __('Try the bare part number, a make or a model — or') }} <a class="textlink" href="/catalog/driveshafts">{{ __('find it by vehicle') }}</a>. <a class="textlink" href="/shop/complete-driveshafts/by-vehicle">{{ __('Clear search') }}</a></div>
    @endif
  </div>
</section>
<div class="img-pop" id="img-pop" aria-hidden="true"><img alt=""></div>
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('partials.ds-add')
@if(app()->getLocale() !== 'en')
<script>window.UDS_T=Object.assign(window.UDS_T||{},{!! json_encode(collect(['Pick a year first','Any year','Any model','Pick a make first'])->mapWithKeys(fn ($k) => [$k => __($k)]), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) !!});</script>
@endif
<script>var TT=function(s){return (window.UDS_T||{})[s]||s;};
(function(){var f=document.querySelector('[data-vfilter]');if(!f)return;var S={make:f.querySelector('[data-v=make]'),year:f.querySelector('[data-v=year]'),model:f.querySelector('[data-v=model]')};
function fill(sel,list,empty){sel.innerHTML='<option value="">'+empty+'</option>'+list.map(function(v){return '<option value="'+String(v).replace(/"/g,'&quot;')+'">'+String(v).replace(/</g,'&lt;')+'</option>';}).join('');}
function load(){var make=S.make.value,year=S.year.value;S.year.disabled=!make;S.model.disabled=!year;if(!make){fill(S.year,[],TT("Pick a make first"));fill(S.model,[],TT("Pick a year first"));return Promise.resolve();}
  f.classList.add('is-loading');return fetch('/api/catalog/driveshafts/options?make='+encodeURIComponent(make)+(year?'&year='+encodeURIComponent(year):''),{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(d){
    fill(S.year,d.years||[],TT("Any year"));S.year.value=year&&(d.years||[]).indexOf(year)>-1?year:'';S.year.disabled=false;
    fill(S.model,d.models||[],S.year.value?TT("Any model"):TT("Pick a year first"));S.model.disabled=!S.year.value;}).finally(function(){f.classList.remove('is-loading');});}
S.make.addEventListener('change',function(){S.year.value='';S.model.value='';load().then(function(){f.submit();});});
S.year.addEventListener('change',function(){S.model.value='';load().then(function(){f.submit();});});
S.model.addEventListener('change',function(){f.submit();});
f.addEventListener('submit',function(){f.querySelector('[name=page]')&&f.querySelector('[name=page]').remove();});
})();
</script>
@endsection
