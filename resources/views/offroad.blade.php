@extends('layouts.app')
@section('title', __('Custom and off-road driveshafts | Universal Driveshafts'))
@section('desc', __('Custom off-road driveshafts for lifted and swapped builds: high-angle u-joints, 1310 to 1480 series, steel or aluminum tube, built to your specs in Florida.'))
@section('content')
@if(i18n_view('offroad'))
@include(i18n_view('offroad'))
@else
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><a href="/online-builder">Driveshafts</a><x-icon name="caret-right"/><span aria-current="page">Custom and off-road</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">Lifted, swapped, modified</span>
      <h1>Custom driveshafts for off-road and performance builds</h1>
      <p class="lead">When the lift changed the angle or the swap changed the length, a factory shaft no longer fits. We build the one that does: your series, your yokes, your measurements, balanced before it ships.</p>
      <div class="phero__cta">
        <a href="/online-builder" class="btn btn-primary btn-lg">Build it in the online builder <x-icon name="arrow-right"/></a>
        <a href="/driveshaft-designer" class="btn btn-ghost btn-lg">Draw it in the designer</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">1310 to 1480</b><span>u-joint series for light and medium duty</span></li>
        <li><b class="u-mono">2 to 5 days</b><span>typical build time</span></li>
        <li><b class="u-mono">6</b><span>measurements is all we need</span></li>
      </ul>
    </div>
    <div class="phero__media phero__media--photo"><picture><source srcset="{{ asset('img/photos/hero-overland.webp') }}" type="image/webp"><img src="{{ asset('img/photos/hero-overland.jpg') }}" width="2000" height="1000" alt="An overland truck running hard across desert terrain"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>What changes when the vehicle changes</h2><p>Three things decide whether a shaft lives or dies off-road: operating angle, length through travel, and the joint series. We size all three.</p></div>
  <div class="why-grid">
    <div class="why-card"><h3>Operating angle</h3><p>A lift raises the pinion angle. Past about 3 degrees of difference the u-joints cycle faster and wear out. We spec the joint series and, when needed, a double cardan (CV) head for high angles.</p></div>
    <div class="why-card"><h3>Length through travel</h3><p>Long-travel suspension means the shaft has to grow and shrink. We size the slip yoke and spline engagement from your compressed and extended measurements, not a catalog guess.</p></div>
    <div class="why-card"><h3>Joint series and tube</h3><p>1310 for stock-ish rigs, 1350 for big tires and torque, 1480 for the heavy stuff. Steel tube for strength, aluminum when weight and length matter.</p></div>
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="split">
    <div>
      <span class="kicker">How it works</span>
      <h2>Six measurements and your yoke style</h2>
      <p>The online builder walks the duty class, series, material and yokes, then asks for the measurements off the vehicle. The price updates as you go, and the build sheet goes to the shop with your order. Not sure what to measure? The driveshaft designer lets you draw the shaft and print the dimension sheet.</p>
      <ul class="checks"><li>One piece or two piece, with center support bearing</li><li>Slip yoke, flange, or CV head on either end</li><li>Balanced on our machine before it is boxed</li><li>Measurements and yoke choice saved with the order</li></ul>
      <div class="phero__cta"><a href="/online-builder" class="btn btn-primary">Start the build</a><a href="/driveshaft-designer" class="btn btn-ghost">Open the designer</a></div>
    </div>
    <div><picture><source srcset="{{ asset('img/photos/panel-custom.webp') }}" type="image/webp"><img src="{{ asset('img/photos/panel-custom.jpg') }}" width="849" height="566" alt="A two-piece and a one-piece driveshaft side by side on the bench" loading="lazy"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>Parts for the shaft you already have</h2><p>If the tube is fine and only the joints or yokes are tired, we stock the components by series.</p></div>
  <div class="why-grid">
    <a class="why-card" href="/shop/driveshaft-components/u-joints"><h3>Universal joints</h3><p>1310, 1330, 1350, 1410, 1480 and conversion joints for mixed-series swaps.</p></a>
    <a class="why-card" href="/shop/driveshaft-components/slip-yoke"><h3>Slip yokes</h3><p>Transmission and transfer case slip yokes by spline count and series.</p></a>
    <a class="why-card" href="/shop/driveshaft-components/cv-double-joints-heads"><h3>CV double cardan heads</h3><p>For high-angle front shafts on lifted 4x4s.</p></a>
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="ctaband">
    <div><span class="kicker">Talk to a driveline tech</span><h2>Not sure what you need? Call the shop.</h2><p>Tell us the vehicle, the lift and the tires, and we will spec the shaft with you. No call center.</p></div>
    <div class="phero__cta"><a href="tel:+18135246491" class="btn btn-primary btn-lg"><x-icon name="phone"/> (813) 524-6491</a><a href="/contact" class="btn btn-ghost btn-lg">Send photos and measurements</a></div>
  </div>
</div></section>
@endif
@endsection
