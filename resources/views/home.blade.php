@extends('layouts.app')
@section('bodyclass','night')
@section('title','Driveshaft Parts & Complete Driveshafts | Universal Driveshafts')
@section('desc','U-joints, yokes, center support bearings, flanges and tubing in stock. Complete driveshafts by vehicle, premade or built to measure. Ships from Florida.')
@push('head')
<link rel="stylesheet" href="/css/night.css?v={{ @filemtime(public_path('css/night.css')) }}">
@endpush
@section('content')
@php
  // vehicle-finder totals from the finder itself (the make dropdown counts the same list); the layout reuses this
  $udsFinder = \Illuminate\Support\Facades\Cache::remember('uds_finder_totals', 3600, function () {
      try {
          return ['makes' => count(app(\App\Services\DriveshaftFinder::class)->makes()),
                  'shafts' => \App\Services\DriveshaftFinder::fitments()->distinct()->count('ds_fitments.part_number')];
      } catch (\Throwable $e) { return ['makes' => 43, 'shafts' => 4400]; }
  });
@endphp

{{-- 1. Hero: the part itself, then the vehicle bar --}}
<section class="n-hero n-dark">
  <div class="n-hero__glow" aria-hidden="true"></div>
  <div class="n-wrap n-hero__grid">
    <div class="n-hero__in">
      <span class="n-kicker">{!! __('Driveshaft Components, Complete Driveshafts, Driveshaft Performance') !!}</span>
      <h1 class="n-h">{!! __('The driveshaft parts you need,') !!} <em>{!! __('on the shelf today.') !!}</em></h1>
      <p class="n-lead">{!! __('U-joints, yokes, center support bearings, flanges, slip assemblies and tubing, 1310 to 1810 series, cross-referenced to the part numbers you already run. Order stock before 2pm ET and it ships the same day.') !!}</p>
      <div class="n-hero__cta">
        <a class="btn btn-primary btn-lg" href="/shop/driveshaft-components">{!! __('Shop components') !!} <x-icon name="arrow-right"/></a>
        <a class="btn btn-lg n-btn-ghost" href="/catalog/driveshafts"><x-icon name="crosshair"/> {!! __('Find by vehicle') !!}</a>
      </div>
      <x-google-rating variant="dark" class="n-hero__rating"/>
    </div>
    <div class="n-hero__part">
      <picture>
        <source media="(max-width:700px)" type="image/webp" srcset="{{ asset('img/hero/hero-ujoint-sm.webp') }}">
        <source type="image/webp" srcset="{{ asset('img/hero/hero-ujoint.webp') }}">
        <img src="{{ asset('img/hero/hero-ujoint-sm.png') }}" width="1400" height="1344" alt="{!! __('A UDS universal joint with the UDS stamp on the cross') !!}" fetchpriority="high">
      </picture>
    </div>
  </div>
  <div class="n-wrap n-vehicle">
    <form class="n-vehicle__box" id="n-vehicle" action="/catalog/driveshafts" method="get" autocomplete="off">
      <div class="n-vehicle__t"><x-icon name="crosshair"/> {!! __('Find the complete driveshaft for your vehicle') !!} <small>{{ __(':makes makes, :n driveshafts', ['makes' => $udsFinder['makes'], 'n' => number_format($udsFinder['shafts'])]) }}</small></div>
      <div class="n-vehicle__row">
        <label class="n-sel" id="nv-make-w"><span class="n-sel__k">{!! __('1. Make') !!}</span><select name="make" id="nv-make" aria-label="{!! __('Make') !!}" required><option value="">{!! __('Choose a make') !!}</option></select><x-icon name="caret-down"/></label>
        <label class="n-sel" id="nv-year-w"><span class="n-sel__k">{!! __('2. Year') !!}</span><select name="year" id="nv-year" aria-label="{!! __('Year') !!}" disabled><option value="">{!! __('Year') !!}</option></select><x-icon name="caret-down"/></label>
        <label class="n-sel" id="nv-model-w"><span class="n-sel__k">{!! __('3. Model') !!}</span><select name="model" id="nv-model" aria-label="{!! __('Model') !!}" disabled><option value="">{!! __('Model') !!}</option></select><x-icon name="caret-down"/></label>
        <button class="btn btn-primary" type="submit" id="nv-go">{!! __('Find my driveshaft') !!} <x-icon name="arrow-right"/></button>
      </div>
      <p class="n-vehicle__hint" id="nv-hint">{!! __('Start with the make. The year and model lists come straight from the fitment catalog, so only real combinations show up.') !!}</p>
    </form>
  </div>
