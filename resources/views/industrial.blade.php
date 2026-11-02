@extends('layouts.app')
@section('title', __('Industrial driveshafts | Universal Driveshafts'))
@section('desc', __('Industrial driveshafts for pumps, compressors, conveyors, generators and test stands. Built to your flange and length specification and balanced in Florida.'))
@section('content')
@if(i18n_view('industrial'))
@include(i18n_view('industrial'))
@else
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><a href="/online-builder">Driveshafts</a><x-icon name="caret-right"/><span aria-current="page">Industrial</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">Pumps, compressors, drives</span>
      <h1>Industrial driveshafts built to your flange and length</h1>
      <p class="lead">Fixed installations do not forgive a bad shaft: misalignment and imbalance go straight into bearings and seals. We build industrial shafts to the flange pattern, length and speed you specify, and balance them before they leave.</p>
      <div class="phero__cta">
        <a href="/contact" class="btn btn-primary btn-lg">Request a quote <x-icon name="arrow-right"/></a>
        <a href="/driveshaft-designer" class="btn btn-ghost btn-lg">Draw the shaft in the designer</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">Flange</b><span>companion, CV and Rzeppa patterns</span></li>
        <li><b class="u-mono">1310 to 1810</b><span>joint series by torque</span></li>
        <li><b class="u-mono">In house</b><span>welding, machining, balancing</span></li>
      </ul>
    </div>
    <div class="phero__media phero__media--photo"><picture><source srcset="{{ asset('img/photos/tile-steel-tube.webp') }}" type="image/webp"><img src="{{ asset('img/photos/tile-steel-tube.jpg') }}" width="1000" height="750" alt="Steel driveshaft tube stock in the shop"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>Where they go</h2><p>Anything that moves power between two fixed centers that are not quite in line.</p></div>
  <div class="why-grid">
    <div class="why-card"><h3>Pumps and compressors</h3><p>Engine or motor to pump with an offset or angle. Short couple and flange-to-flange shafts, often with a slip section for thermal growth.</p></div>
    <div class="why-card"><h3>Conveyors and mills</h3><p>Gearbox to head pulley or roll drive. Heavy series joints, long tube, balanced for continuous duty.</p></div>
    <div class="why-card"><h3>Generators and test stands</h3><p>Dyno and generator couplings where speed and balance matter most. We balance to the RPM you run.</p></div>
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="split">
    <div>
      <span class="kicker">What we need from you</span>
      <h2>The drawing, or the five numbers</h2>
      <p>Send a drawing if you have one. If not, the driveshaft designer lets you pick the end styles and fill in the dimensions, then print the sheet for us.</p>
      <ul class="checks"><li>Flange pattern on each end: bolt circle, pilot diameter, bolt count</li><li>Center-to-center or face-to-face length, compressed and extended if it slips</li><li>Operating speed and horsepower or torque</li><li>Operating angle and any offset</li><li>Environment: outdoor, washdown, high temperature</li></ul>
      <div class="phero__cta"><a href="/driveshaft-designer" class="btn btn-primary">Open the designer</a><a href="/contact" class="btn btn-ghost">Send a drawing</a></div>
    </div>
    <div><picture><source srcset="{{ asset('img/photos/band-fabrication.webp') }}" type="image/webp"><img src="{{ asset('img/photos/band-fabrication.jpg') }}" width="1800" height="1000" alt="Fabricating a driveshaft in the shop" loading="lazy"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="ctaband">
    <div><span class="kicker">Quote</span><h2>Send the specification, get a price and a lead time.</h2><p>Most industrial quotes go back the same business day.</p></div>
    <div class="phero__cta"><a href="/contact" class="btn btn-primary btn-lg">Request a quote <x-icon name="arrow-right"/></a><a href="mailto:inquiry@universaldriveshafts.com" class="btn btn-ghost btn-lg"><x-icon name="mail"/> inquiry@universaldriveshafts.com</a></div>
  </div>
</div></section>
@endif
@endsection
