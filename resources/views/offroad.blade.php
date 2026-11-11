@extends('layouts.app')
@section('title', __('Custom and off-road driveshafts | Universal Driveshafts'))
@section('desc', __('Custom off-road driveshafts for lifted and swapped builds: high-angle u-joints, 1310 to 1480 series, steel or aluminum tube, built to your specs in Florida.'))
@section('bodyclass','pg')
@section('content')
@if(i18n_view('offroad'))
@include(i18n_view('offroad'))
@else
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><a href="/online-builder">Driveshafts</a><x-icon name="caret-right"/><span aria-current="page">Custom and off-road</span></nav>
  <div class="pagehead__row">
    <h1>Custom driveshafts for off-road and performance builds</h1>
    <div class="btn-row"><a href="/online-builder" class="btn btn-primary btn-sm"><x-icon name="wrench"/> Build it in the online builder</a><a href="/driveshaft-designer" class="btn btn-ghost btn-sm">Draw it in the designer</a></div>
  </div>
  <p class="pagehead__sub">When the lift changed the angle or the swap changed the length, a factory shaft no longer fits. We build the one that does from six measurements: your series, your yokes, balanced before it ships. Typical build time 2 to 5 days.</p>
</div>

<section class="catsec"><div class="wrap">
  <div class="pg-split">
    <div>
      <ul class="pg-facts">
        <li><b>Operating angle</b><span>A lift raises the pinion angle; past about 3 degrees of difference the u-joints wear fast. We spec the series and, when needed, a double cardan (CV) head.</span></li>
        <li><b>Length through travel</b><span>Slip yoke and spline engagement sized from your compressed and extended measurements, not a catalog guess.</span></li>
        <li><b>Series and tube</b><span>1310 for stock-ish rigs, 1350 for big tires and torque, 1480 for the heavy stuff. Steel for strength, aluminum when weight and length matter.</span></li>
        <li><b>Ends</b><span>One piece or two piece with center support bearing; slip yoke, flange or CV head on either end. Measurements and yoke choice are saved with the order.</span></li>
      </ul>
      <div class="pg-call" style="margin-top:1rem">
        <p><b>Not sure what you need?</b> Tell us the vehicle, the lift and the tires and we will spec the shaft with you.</p>
        <a class="pg-tel" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a>
        <a class="btn btn-ghost btn-sm" href="/contact">Send photos and measurements</a>
      </div>
    </div>
    <div class="pg-split__media"><picture><source srcset="{{ asset('img/photos/hero-overland.webp') }}" type="image/webp"><img src="{{ asset('img/photos/hero-overland.jpg') }}" width="2000" height="1000" alt="An overland truck running hard across desert terrain"></picture></div>
  </div>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  <div class="catsec__head"><h2>Parts for the shaft you already have</h2><a class="textlink" href="/shop/driveshaft-components">All components <x-icon name="arrow-right"/></a></div>
  <ul class="hm-ways is-3">
    <li><a href="/shop/driveshaft-components/u-joints"><x-icon name="package"/><b>Universal joints</b><span>1310, 1330, 1350, 1410, 1480 and conversion joints</span></a></li>
    <li><a href="/shop/driveshaft-components/slip-yoke"><x-icon name="sliders"/><b>Slip yokes</b><span>By spline count and series</span></a></li>
    <li><a href="/shop/driveshaft-components/cv-double-joints-heads"><x-icon name="crosshair"/><b>CV double cardan heads</b><span>High-angle front shafts on lifted 4x4s</span></a></li>
  </ul>
  <details class="morecopy">
    <summary>More about sizing an off-road driveshaft</summary>
    <div class="morecopy__body">
      <p>Three things decide whether a shaft lives or dies off-road: operating angle, length through travel, and the joint series. A lift raises the pinion angle. Past about 3 degrees of difference the u-joints cycle faster and wear out, so we spec the joint series and, when needed, a double cardan (CV) head for high angles. Long-travel suspension means the shaft has to grow and shrink; we size the slip yoke and spline engagement from your compressed and extended measurements.</p>
      <p>The <a href="/online-builder">online builder</a> walks the duty class, series, material and yokes, then asks for the measurements off the vehicle. The price updates as you go, and the build sheet goes to the shop with your order. Not sure what to measure? The <a href="/driveshaft-designer">driveshaft designer</a> lets you draw the shaft and print the dimension sheet. Every shaft is balanced on our machine before it is boxed.</p>
    </div>
  </details>
</div></section>
@endif
@endsection