</section>
<div class="n-proof"><div class="n-wrap">
  <div><b>{{ number_format($udsCatalog['parts']) }}</b><span>{!! __('parts on the shelf') !!}</span></div>
  <div><b>{{ $udsFinder['makes'] }}</b><span>{!! __('makes in the vehicle finder') !!}</span></div>
  <div><b>32</b><span>{!! __('u-joint series stocked') !!}</span></div>
  <div><b>2018</b><span>{!! __('building since') !!}</span></div>
</div></div>

{{-- 2. Three ways to get a complete shaft --}}
<section class="n-sec">
  <div class="n-wrap">
    <div class="n-head">
      <div>
        <span class="n-kicker">{!! __('Complete driveshafts') !!}</span>
        <h2 class="n-h n-h2">{!! __('Find it, buy it, or have us build it') !!}</h2>
        <p class="n-lead">{!! __('Most jobs are one component. When the shaft itself is the problem, there are three ways through.') !!}</p>
      </div>
    </div>
    <div class="n-paths">
      <a class="n-path" href="/catalog/driveshafts">
        <span class="n-path__media n-path__media--logos">
          @foreach (['ford','chevrolet','dodge','toyota','jeep','gmc','nissan','ram'] as $mk)
            @if(file_exists(public_path("img/makes/$mk.png")))<img src="{{ media_url("/img/makes/$mk.png", 160) }}" alt="{{ ucfirst($mk) }}" loading="lazy" width="80" height="80">@endif
          @endforeach
        </span>
        <span class="n-path__body"><small>{!! __('By vehicle') !!}</small><b>{!! __('Find the shaft for your truck') !!}</b><p>{{ __('Make, year and model. :n driveshafts across :makes makes, each with its exact part number and price.', ['makes' => $udsFinder['makes'], 'n' => number_format($udsFinder['shafts'])]) }}</p><span class="n-path__go">{!! __('Find by vehicle') !!} <x-icon name="arrow-right"/></span></span>
      </a>
      <a class="n-path" href="/online-builder">
        <span class="n-path__media n-path__media--shot">
          <picture><source srcset="{{ asset('img/home/builder-shot.webp') }}" type="image/webp"><img src="{{ asset('img/home/builder-shot.png') }}" width="1200" height="750" loading="lazy" alt="{!! __('The online driveshaft builder: duty, series and yoke steps with a live price') !!}"></picture>
        </span>
        <span class="n-path__body"><small>{!! __('To your measurements') !!}</small><b>{!! __('Build one in the online builder') !!}</b><p>{!! __('Pick duty, series and yokes, enter your measurements and watch the price update. One piece or two, steel or aluminum.') !!}</p><span class="n-path__go">{!! __('Open the builder') !!} <x-icon name="arrow-right"/></span></span>
      </a>
      <a class="n-path" href="/premade-driveshafts">
        <span class="n-path__media n-path__media--fit">
          <picture><source srcset="{{ asset('img/home/premade-shaft.webp') }}" type="image/webp"><img src="{{ asset('img/home/premade-shaft.png') }}" width="1000" height="1000" loading="lazy" alt="{!! __('A premade UDS driveshaft, assembled and balanced') !!}"></picture>
        </span>
        <span class="n-path__body"><small>{!! __('Ready to ship') !!}</small><b>{!! __('Buy a premade driveshaft') !!}</b><p>{!! __('Common fitments, assembled and balanced, packed and out the door the day you order.') !!}</p><span class="n-path__go">{!! __('Shop premade') !!} <x-icon name="arrow-right"/></span></span>
      </a>
    </div>
  </div>
