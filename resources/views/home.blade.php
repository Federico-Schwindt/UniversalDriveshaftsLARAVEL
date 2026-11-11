@extends('layouts.app')
@section('title','Driveshaft Parts & Complete Driveshafts | Universal Driveshafts')
@section('desc','U-joints, yokes, center support bearings, flanges and tubing in stock. Complete driveshafts by vehicle, premade or built to measure. Ships from Florida.')
@section('content')
@php
  // vehicle-finder totals from the finder itself (the make dropdown counts the same list)
  $udsFinder = \Illuminate\Support\Facades\Cache::remember('uds_finder_totals', 3600, function () {
      try {
          return ['makes' => count(app(\App\Services\DriveshaftFinder::class)->makes()),
                  'shafts' => \App\Services\DriveshaftFinder::fitments()->distinct()->count('ds_fitments.part_number')];
      } catch (\Throwable $e) { return ['makes' => 43, 'shafts' => 4400]; }
  });
  $cs = app(\App\Services\CatalogService::class);
  $components = $cs->group('driveshaft-components')['subcategories'] ?? [];
  // popular parts: the SKUs picked in config/uds.php, with live price and stock so the Add button sells the real thing
  $popular = \Illuminate\Support\Facades\Cache::remember('home_popular', 600, function () use ($cs) {
      try {
          $skus = array_map(fn ($b) => strtok($b[0], ' '), config('uds.bestsellers', []));
          $ids = \Illuminate\Support\Facades\DB::connection('wp')->table('wp_postmeta')->where('meta_key', '_sku')->whereIn('meta_value', $skus)->pluck('post_id', 'meta_value')->all();
          $rows = collect($cs->productsByIds(array_values($ids)))->keyBy('sku');
          return collect($skus)->map(fn ($s) => $rows[$s] ?? null)->filter()->take(6)->values()->all();   // one row on a desktop
      } catch (\Throwable $e) { return []; }
  });
@endphp

