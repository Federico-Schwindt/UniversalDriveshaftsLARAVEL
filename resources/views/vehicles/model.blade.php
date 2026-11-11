@extends('layouts.app')
@php
  use App\Services\VehiclePages;
  use App\Services\DriveshaftFinder;
  $mm = "$make $model"; $span = $y1 === $y2 ? (string) $y1 : "{$y1}–{$y2}";
  $money = fn ($n) => VehiclePages::money($n);
  $hasNotes = collect($items)->contains(fn ($i) => $i['notes'] !== '');
  $base = rtrim(config('app.url'), '/');

  /* ItemList JSON-LD: one Product per part number (capped), each with the vehicles it fits. */
  $byPart = collect($items)->groupBy('part_number')->take(60);
  $products = []; $pos = 0;
  foreach ($byPart as $pn => $rows) {
      $first = $rows->first(); $pos++;
      $vehicles = [];
      foreach ($rows->groupBy('drive') as $drive => $g) {
          $yrs = $g->flatMap(fn ($i) => explode(',', $i['years_csv']))->map('intval')->unique()->sort()->values();
          $cfg = array_filter(['vehicleConfiguration' => $drive !== '' ? $drive : null]);
          if ($yrs->count() <= 12) {
              foreach ($yrs as $yy) $vehicles[] = ['@type' => 'Vehicle', 'name' => trim("$yy $mm $drive"), 'brand' => ['@type' => 'Brand', 'name' => $make], 'model' => $model, 'vehicleModelDate' => (string) $yy] + $cfg;
          } else {
              foreach (explode(', ', VehiclePages::yearRanges($yrs->all())) as $range) $vehicles[] = ['@type' => 'Vehicle', 'name' => trim("$range $mm $drive"), 'brand' => ['@type' => 'Brand', 'name' => $make], 'model' => $model] + $cfg;
          }
      }
      $products[] = ['@type' => 'ListItem', 'position' => $pos, 'item' => array_filter([
          '@type' => 'Product',
          'name' => trim(($first['description'] ?: 'Complete driveshaft') . ' ' . $first['sku']),
          'sku' => $first['sku'], 'mpn' => $pn, 'brand' => ['@type' => 'Brand', 'name' => 'UDS'],
          'category' => VehiclePages::CATEGORY,
          'url' => $base . $first['url'], 'image' => $first['image'] ? (str_starts_with($first['image'], 'http') ? $first['image'] : $base . $first['image']) : null,
          'description' => "Complete driveshaft {$first['sku']} for the $span $mm" . ($first['loc'] ? ', ' . strtolower($first['loc']) . ' position' : '') . '.',
          'isAccessoryOrSparePartFor' => $vehicles,
          'offers' => $first['can_buy'] ? ['@type' => 'Offer', 'url' => $base . $first['url'], 'price' => number_format($first['price'], 2, '.', ''), 'priceCurrency' => 'USD',
              'availability' => 'https://schema.org/InStock', 'itemCondition' => ($first['condition'] ?: 'new') === 'new' ? 'https://schema.org/NewCondition' : 'https://schema.org/UsedCondition'] : null,
      ])];
  }