</section>

{{-- 3. Shop by component --}}
<section class="n-sec n-sec--tint">
  <div class="n-wrap">
    <div class="n-head">
      <div>
        <span class="n-kicker">{!! __('Parts catalog') !!}</span>
        <h2 class="n-h n-h2">{!! __('Shop by component') !!}</h2>
        <p class="n-lead">{{ __(':parts parts across :cats categories, cross-referenced to the series you already run.', ['parts' => number_format($udsCatalog['parts']), 'cats' => $udsCatalog['cats']]) }}</p>
      </div>
      <a class="n-link" href="/shop">{!! __('All categories') !!} <x-icon name="arrow-right"/></a>
    </div>
    @php $tilePhoto = ['complete-driveshafts'=>'tile-complete-driveshafts','driveshaft-components'=>'tile-driveshaft-components','custom-cut-tubing'=>'tile-steel-tube','small-parts'=>'small-parts']; @endphp
    <div class="n-groups">
      @foreach ($udsCatalog['groups'] as $g)
        @php $ph = $tilePhoto[$g['slug']] ?? 'tile-steel-tube'; @endphp
        <a class="n-group" href="/shop/{{ $g['slug'] }}">
          <img src="{{ asset('img/photos/'.$ph.'.webp') }}" alt="" loading="lazy" width="1000" height="750">
          <span class="n-group__t"><small class="n-mono">{{ __(':n parts', ['n' => number_format($g['count'])]) }}</small><b>{{ __($g['label']) }}</b></span>
        </a>
      @endforeach
    </div>
    <div class="n-cats">
      @foreach (array_slice($categories, 0, 11) as [$name,$slug,$count,$img])
        <a class="n-cat" href="{{ app(\App\Services\CatalogService::class)->categoryPath($slug) }}"><img src="{{ media_url($img, 320) }}" alt="" loading="lazy" width="46" height="46"><span><b>{{ __($name) }}</b><span>{{ __(':n parts', ['n' => $count]) }}</span></span></a>
      @endforeach
      <a class="n-cat n-cat--all" href="/shop">{{ __('All :cats categories', ['cats' => $udsCatalog['cats']]) }} <x-icon name="arrow-right"/></a>
    </div>
  </div>
</section>

{{-- 4. Best sellers --}}
<section class="n-sec">
  <div class="n-wrap">
    <div class="n-head">
      <div>
        <span class="n-kicker">{!! __('Live from the shelf') !!}</span>
        <h2 class="n-h n-h2">{!! __('Moving fastest this month') !!}</h2>
        <p class="n-lead">{!! __('Real counts, not a ranking we made up.') !!}</p>
      </div>
      <a class="n-link" href="/shop">{!! __('Full parts catalog') !!} <x-icon name="arrow-right"/></a>
    </div>
    <div class="n-rail">
      @foreach ($bestsellers as [$name,$slug,$price,$img])
        <a class="n-prod" href="/parts/{{ rawurlencode(strtok($name, ' ')) }}">
          <span class="n-prod__media"><img src="{{ media_url($img, 320) }}" alt="{{ $name }}" loading="lazy" width="320" height="320"></span>
          <span class="n-prod__body">
            <span class="n-prod__sku">{{ strtok($name,' ') }}</span>
            <span class="n-prod__name">{{ trim(substr($name, strpos($name,' ') ?: 0)) }}</span>
            <span class="n-prod__foot"><span class="n-prod__price">${{ number_format($price,2) }}</span><span class="n-prod__stock">{!! __('IN STOCK') !!}</span></span>
          </span>
        </a>
      @endforeach
    </div>
  </div>
</section>

