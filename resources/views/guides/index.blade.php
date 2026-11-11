@extends('layouts.app')
@section('title', __('Driveshaft Guides: Measuring, U-Joints, Vibration | UDS'))
@section('desc', __('Shop-written driveshaft guides: measuring a driveshaft, u-joint series identification, vibration diagnosis, tubing, yokes, pinion angle and repair vs replace.'))
@section('canonical','/guides')

@php $base = rtrim(config('app.url'), '/'); @endphp
@push('schema')
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org',
  '@@type' => 'CollectionPage',
  '@@id' => $base . '/guides',
  'url' => $base . '/guides',
  'name' => 'Driveshaft guides',
  'description' => 'Technical guides on measuring, specifying, diagnosing and repairing driveshafts, written by the Universal Driveshafts shop.',
  'isPartOf' => ['@@id' => $base . '/#website'],
  'publisher' => ['@@id' => $base . '/#organization'],
  'mainEntity' => [
    '@@type' => 'ItemList',
    'itemListElement' => collect($guides)->values()->map(fn ($g, $i) => [
      '@@type' => 'ListItem', 'position' => $i + 1, 'url' => $base . '/guides/' . array_keys($guides)[$i], 'name' => $g['h1'],
    ])->all(),
  ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@include('partials.breadcrumb-schema', ['crumbs' => [['Home', $base . '/'], ['Guides', $base . '/guides']]])
@endpush

@section('content')
@if(i18n_view('guides.index'))
@include(i18n_view('guides.index'))
@else
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><span aria-current="page">Guides</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">From the shop floor</span>
      <h1>Driveshaft guides: measure it, identify it, fix it</h1>
      <p class="lead">Everything we explain over the counter, written down. How to measure a one- or two-piece shaft, read a u-joint series off a cap, chase a vibration to its cause, size tubing, set pinion angle and decide whether the shaft is worth repairing.</p>
      <div class="phero__cta">
        <a href="/guides/how-to-measure-a-driveshaft" class="btn btn-primary btn-lg"><x-icon name="ruler"/> Start with measuring</a>
        <a href="/online-builder" class="btn btn-ghost btn-lg">Open the online builder</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">{{ count($guides) }}</b><span>technical guides</span></li>
        <li><b class="u-mono">10</b><span>u-joint series charted</span></li>
        <li><b class="u-mono">1</b><span>shop that builds what it writes about</span></li>
      </ul>
    </div>
    <div class="phero__media phero__media--photo"><picture><source srcset="{{ asset('img/photos/hero-weld.webp') }}" type="image/webp"><img src="{{ asset('img/photos/hero-weld.jpg') }}" alt="Welding a driveshaft tube to a yoke at Universal Driveshafts" width="1200" height="900" loading="eager" fetchpriority="high"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>All guides</h2><p>Each one is written for someone standing under the vehicle with a tape measure, and links straight to the parts it talks about.</p></div>
  <div class="guide-grid">
    @foreach($guides as $slug => $g)
      <a class="guide-card" href="/guides/{{ $slug }}">
        <span class="guide-card__top"><span class="guide-card__ic"><x-icon :name="$g['icon']"/></span><span class="guide-card__kicker u-mono">{{ $g['kicker'] }}</span></span>
        <h3>{{ strtok($g['h1'], ':') }}</h3>
        <p>{{ $g['card'] }}</p>
        <span class="guide-card__go">Read the guide <x-icon name="arrow-right"/></span>
      </a>
    @endforeach
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>Where the guides lead</h2><p>The guides explain the decisions. These are the tools and categories that act on them.</p></div>
  @php $catImg = collect(config('uds.categories'))->keyBy(1); @endphp
  <div class="uhub guide-hub">
    <a class="uhubcard" href="/online-builder"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-complete-driveshafts.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Online driveshaft builder</b><span>Spec it, see the price</span></span></a>
    <a class="uhubcard" href="/driveshaft-designer"><span class="uhubcard__media"><img src="{{ asset('img/photos/panel-custom.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Driveshaft designer</b><span>Draw it, dimension it, print</span></span></a>
    <a class="uhubcard" href="/catalog/driveshafts"><span class="uhubcard__media"><img src="{{ asset('img/photos/hero-overland-sm.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Find by vehicle</b><span>Year, make and model</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/u-joints"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['u-joints'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>Universal joints</b><span>179 parts by series</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-components/center-support-bearing"><span class="uhubcard__media uhubcard__media--part"><img src="{{ media_url($catImg['center-support-bearing'][3] ?? null, 320) }}" alt="" loading="lazy" width="320" height="320"></span><span class="uhubcard__body"><b>Center support bearings</b><span>324 parts</span></span></a>
    <a class="uhubcard" href="/shop/driveshaft-tubing/tubing"><span class="uhubcard__media"><img src="{{ asset('img/photos/tile-steel-tube.webp') }}" alt="" loading="lazy" width="1000" height="750"></span><span class="uhubcard__body"><b>Cut-to-length tubing</b><span>DOM steel, sold by the foot</span></span></a>
  </div>
</div></section>

<section class="section"><div class="wrap"><div class="ctaband">
  <h2>Measured it? We will build it</h2>
  <p>One- and two-piece driveshafts built to your numbers, balanced in Florida and shipped nationwide. Call (813) 524-6491 or start online.</p>
  <div class="cta"><a href="/online-builder" class="btn btn-primary">Open the online builder <x-icon name="arrow-right"/></a><a href="/contact" class="btn btn-ghost">Talk to a tech</a></div>
</div></div></section>
@endif
@endsection
