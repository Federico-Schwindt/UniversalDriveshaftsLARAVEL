@extends('layouts.app')
@section('title', __('Heavy duty driveshafts | Universal Driveshafts'))
@section('desc', __('Heavy duty driveshafts for class 6 to 8 trucks and vocational equipment. 1550 to 1810 series and SPL, balanced in Florida. Certified take-offs in stock.'))
@section('content')
@if(i18n_view('heavy-duty'))
@include(i18n_view('heavy-duty'))
@else
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><a href="/online-builder">Driveshafts</a><x-icon name="caret-right"/><span aria-current="page">Heavy duty</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">Class 6 to 8, vocational, commercial</span>
      <h1>Heavy duty driveshafts that keep the truck working</h1>
      <p class="lead">Dump, refuse, mixer, tractor: when a shaft fails the truck is parked and the day is lost. We build and balance heavy duty shafts in house, stock certified take-offs for the common series, and ship the same day on stock.</p>
      <div class="phero__cta">
        <a href="/online-builder" class="btn btn-primary btn-lg">Build a heavy duty shaft <x-icon name="arrow-right"/></a>
        <a href="/shop/complete-driveshafts/certifed-driveshafts" class="btn btn-ghost btn-lg">Certified take-offs in stock</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">1550 to 1810</b><span>and SPL 170 / 250 series</span></li>
        <li><b class="u-mono">12</b><span>series in the heavy duty builder</span></li>
        <li><b class="u-mono">Same day</b><span>dispatch on stock before 2pm ET</span></li>
      </ul>
    </div>
    <div class="phero__media phero__media--photo"><picture><source srcset="{{ asset('img/photos/hero-weld.webp') }}" type="image/webp"><img src="{{ asset('img/photos/hero-weld.jpg') }}" width="2000" height="1000" alt="Welding a heavy driveshaft tube in our shop"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>Built for the duty cycle, not the spec sheet</h2><p>Heavy duty is about torque, hours and abuse. Every shaft we build for a truck is sized for all three.</p></div>
  <div class="why-grid">
    <div class="why-card"><h3>Series matched to torque</h3><p>1610 and 1710 for most class 7 and 8 applications, 1810 and SPL 250 where the torque and the weight demand it. We confirm the series from the yokes you already run.</p></div>
    <div class="why-card"><h3>Tube sized for the length</h3><p>Longer shafts need larger, thicker tube to stay above critical speed. The builder checks tube OD against your length and material before it prices.</p></div>
    <div class="why-card"><h3>Balanced in house</h3><p>Every shaft, new or certified take-off, is balanced on our machine before it goes on the shelf or into a box.</p></div>
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="split">
    <div>
      <span class="kicker">Certified take-offs</span>
      <h2>Inspected, balanced, ready today</h2>
      <p>Take-off shafts from low-mileage trucks, inspected joint by joint, rebalanced and tagged with the series and length. They cost less than new and leave the shop the day you order. Most can be shortened or re-yoked to your measurements.</p>
      <ul class="checks"><li>SPL 170, SPL 250, 1710 and 1810 series</li><li>Inspected and rebalanced before stocking</li><li>Modification to your measurements available</li></ul>
      <div class="phero__cta"><a href="/shop/complete-driveshafts/certifed-driveshafts" class="btn btn-primary">Shop certified driveshafts</a></div>
    </div>
    <div><picture><source srcset="{{ asset('img/photos/tile-complete-driveshafts.webp') }}" type="image/webp"><img src="{{ asset('img/photos/tile-complete-driveshafts.webp') }}" width="1000" height="750" alt="Heavy duty driveshafts on the bench" loading="lazy"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="ctaband">
    <div><span class="kicker">Fleet and shop accounts</span><h2>Running more than one truck?</h2><p>Wholesale pricing, PO terms and dropship to your yards. Talk to the shop about an account.</p></div>
    <div class="phero__cta"><a href="/wholesale" class="btn btn-primary btn-lg">Apply for wholesale <x-icon name="arrow-right"/></a><a href="tel:+18135246491" class="btn btn-ghost btn-lg"><x-icon name="phone"/> (813) 524-6491</a></div>
  </div>
</div></section>
@endif
@endsection