{{-- 5. Builder band: content on the site grid --}}
<section class="n-band n-dark">
  <div class="n-band__media"><picture><source srcset="{{ asset('img/photos/band-fabrication.webp') }}" type="image/webp"><img src="{{ asset('img/photos/band-fabrication.jpg') }}" width="1800" height="1000" loading="lazy" alt="{!! __('A welder joining steel driveshaft tube in the shop') !!}"></picture></div>
  <div class="n-wrap"><div class="n-band__in">
    <span class="n-kicker">{!! __('Live pricing as you spec it') !!}</span>
    <h2 class="n-h n-h2">{!! __('Cannot find your shaft? Give us the numbers.') !!}</h2>
    <p class="n-lead">{!! __('Six measurements and your yoke style is all it takes. The builder prices the exact assembly before you commit.') !!}</p>
    <div><a class="btn btn-primary btn-lg" href="/online-builder">{!! __('Start the online builder') !!} <x-icon name="arrow-right"/></a></div>
    <ul class="n-band__specs">
      <li><x-icon name="gauge"/> {!! __('One piece and two piece') !!}</li>
      <li><x-icon name="factory"/> {!! __('Steel and aluminum tube') !!}</li>
      <li><x-icon name="certificate"/> {!! __('Balanced and certified') !!}</li>
    </ul>
  </div></div>
</section>

{{-- 6. Series we stock, and the four assurances --}}
<section class="n-sec">
  <div class="n-wrap">
    <div class="n-head">
      <div>
        <span class="n-kicker">{!! __('Series we stock') !!}</span>
        <h2 class="n-h n-h2">{!! __('1310 to 1810, on the shelf') !!}</h2>
      </div>
      <a class="n-link" href="/shop/driveshaft-components/u-joints">{!! __('All universal joints') !!} <x-icon name="arrow-right"/></a>
    </div>
    <ul class="n-series">
      <li>1310</li><li>1330</li><li>1350</li><li>1410</li><li>1480</li><li>1550</li><li>1610</li><li>1710</li><li>1810</li><li class="is-more">{!! __('and 23 more') !!}</li>
    </ul>
    <div class="n-assure">
      <div><x-icon name="factory"/><div><b>{!! __('Built in the USA') !!}</b><span>{!! __('Balanced in our Florida shop') !!}</span></div></div>
      <div><x-icon name="truck"/><div><b>{{ __('Free over :amount', ['amount' => '$'.number_format(config('uds.free_shipping_min'))]) }}</b><span>{!! __('Same-day dispatch on stock') !!}</span></div></div>
      <div><x-icon name="headset"/><div><b>{!! __('Talk to a driveline tech') !!}</b><span>{!! __('Not a call center') !!}</span></div></div>
      <div><x-icon name="returns"/><div><b>{!! __('14-day returns') !!}</b><span>{!! __('Our mistake? Return shipping is on us') !!}</span></div></div>
    </div>
  </div>
</section>

@endsection
@push('scripts')
<script>
(function(){
  /* hero vehicle bar: driven by the finder's own step API, so the options are always the real catalog */
  var sel={make:document.getElementById('nv-make'),year:document.getElementById('nv-year'),model:document.getElementById('nv-model')};
  var form=document.getElementById('n-vehicle'); if(!form) return;
  var chosen={};
  function fill(el,label,opts){el.innerHTML='<option value="">'+udsT('Choose :what (:n)',{what:udsT(label).toLowerCase(),n:opts.length})+'</option>'+opts.map(function(o){var d=document.createElement('option');d.value=o;d.textContent=o;return d.outerHTML;}).join('');el.disabled=false;el.closest('.n-sel').classList.remove('is-set');}
  function mark(){['make','year','model'].forEach(function(k){sel[k].closest('.n-sel').classList.toggle('is-set',!!sel[k].value);});}
  function reset(keys){keys.forEach(function(k){sel[k].innerHTML='<option value="">'+udsT(k.charAt(0).toUpperCase()+k.slice(1))+'</option>';sel[k].disabled=true;delete chosen[k];});}
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
      if(!sel[k].value){reset(all.slice(i));mark();if(i===0)next();return;}
      chosen[k]=sel[k].value; reset(all.slice(i+1)); mark(); next();
    });
  });
  next();
})();
</script>
@endpush
