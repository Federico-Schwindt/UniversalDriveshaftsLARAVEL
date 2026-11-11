@extends('layouts.app')
@section('title', __('PTO and agricultural driveshafts | Universal Driveshafts'))
@section('desc', __('PTO driveshafts and agricultural driveline parts: tractor to implement shafts, PTO end yokes, u-joints and slip assemblies, built and stocked in Florida.'))
@section('bodyclass','pg')
@section('content')
@if(i18n_view('pto'))
@include(i18n_view('pto'))
@else
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><a href="/online-builder">Driveshafts</a><x-icon name="caret-right"/><span aria-current="page">PTO and agriculture</span></nav>
  <div class="pagehead__row">
    <h1>PTO and agricultural driveshafts</h1>
    <div class="btn-row"><a href="/shop/driveshaft-components/pto-end-yokes" class="btn btn-primary btn-sm"><x-icon name="package"/> Shop PTO end yokes</a><a href="/online-builder" class="btn btn-ghost btn-sm"><x-icon name="wrench"/> Build a complete shaft</a></div>
  </div>
  <p class="pagehead__sub">PTO end yokes, u-joints and slip assemblies in stock for the mower, the baler or the spreader, and complete shafts built to the tractor and the implement. Stock orders placed before 2pm EST ship the same day.</p>
</div>

<section class="catsec"><div class="wrap">
  <div class="catsec__head"><h2>Parts by what broke</h2><a class="textlink" href="/shop/driveshaft-components">All components <x-icon name="arrow-right"/></a></div>
  <ul class="hm-ways is-3">
    <li><a href="/shop/driveshaft-components/pto-end-yokes"><x-icon name="package"/><b>PTO end yokes</b><span>Quick-disconnect and push-pin: 1 3/8 in 6 spline, 1 3/8 in 21 spline, 1 3/4 in 20 spline</span></a></li>
    <li><a href="/shop/driveshaft-components/u-joints"><x-icon name="crosshair"/><b>Universal joints</b><span>Cross and bearing kits by series</span></a></li>
    <li><a href="/shop/driveshaft-components/splined-sleeves"><x-icon name="sliders"/><b>Slip assemblies and tubing</b><span>Splined sleeves and shafts so the implement can lift and turn</span></a></li>
  </ul>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  <div class="pg-split">
    <div>
      <div class="pg-prose"><h2>Complete shafts built to the tractor and the implement</h2><p>Tell us the tractor PTO size and the implement input, plus the length with the three-point hitch raised and lowered. We build the shaft with the right yokes on each end and the slip travel to match.</p></div>
      <ul class="pg-checks" style="margin-top:.75rem"><li>Yokes sized to tractor and implement splines</li><li>Slip travel from your raised and lowered lengths</li><li>Shear bolt or friction clutch yokes available</li><li>Balanced and shipped from Florida</li></ul>
      <div class="pg-call" style="margin-top:1rem">
        <p><b>Down in the field?</b> Give us the spline count and the length and we will tell you what is on the shelf right now.</p>
        <a class="pg-tel" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a>
        <a class="btn btn-ghost btn-sm" href="/contact">Message the shop</a>
      </div>
    </div>
    <div class="pg-split__media"><picture><source srcset="{{ asset('img/photos/tile-driveshaft-components.webp') }}" type="image/webp"><img src="{{ asset('img/photos/tile-driveshaft-components.jpg') }}" width="1000" height="750" alt="Driveshaft joints and yokes on the bench"></picture></div>
  </div>
</div></section>
@endif
@endsection
