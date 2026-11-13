@extends('layouts.app')
@section('title', __('Industrial Driveshafts: Custom Built & Balanced | UDS'))
@section('desc', __('Industrial driveshafts for pumps, compressors, conveyors, mills, generators and test stands, built to your flange and length and balanced in Florida.'))
@section('bodyclass','pg')
@section('content')
@if(i18n_view('industrial'))
@include(i18n_view('industrial'))
@else
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><a href="/online-builder">Driveshafts</a><x-icon name="caret-right"/><span aria-current="page">Industrial</span></nav>
  <div class="pagehead__row">
    <h1>Industrial driveshafts built to your flange and length</h1>
    <div class="btn-row"><a href="/contact" class="btn btn-primary btn-sm">Request a quote <x-icon name="arrow-right"/></a><a href="/driveshaft-designer" class="btn btn-ghost btn-sm">Draw the shaft in the designer</a></div>
  </div>
  <p class="pagehead__sub">Pumps, compressors, conveyors, mills, generators and test stands. Companion, CV and Rzeppa flange patterns, 1310 to 1810 joint series by torque, welded, machined and balanced in house to the speed you run. Most industrial quotes go back the same business day.</p>
</div>

<section class="catsec"><div class="wrap">
  <div class="pg-split">
    <div>
      <div class="pg-prose"><h2>What we need from you: a drawing, or five numbers</h2></div>
      <ul class="pg-checks" style="margin-top:.6rem"><li>Flange pattern on each end: bolt circle, pilot diameter, bolt count</li><li>Center-to-center or face-to-face length, compressed and extended if it slips</li><li>Operating speed and horsepower or torque</li><li>Operating angle and any offset</li><li>Environment: outdoor, washdown, high temperature</li></ul>
      <p class="pg-note">Send a drawing if you have one. If not, the <a href="/driveshaft-designer">driveshaft designer</a> lets you pick the end styles, fill in the dimensions and print the sheet for us.</p>
      <ul class="pg-facts" style="margin-top:1rem">
        <li><b>Pumps and compressors</b><span>Engine or motor to pump with an offset or angle. Short couple and flange-to-flange shafts, often with a slip section for thermal growth.</span></li>
        <li><b>Conveyors and mills</b><span>Gearbox to head pulley or roll drive. Heavy series joints, long tube, balanced for continuous duty.</span></li>
        <li><b>Generators and test stands</b><span>Dyno and generator couplings where speed and balance matter most. We balance to the RPM you run.</span></li>
      </ul>
      <div class="pg-call" style="margin-top:1rem">
        <p><b>Send the specification</b> and get a price and a lead time.</p>
        <a class="pg-tel" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a>
        <a class="btn btn-ghost btn-sm" href="mailto:inquiry@universaldriveshafts.com"><x-icon name="mail"/> inquiry@universaldriveshafts.com</a>
      </div>
    </div>
    <div class="pg-split__media"><picture><source srcset="{{ asset('img/photos/tile-steel-tube.webp') }}" type="image/webp"><img src="{{ asset('img/photos/tile-steel-tube.jpg') }}" width="1000" height="750" alt="Steel driveshaft tube stock in the shop"></picture></div>
  </div>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  <div class="catsec__head"><h2>Industrial parts in stock</h2><a class="textlink" href="/shop">Full catalog <x-icon name="arrow-right"/></a></div>
  <ul class="hm-ways">
    <li><a href="/shop/driveshaft-components/companion-flanges"><x-icon name="sliders"/><b>Companion flanges</b><span>By bolt circle and pilot</span></a></li>
    <li><a href="/shop/driveshaft-components/flange-yokes"><x-icon name="package"/><b>Flange yokes</b><span>1310 to 1810 series</span></a></li>
    <li><a href="/shop/complete-driveshafts/short-couple-driveshafts"><x-icon name="cube"/><b>Short couple driveshafts</b><span>Flange to flange, in stock</span></a></li>
    <li><a href="/shop/driveshaft-tubing"><x-icon name="ruler"/><b>Driveshaft tubing</b><span>DOM steel, cut to length</span></a></li>
  </ul>
</div></section>
@endif
@endsection