@endphp
@section('title', $title)
@section('desc', $desc)
@section('canonical', $url)
@section('bodyclass','pg')
@push('schema')
@include('partials.breadcrumb-schema', ['crumbs' => [['Home', url('/')], ['Driveshafts by vehicle', url('/driveshafts')], [$make, url($make_url)], [$model, url($url)]]])
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org', '@@type' => 'FAQPage',
  'mainEntity' => array_map(fn ($f) => ['@@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@@type' => 'Answer', 'text' => $f['a']]], $faq),
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) !!}</script>
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org', '@@type' => 'ItemList',
  'name' => "$mm driveshafts $span", 'url' => url($url), 'numberOfItems' => $parts, 'itemListElement' => $products,
], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) !!}</script>
@endpush
@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" style="position:absolute;width:0;height:0;overflow:hidden" focusable="false"><symbol id="veh-plus" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a8,8,0,0,1-8,8H136v80a8,8,0,0,1-16,0V136H40a8,8,0,0,1,0-16h80V40a8,8,0,0,1,16,0v80h80A8,8,0,0,1,224,128Z"/></symbol></svg>
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <a href="/driveshafts">{{ __('Driveshafts by vehicle') }}</a><x-icon name="caret-right"/>
    <a href="{{ $make_url }}">{{ $make }}</a><x-icon name="caret-right"/>
    <span aria-current="page">{{ $model }}</span>
  </nav>
  <div class="pagehead__row">
    <div class="pagehead__title">
      @if($logo)<a class="pagehead__logo" href="{{ $make_url }}" aria-label="All {{ $make }} models"><img src="{{ $logo }}" alt="{{ $make }} logo" width="96" height="96"></a>@endif
      <h1>{{ __(':model driveshafts', ['model' => $mm]) }} <span class="veh-h1-years">({{ $y1 === $y2 ? __('year :y', ['y' => $y1]) : __('years :span', ['span' => "{$y1}–{$y2}"]) }})</span></h1>
    </div>
    <div class="btn-row"><a class="btn btn-primary btn-sm" href="#veh-table">{{ __('See part numbers by year') }}</a><a class="btn btn-ghost btn-sm" href="{{ $finder_url }}"><x-icon name="car"/> {{ __('Not sure? Use the vehicle finder') }}</a></div>
  </div>
  <p class="pagehead__sub">{{ $intro }}</p>
  <ul class="srs-facts" aria-label="{{ $mm }} coverage">
    <li><b>{{ $parts }}</b> {{ trans_choice('{1} Part number|[2,*] Part numbers', $parts) }}</li>
    <li><b>{{ $span }}</b> {{ __('Model years') }}</li>
    @if($positions)<li><b>{{ \Illuminate\Support\Str::ucfirst(implode(' + ', array_map(fn ($p) => __($p), $positions))) }}</b> {{ trans_choice('{1} Position|[2,*] Positions', count($positions)) }}</li>@endif
    @if($drives)<li><b>{{ implode(' · ', $drives) }}</b> {{ __('Drive') }}</li>@endif
    @if($price_min !== null)<li><b>{{ $money($price_min) }}</b> {{ __('Price from') }}</li>@endif
  </ul>
</div>

<section class="catsec"><div class="wrap">
  <div class="catsec__head"><h2 id="by-year">{{ __(':model driveshafts by model year', ['model' => $mm]) }}</h2></div>
  <p class="veh-lead">{{ __('Pick a year to filter the table. Each row is one complete shaft for one configuration; the same part number appears once per year range and drivetrain it fits.') }}</p>
  <div class="veh-years" id="veh-years" role="list" aria-label="{{ __('Filter by model year') }}">
    <a class="veh-chip is-on" role="listitem" href="#veh-table" data-veh-year="">{{ __('All years') }}</a>
    @foreach($years as $yy => $cnt)<a class="veh-chip" role="listitem" href="#y{{ $yy }}" data-veh-year="{{ $yy }}">{{ $yy }} <small>{{ $cnt }}</small></a>@endforeach
  </div>
  <div class="shop-count" id="veh-count">{{ trans_choice('{1} :n configuration|[2,*] :n configurations', count($items), ['n' => count($items)]) }} · {{ trans_choice('{1} :n part number|[2,*] :n part numbers', $parts, ['n' => $parts]) }}</div>
  <div class="ptbl-wrap"><table class="ptbl veh-table" id="veh-table">
    <thead><tr>
      <th>{{ __('Years') }}</th><th>{{ __('Position') }}</th><th>{{ __('Drive') }}</th>
      @foreach($cols as $c)<th>{{ DriveshaftFinder::LABELS[$c] }}</th>@endforeach
      @if($cbrgs)<th>{{ __('Ctr. brg') }}</th>@endif
      @if($hasNotes)<th class="l">{{ __('Notes') }}</th>@endif
      <th>{{ __('Part #') }}</th><th>{{ __('Price') }}</th><th>{{ __('Add') }}</th>
    </tr></thead>
    <tbody>
    @foreach($items as $d)
      <tr class="veh-row" data-years="{{ $d['years_csv'] }}">
        <td data-label="{{ __('Years') }}"><span class="veh-yr">{{ $d['years_label'] }}</span></td>
        <td data-label="{{ __('Position') }}" class="veh-loc">{{ $d['loc'] ? __($d['loc']) : '-' }}</td>
        <td data-label="{{ __('Drive') }}">{{ $d['drive'] ?: 'Any' }}</td>
        @foreach($cols as $c)<td data-label="{{ DriveshaftFinder::LABELS[$c] }}">{{ $d['facets'][$c] !== '' ? $d['facets'][$c] : 'Any' }}</td>@endforeach
        @if($cbrgs)<td data-label="Ctr. brg">{{ $d['c_brg'] ?: '-' }}</td>@endif
        @if($hasNotes)<td data-label="{{ __('Notes') }}" class="l">@if($d['notes'])<span class="p-notes">{{ $d['notes'] }}</span>@else<span class="p-muted">-</span>@endif</td>@endif
        <td data-label="Part #"><a class="p-pn" href="{{ $d['url'] }}" title="{{ $d['description'] ?: 'Complete driveshaft' }}">{{ $d['sku'] }}</a></td>
        <td data-label="{{ __('Price') }}"><div class="p-price">@if($d['can_buy'])<span class="now">{{ $money($d['price']) }}</span>@if($d['core_price'])<span class="save">+ {{ $money($d['core_price']) }} {{ __('core') }}</span>@endif @else<span class="p-muted">{{ __('Call for price') }}</span>@endif</div></td>
        <td data-label="{{ __('Add') }}"><span class="p-add">@if($d['can_buy'])<button class="p-plus" data-ds-add="{{ $d['part_number'] }}" aria-label="Add {{ $d['sku'] }} to cart" title="{{ __('Add to cart') }}"><svg class="i" width="1em" height="1em" aria-hidden="true"><use href="#veh-plus"/></svg></button>@else<a class="btn btn-line btn-sm" href="tel:+18135246491">{{ __('Call') }}</a>@endif</span></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
  <p class="veh-help">{!! __('Not sure about a detail? Call :phone and a driveline tech will confirm the fit before you order. Need a different length or a heavier series?', ['phone' => '<a href="tel:+18135246491">(813) 524-6491</a>']) !!} <a href="/online-builder">{{ __('We build driveshafts to spec') }}</a>.</p>