{{-- 1. Hero: a finished driveshaft on our bench (owner's pick, 2026-11) behind what we sell, and the fastest way in (a vehicle or a build) --}}
<section class="hm-hero">
  <picture class="hm-hero__bg" aria-hidden="true">
    <source type="image/webp" srcset="{{ asset('img/photos/tile-complete-driveshafts.webp') }}">
    <img src="{{ asset('img/photos/tile-complete-driveshafts.jpg') }}" alt="" width="1000" height="750" fetchpriority="high">
  </picture>
  <div class="wrap hm-top__grid">
  <div class="hm-intro">
    <h1>{!! __('Driveshaft parts and complete driveshafts') !!}</h1>
    <p>{!! __('U-joints, yokes, center support bearings, flanges and tubing from 1310 to 1810 series. Stock orders placed by 2 pm EST ship the same day.') !!}</p>
    <div class="hm-intro__cta">
      <a class="btn btn-primary btn-lg" href="/shop/driveshaft-components">{!! __('Shop components') !!}</a>
      <a class="btn btn-lg hm-btn-light" href="/online-builder"><x-icon name="wrench"/> {!! __('Build a driveshaft') !!}</a>
    </div>
    <ul class="hm-intro__facts">
      <li><b>{{ number_format($udsCatalog['parts']) }}</b> {!! __('parts in stock') !!}</li>
      <li><b>1310–1810</b> {!! __('series') !!}</li>
      <li><b>{!! __('Same day') !!}</b> {!! __('shipping by 2 pm EST') !!}</li>
    </ul>
  </div>
  <form class="hm-finder" id="n-vehicle" action="/catalog/driveshafts" method="get" autocomplete="off">
    <h2><x-icon name="car"/> {!! __('Find a driveshaft for your vehicle') !!}</h2>
    <p class="hm-finder__n">{{ __(':n driveshafts for :makes makes', ['makes' => $udsFinder['makes'], 'n' => number_format($udsFinder['shafts'])]) }}</p>
    <ol class="hm-steps">
      <li class="n-sel"><label for="nv-make"><i>1</i>{!! __('Make') !!}</label><select name="make" id="nv-make" required><option value="">{!! __('Choose a make') !!}</option></select></li>
      <li class="n-sel"><label for="nv-year"><i>2</i>{!! __('Year') !!}</label><select name="year" id="nv-year" disabled><option value="">{!! __('Pick a make first') !!}</option></select></li>
      <li class="n-sel"><label for="nv-model"><i>3</i>{!! __('Model') !!}</label><select name="model" id="nv-model" disabled><option value="">{!! __('Pick a year first') !!}</option></select></li>
    </ol>
    <button class="btn btn-primary btn-block" type="submit" id="nv-go" disabled>{!! __('Find my driveshaft') !!}</button>
    <p class="hm-finder__next" id="nv-next" hidden>{!! __('Drive and shaft position come next, with every matching driveshaft and its price.') !!}</p>
  </form>
  </div>
</section>

{{-- 2. Ways to buy a complete shaft --}}
<section class="hm-sec"><div class="wrap">
  <ul class="hm-ways">
    <li><a href="/catalog/driveshafts"><x-icon name="car"/><b>{!! __('By vehicle') !!}</b><span>{!! __('Year, make and model') !!}</span></a></li>
    <li><a href="/online-builder"><x-icon name="wrench"/><b>{!! __('Built to your length') !!}</b><span>{!! __('Custom length, priced online') !!}</span></a></li>
    <li><a href="/premade-driveshafts"><x-icon name="package"/><b>{!! __('Premade driveshafts') !!}</b><span>{!! __('Assembled, balanced, in stock') !!}</span></a></li>
    <li><a href="/quick-order"><x-icon name="list-numbers"/><b>{!! __('Quick order') !!}</b><span>{!! __('Paste a list of part numbers') !!}</span></a></li>
  </ul>
</div></section>

{{-- 3. The catalog itself --}}
<section class="hm-sec"><div class="wrap">
  <div class="catsec__head">
    <h2><a href="/shop/driveshaft-components">{!! __('Driveshaft components') !!}</a> <span>{{ number_format(array_sum(array_column($components, 'count'))) }}</span></h2>
    <a class="textlink" href="/shop">{{ __('All :cats categories', ['cats' => $udsCatalog['cats']]) }} <x-icon name="arrow-right"/></a>
  </div>
  @include('shop.partials.cat-tiles', ['group' => 'driveshaft-components', 'subs' => $components])
</div></section>

@if($popular)
<section class="hm-sec"><div class="wrap">
  <div class="catsec__head"><h2>{!! __('Popular parts') !!}</h2></div>
  <div class="pgrid">
    @foreach($popular as $p)
      @php $url = '/parts/' . rawurlencode($p['sku']); @endphp
      <div class="pcard ba-also__card">
        <a class="pcard__media" href="{{ $url }}" tabindex="-1" aria-hidden="true">@if($p['image'])<img src="{{ $p['image'] }}" alt="" width="240" height="240" loading="lazy">@else<x-icon name="package"/>@endif</a>
        <span class="pcard__body">
          <a class="pcard__sku" href="{{ $url }}">{{ $p['sku'] }}</a>
          <a class="pcard__name" href="{{ $url }}">{{ \Illuminate\Support\Str::limit($cs->cleanName($p), 64) }}</a>
          <span class="pcard__foot"><b>@if($p['price'] > 0)${{ number_format($p['price'], 2) }}@else{{ __('Call for price') }}@endif</b><small class="{{ $p['stock_status'] === 'instock' ? 'pcard__ok' : '' }}">{{ $p['stock_status'] === 'instock' ? __('In stock') : __('Call') }}</small></span>
          @if($p['price'] > 0)<button type="button" class="p-plus ba-also__add" data-add="{{ $p['id'] }}" aria-label="{{ __('Add :sku to cart', ['sku' => $p['sku']]) }}"><x-icon name="cart"/><span>{{ __('Add to cart') }}</span></button>@endif
        </span>
      </div>
    @endforeach
  </div>
</div></section>
@endif

{{-- 4. Why order here: four facts, one line each --}}
<section class="hm-sec"><div class="wrap">
  <ul class="hm-trust">
    <li><x-icon name="truck"/><div><b>{{ __('Free shipping over :amount', ['amount' => '$'.number_format(config('uds.free_shipping_min'))]) }}</b><span>{!! __('U.S. parts orders, same-day dispatch') !!}</span></div></li>
    <li><x-icon name="factory"/><div><b>{!! __('Built in the USA') !!}</b><span>{!! __('Assembled and balanced in Florida') !!}</span></div></li>
    <li><x-icon name="headset"/><div><b>{!! __('Talk to a driveline tech') !!}</b><span><a href="tel:18135246491">(813) 524-6491</a></span></div></li>
    <li><x-icon name="returns"/><div><b>{!! __('14-day returns') !!}</b><span><a href="/return-policy">{!! __('Returns and warranty') !!}</a></span></div></li>
  </ul>
</div></section>

{{-- 5. Who we are, for people and search engines: short --}}
<section class="hm-sec hm-about"><div class="wrap">
  <h2>{!! __('Universal Driveshafts') !!}</h2>
  <p>{!! __('We are a driveshaft shop in Florida that also stocks the parts: universal joints, flange, weld, slip and end yokes, center support bearings, companion flanges, CV joints and DOM tubing, cross-referenced to the Spicer and OEM numbers you already run. Complete driveshafts are sold by vehicle, premade for common fitments, or built to your measurements and balanced before they ship.') !!}</p>
</div></section>

<div class="stock-pop" id="stock-pop"></div>
@endsection
@push('scripts')
@include('shop.partials.js-i18n')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
<script>
(function(){
  /* vehicle finder: driven by the finder's own step API, so the options are always the real catalog */
  var sel={make:document.getElementById('nv-make'),year:document.getElementById('nv-year'),model:document.getElementById('nv-model')};
  var form=document.getElementById('n-vehicle'); if(!form) return;
  var chosen={};
  function fill(el,label,opts){el.innerHTML='<option value="">'+udsT('Choose :what (:n)',{what:udsT(label).toLowerCase(),n:opts.length})+'</option>'+opts.map(function(o){var d=document.createElement('option');d.value=o;d.textContent=o;return d.outerHTML;}).join('');el.disabled=false;el.closest('.n-sel').classList.remove('is-set');}
  function mark(){['make','year','model'].forEach(function(k){sel[k].closest('.n-sel').classList.toggle('is-set',!!sel[k].value);});}
  var WAIT={year:udsT('Pick a make first'),model:udsT('Pick a year first')};
  function reset(keys){keys.forEach(function(k){sel[k].innerHTML='<option value="">'+(WAIT[k]||udsT('Choose a make'))+'</option>';sel[k].disabled=true;delete chosen[k];});}
  /* the button says what it will open, and stays off until there is a make */
  var go=document.getElementById('nv-go'), nxt=document.getElementById('nv-next'), GO=go.textContent;
  function button(){
    go.disabled=!chosen.make;
    go.textContent=chosen.model?udsT('See driveshafts for :vehicle',{vehicle:[chosen.year,chosen.make,chosen.model].join(' ')}):(chosen.make?udsT('See :make driveshafts',{make:chosen.make}):GO);
    nxt.hidden=!chosen.model;
  }
  function step(){
    var q=new URLSearchParams(chosen).toString();
    return fetch('/api/catalog/driveshafts/step'+(q?'?'+q:''),{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();});
  }
  function next(){
    step().then(function(d){
      var n=d&&d.next; if(!n) return;
      if(sel[n.facet]) fill(sel[n.facet],n.label,n.options||[]);
    }).catch(function(){});
  }
  ['make','year','model'].forEach(function(k,i,all){
    sel[k].addEventListener('change',function(){
      if(!sel[k].value){reset(all.slice(i));mark();button();if(i===0)next();return;}
      chosen[k]=sel[k].value; reset(all.slice(i+1)); mark(); button(); next();
    });
  });
  next();
})();
</script>
@endpush
