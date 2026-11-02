@extends('layouts.app')
@section('title', __('PTO and agricultural driveshafts | Universal Driveshafts'))
@section('desc', __('PTO driveshafts and agricultural driveline parts: tractor to implement shafts, PTO end yokes, u-joints and slip assemblies, built and stocked in Florida.'))
@section('content')
@if(i18n_view('pto'))
@include(i18n_view('pto'))
@else
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><a href="/online-builder">Driveshafts</a><x-icon name="caret-right"/><span aria-current="page">PTO and agriculture</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">Tractor and implement</span>
      <h1>PTO and agricultural driveshafts, in season and in stock</h1>
      <p class="lead">A broken PTO shaft stops the mower, the baler or the spreader on the day you need it most. We stock PTO end yokes, joints and slip assemblies, and build complete shafts to the implement and the tractor.</p>
      <div class="phero__cta">
        <a href="/shop/driveshaft-components/pto-end-yokes" class="btn btn-primary btn-lg">Shop PTO end yokes <x-icon name="arrow-right"/></a>
        <a href="/online-builder" class="btn btn-ghost btn-lg">Build a complete shaft</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">1 3/8 in</b><span>6 and 21 spline PTO yokes</span></li>
        <li><b class="u-mono">1 3/4 in</b><span>20 spline for larger tractors</span></li>
        <li><b class="u-mono">Same day</b><span>dispatch on stock before 2pm ET</span></li>
      </ul>
    </div>
    <div class="phero__media phero__media--photo"><picture><source srcset="{{ asset('img/photos/tile-driveshaft-components.webp') }}" type="image/webp"><img src="{{ asset('img/photos/tile-driveshaft-components.jpg') }}" width="1000" height="750" alt="Driveshaft joints and yokes on the bench"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>Parts by what broke</h2><p>Most PTO failures are one of three things. We stock all three.</p></div>
  <div class="why-grid">
    <a class="why-card" href="/shop/driveshaft-components/pto-end-yokes"><h3>PTO end yokes</h3><p>Quick-disconnect and push-pin yokes in 1 3/8 in 6 spline, 1 3/8 in 21 spline and 1 3/4 in 20 spline.</p></a>
    <a class="why-card" href="/shop/driveshaft-components/u-joints"><h3>Universal joints</h3><p>Cross and bearing kits by series. Grease them, replace them, keep a spare in the tractor.</p></a>
    <a class="why-card" href="/shop/driveshaft-components/splined-sleeves"><h3>Slip assemblies and tubing</h3><p>Splined sleeves and shafts so the implement can lift and turn without binding.</p></a>
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="split">
    <div>
      <span class="kicker">Complete shafts</span>
      <h2>Built to the tractor and the implement</h2>
      <p>Tell us the tractor PTO size and the implement input, plus the length with the three-point hitch raised and lowered. We build the shaft with the right yokes on each end and the slip travel to match.</p>
      <ul class="checks"><li>Yokes sized to tractor and implement splines</li><li>Slip travel from your raised and lowered lengths</li><li>Shear bolt or friction clutch yokes available</li><li>Balanced and shipped from Florida</li></ul>
      <div class="phero__cta"><a href="/online-builder" class="btn btn-primary">Build it online</a><a href="tel:+18135246491" class="btn btn-ghost"><x-icon name="phone"/> (813) 524-6491</a></div>
    </div>
    <div><picture><source srcset="{{ asset('img/photos/panel-premade.webp') }}" type="image/webp"><img src="{{ asset('img/photos/panel-premade.jpg') }}" width="1200" height="800" alt="A finished driveshaft on the bench, ready to ship" loading="lazy"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="ctaband">
    <div><span class="kicker">In a hurry</span><h2>Down in the field? Call the shop.</h2><p>Give us the spline count and the length and we will tell you what is on the shelf right now.</p></div>
    <div class="phero__cta"><a href="tel:+18135246491" class="btn btn-primary btn-lg"><x-icon name="phone"/> (813) 524-6491</a><a href="/contact" class="btn btn-ghost btn-lg">Message the shop</a></div>
  </div>
</div></section>
@endif
@endsection