</div></section>

<section class="catsec"><div class="wrap">
  <div class="catsec__head"><h2>{{ __(':model driveshaft questions', ['model' => $mm]) }}</h2></div>
  <div class="pg-faq">
    @foreach($faq as $i => $f)
      <details><summary><h3>{{ $f['q'] }}</h3></summary><p>{{ $f['a'] }}</p></details>
    @endforeach
  </div>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  @if($siblings)
    <div class="catsec__head"><h2>{{ __('Other :make models', ['make' => $make]) }}</h2><a class="textlink" href="{{ $make_url }}">{{ __('All :make driveshafts', ['make' => $make]) }} <x-icon name="arrow-right"/></a></div>
    <div class="usiblings veh-sibs" role="list" aria-label="Other {{ $make }} models">
      @foreach($siblings as $s)
        <a class="usib" role="listitem" href="{{ $s['url'] }}"><span>@if($logo)<img src="{{ $logo }}" alt="" loading="lazy">@else<x-icon name="truck"/>@endif</span><b>{{ $s['model'] }}</b> <small>{{ $s['y1'] === $s['y2'] ? $s['y1'] : $s['y1'] . '–' . $s['y2'] }}</small></a>
      @endforeach
      @if($make_models > count($siblings) + 1)<a class="usib" role="listitem" href="{{ $make_url }}"><span><x-icon name="arrow-right"/></span><b>{{ __('All :n :make models', ['n' => $make_models, 'make' => $make]) }}</b></a>@endif
    </div>
  @endif
  <p class="veh-cta"><a class="btn btn-primary btn-sm" href="{{ $finder_url }}"><x-icon name="car"/> {{ __('Not sure? Use the vehicle finder') }}</a> <a class="textlink" href="/driveshafts">{{ __('All makes') }} <x-icon name="arrow-right"/></a></p>
  <p class="xref-note veh-note">{{ __('Vehicle makes, logos, models and manufacturer part numbers are trademarks of their respective owners, used for identification and fitment reference only. Universal Driveshafts is not affiliated with them. Fitment data may contain errors, verify before ordering.') }} <a href="/terms">{{ __('Terms of use') }}</a></p>
</div></section>
@include('partials.ds-add')
<script>
(function(){
  var chips=document.querySelectorAll('[data-veh-year]'),rows=document.querySelectorAll('#veh-table tbody tr'),count=document.getElementById('veh-count');
  if(!chips.length)return;
  function apply(y){var n=0,pn={};rows.forEach(function(r){var ok=!y||(','+r.getAttribute('data-years')+',').indexOf(','+y+',')>-1;r.classList.toggle('is-hidden',!ok);if(ok){n++;var a=r.querySelector('.p-pn');if(a)pn[a.textContent]=1;}});
    chips.forEach(function(c){c.classList.toggle('is-on',(c.getAttribute('data-veh-year')||'')===(y||''));});
    var k=Object.keys(pn).length;if(count)count.textContent=n+' configuration'+(n===1?'':'s')+' · '+k+' part number'+(k===1?'':'s')+(y?' for '+y:'');}
  function fromHash(){var m=(location.hash||'').match(/^#y(\d{4})$/);apply(m?m[1]:'');}
  chips.forEach(function(c){c.addEventListener('click',function(e){e.preventDefault();var y=c.getAttribute('data-veh-year');history.replaceState(null,'',y?'#y'+y:location.pathname);apply(y);});});
  window.addEventListener('hashchange',fromHash);fromHash();
})();
</script>
@endsection
