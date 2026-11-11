@extends('layouts.app')
@section('title', __('About Universal Driveshafts, Florida | UDS'))
@section('desc', __('Universal Driveshafts is a driveshaft shop and parts supplier in Florida, founded in 2018. We build, repair and balance driveshafts and stock the parts.'))
@section('canonical','/about-us')
@section('bodyclass','pg')

@php $base = rtrim(config('app.url'), '/'); @endphp
@push('schema')
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org',
  '@@type' => 'AboutPage',
  '@@id' => $base . '/about-us',
  'url' => $base . '/about-us',
  'name' => 'About Universal Driveshafts',
  'description' => 'Driveshaft shop and parts supplier in Florida, founded in 2018.',
  'isPartOf' => ['@@id' => $base . '/#website'],
  'about' => ['@@id' => $base . '/#organization'],
  'mainEntity' => ['@@id' => $base . '/#organization'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@include('partials.breadcrumb-schema', ['crumbs' => [['Home', $base . '/'], ['About us', $base . '/about-us']]])
@endpush

@section('content')
@if(i18n_view('about'))
@include(i18n_view('about'))
@else
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><span aria-current="page">About us</span></nav>
  <div class="pagehead__row">
    <h1>A driveshaft shop in Florida that also stocks the parts</h1>
    <div class="btn-row"><a class="btn btn-primary btn-sm" href="/shop"><x-icon name="grid"/> Shop parts</a><a class="btn btn-ghost btn-sm" href="/online-builder"><x-icon name="wrench"/> Build a driveshaft</a></div>
  </div>
  <p class="pagehead__sub">Universal Driveshafts was founded in 2018 to build, repair and balance driveshafts, and to keep the u-joints, yokes, center support bearings and tubing to do it on the shelf. We supply OEMs, racing teams, fleets and people rebuilding one truck in their own garage.</p>
</div>

<section class="catsec"><div class="wrap">
  <div class="pg-split">
    <div class="pg-prose">
      <h2>What we do</h2>
      <p>Custom one- and two-piece driveshafts are built to measurement in steel or aluminum; worn shafts are rebuilt with new joints, bearings and tube; every shaft is straightened and balanced at speed before it leaves. If a different u-joint series or a different yoke is the right answer, we say so.</p>
      <p>The <a href="/shop">catalog</a> holds thousands of universal joints, slip, weld, flange and end yokes, center support bearings and cut-to-length DOM tubing, shipped nationwide from the same building the shop works in. That is why a rebuild can leave the same week.</p>
      <ul class="hm-ways is-2" style="margin-top:1.1rem">
        <li><a href="/services"><x-icon name="wrench"/><b>Shop services</b><span>Repair, balancing, shortening, conversions</span></a></li>
        <li><a href="/catalog/driveshafts"><x-icon name="car"/><b>Driveshafts by vehicle</b><span>Year, make and model</span></a></li>
        <li><a href="/heavy-duty-driveshafts"><x-icon name="truck"/><b>Heavy duty</b><span>Class 6 to 8 trucks, 1550 to 1810 series</span></a></li>
        <li><a href="/industrial-driveshafts"><x-icon name="factory"/><b>Industrial</b><span>Pumps, conveyors, generators, test stands</span></a></li>
      </ul>
    </div>
    <div class="pg-split__media">
      <video controls playsinline preload="none" poster="" aria-label="Inside the Universal Driveshafts shop">
        <source src="/uploads/wp/2025/01/ssstik.io_@uds247_1736520583506.mp4" type="video/mp4">
        <p class="vfallback">Your browser cannot play this video. <a href="/uploads/wp/2025/01/ssstik.io_@uds247_1736520583506.mp4">Download it instead</a>.</p>
      </video>
    </div>
  </div>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  <div class="catsec__head"><h2>Talk to the shop</h2></div>
  <div class="pg-shop">
    <div class="pg-card">
      <h2>Universal Driveshafts, Florida</h2>
      <p>Parts and service: <a class="pg-tel" href="tel:+18135246491">(813) 524-6491</a><br>OEM and fleet: <a class="pg-tel" href="tel:+18136445707">(813) 644-5707</a><br>Monday to Friday 8:00 am to 5:00 pm, Saturday 9:00 am to 2:00 pm</p>
      <a href="/contact" class="btn btn-ghost btn-sm">Contact the shop</a>
    </div>
    <div class="pg-card">
      <h2>Ships anywhere in the U.S.</h2>
      <p>Parts and complete driveshafts ship by UPS, FedEx or freight. Local pickup is free; choose it at checkout.</p>
      <a href="/shipping-policy" class="btn btn-ghost btn-sm">Shipping and pickup</a>
    </div>
  </div>
</div></section>
@endif
@endsection
