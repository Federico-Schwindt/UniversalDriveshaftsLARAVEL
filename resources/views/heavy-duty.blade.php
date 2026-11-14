@extends('layouts.app')
@section('title', __('Heavy duty driveshafts | Universal Driveshafts'))
@section('desc', __('Heavy duty driveshafts for class 6 to 8 trucks and vocational equipment. 1550 to 1810 series and SPL, balanced in Florida. Certified take-offs in stock.'))
@section('bodyclass','pg')
@section('content')
@if(i18n_view('heavy-duty'))
@include(i18n_view('heavy-duty'))
@else
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><a href="/online-builder">Driveshafts</a><x-icon name="caret-right"/><span aria-current="page">Heavy duty</span></nav>
  <div class="pagehead__row">
    <h1>Heavy duty driveshafts for class 6 to 8 trucks</h1>
    <div class="btn-row"><a href="/online-builder" class="btn btn-primary btn-sm"><x-icon name="wrench"/> Build a heavy duty shaft</a><a href="/shop/complete-driveshafts/certifed-driveshafts" class="btn btn-ghost btn-sm">Certified take-offs in stock</a></div>
  </div>
  <p class="pagehead__sub">Dump, refuse, mixer, tractor: 1550 to 1810 and SPL 170 / 250 series, built and balanced in house. Twelve series in the heavy duty builder; most stock orders ship the same day when placed before 2 pm EST.</p>
</div>

<section class="catsec"><div class="wrap">
  <div class="pg-split">
    <div>
      <ul class="pg-facts">
        <li><b>Series matched to torque</b><span>1610 and 1710 for most class 7 and 8 applications, 1810 and SPL 250 where the torque and the weight demand it. We confirm the series from the yokes you already run.</span></li>
        <li><b>Tube sized for the length</b><span>Longer shafts need larger, thicker tube to stay above critical speed. The builder checks tube OD against your length and material before it prices.</span></li>
        <li><b>Balanced in house</b><span>Every shaft, new or certified take-off, is balanced on our machine before it goes on the shelf or into a box.</span></li>
        <li><b>Certified take-offs</b><span>Shafts from low-mileage trucks, inspected joint by joint, rebalanced and tagged with series and length. SPL 170, SPL 250, 1710 and 1810. They cost less than new and ship the day you order; most can be shortened or re-yoked. <a href="/shop/complete-driveshafts/certifed-driveshafts">Shop certified driveshafts</a></span></li>
      </ul>
      <div class="pg-call" style="margin-top:1rem">
        <p><b>Running more than one truck?</b> Wholesale pricing, PO terms and dropship to your yards.</p>
        <a class="pg-tel" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a>
        <a class="btn btn-ghost btn-sm" href="/wholesale">Apply for wholesale</a>
      </div>
    </div>
    <div class="pg-split__media"><picture><source srcset="{{ asset('img/photos/hero-weld.webp') }}" type="image/webp"><img src="{{ asset('img/photos/hero-weld.jpg') }}" width="2000" height="1000" alt="Welding a heavy driveshaft tube in our shop"></picture></div>
  </div>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  <div class="catsec__head"><h2>Heavy duty parts in stock</h2><a class="textlink" href="/shop">Full catalog <x-icon name="arrow-right"/></a></div>
  <ul class="hm-ways">
    <li><a href="/shop/driveshaft-components/u-joints"><x-icon name="package"/><b>Universal joints</b><span>1550 to 1810 and SPL series</span></a></li>
    <li><a href="/shop/driveshaft-components/center-support-bearing"><x-icon name="gauge"/><b>Center support bearings</b><span>By stub diameter and bracket</span></a></li>
    <li><a href="/shop/driveshaft-components/flange-yokes"><x-icon name="sliders"/><b>Flange yokes</b><span>Matched by bolt circle and pilot</span></a></li>
    <li><a href="/shop/driveshaft-tubing"><x-icon name="ruler"/><b>Driveshaft tubing</b><span>DOM steel, cut to length</span></a></li>
  </ul>
</div></section>
@endif
@endsection
