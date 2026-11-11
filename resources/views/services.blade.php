@extends('layouts.app')
@section('title', __('Driveshaft Repair & Balancing in Odessa, FL (Tampa Bay) | UDS'))
@section('desc', __('Driveshaft shop in Odessa, FL, serving Tampa Bay: repair and rebuild, balancing, custom driveshafts, u-joint and center bearing replacement. (813) 524-6491.'))
@section('canonical','/services')
@section('bodyclass','pg')

@php
  $base = rtrim(config('app.url'), '/');
  $services = [
    ['custom-driveshafts', 'wrench', 'Custom driveshafts', 'One- and two-piece driveshafts built to your measurements in steel or aluminum, any common u-joint series, balanced and painted before they ship.', '/online-builder', 'Price one in the builder',
      'From six measurements and a yoke choice we cut DOM or aluminum tube, press and weld the yokes in a fixture, fit new u-joints, straighten to under 0.010 inch runout and balance at speed. Lifted trucks, engine and transmission swaps, street and strip cars, off-road rigs, vans and industrial drives. Every series from 1310 to 1550 and the Chrysler and GM sizes.',
      ['One piece or two piece with center support bearing', 'Slip yoke, flange, end yoke or CV head at either end', 'Live price online before you order']],
    ['driveshaft-repair', 'returns', 'Repair and rebuild', 'Worn joints, a noisy bearing, a dented tube or a tired slip yoke: we rebuild the shaft you have and keep your factory yokes where they are good.', '/contact', 'Ask about a rebuild',
      'Ship the shaft or bring it in. We inspect runout, phase, yoke ears and splines first, then replace what is worn and balance the result. Re-tubing in new DOM tube is the standard fix for a dented or rusted tube and keeps your original yokes. A rebuild usually leaves the same week.',
      ['Inspection and honest repair-or-replace call', 'Re-tubing with your yokes', 'Returned balanced and painted']],
    ['high-speed-balancing', 'crosshair', 'High-speed balancing', 'Computer balancing on our own machine, at speed, with weights welded to the tube. Included in every shaft we build and available on its own.', '/contact', 'Book a balance',
      'A vibration that tracks road speed in every gear is usually balance, runout or a lost weight. We spin the shaft at operating speed on a two-plane balancer, correct it with welded weights and re-check. Shafts with runout are straightened first; shafts with worn joints get those replaced, because a loose joint cannot be balanced.',
      ['Two-plane balancing at speed', 'Runout check and straightening', 'Phase check on two-piece shafts']],
    ['shortening-lengthening', 'ruler', 'Shortening and lengthening', 'Transmission swap, lift, body swap or a different wheelbase: we cut the tube to the new length, or re-tube longer, then straighten and balance.', '/driveshaft-designer', 'How to measure',
      'Shortening re-uses your yokes in a new or trimmed tube at the exact length you need. Lengthening is a re-tube in new DOM or aluminum. Both are done in a fixture so the shaft stays straight and in phase, then balanced. Give us the joint-center or flange-face length and we handle the yoke math.',
      ['Any length within the tube critical-speed limit', 'Keep your yokes or upgrade them', 'Phase and runout held in a fixture']],
    ['u-joint-replacement', 'package', 'U-joint replacement', 'Pressed in on the bench, in the right series, with greaseable or sealed joints. We check the yoke ears so a new joint does not go into a worn yoke.', '/shop/driveshaft-components/u-joints', 'Shop u-joints',
      'We stock 179 universal joints across every series and press them in while you wait on most shafts. If the yoke ears are oval or peened the joint will not last; we tell you and quote a yoke. Conversion joints let a shaft mate to a yoke of a different series without changing it.',
      ['All series: 1310 to 1550, 3R/S44, 7260/7290', 'Greaseable or sealed, standard or HD', 'Ears inspected before any joint goes in']],
    ['center-support-bearing', 'gauge', 'Center support bearing replacement', 'Pressed on and off the stub shaft correctly, shaft re-phased, bracket height checked. The usual cure for a humming two-piece.', '/shop/driveshaft-components/center-support-bearing', 'Shop bearings',
      'We identify the bearing by stub diameter and bracket pattern from over 300 in stock, press it on driving the inner race only, inspect the stub spline and the two joints next to it, and reassemble the shaft in phase. Shimming guidance for lifted trucks is included.',
      ['324 bearings in stock by diameter and bracket', 'Stub shaft and spline inspected', 'Reassembled in phase']],
    ['flange-conversions', 'sliders', 'Flange and yoke conversions', 'Change a slip yoke shaft to a flange, mate a 1310 transmission to a 1350 axle, or fit a CV head for a lifted truck. Series and end-fitting changes done right.', '/contact', 'Yoke guide',
      'Conversions let a driveline from one vehicle live under another. We match flange patterns by bolt circle, hole count and pilot from 243 flange yokes in stock, select conversion joints where one end must change series, and fit double cardan heads where angle is the problem.',
      ['Flange yoke and companion flange matching', 'Series changes with conversion joints', 'CV head fitment for high angles']],
    ['measuring-consultation', 'headset', 'Measuring and consultation', 'Not sure what you have? Bring the shaft or the vehicle, or send photos and numbers. We measure, identify yokes and series, and tell you what to order.', '/driveshaft-designer', 'Open the designer',
      'Use the free driveshaft designer to draw and dimension the shaft and send it to us, call the shop with your measurements, or drop the vehicle off and we measure it on the lift. We read yokes and series off photos daily and will say when a premade shaft is the cheaper answer.',
      ['Free designer and measuring guides', 'Yoke and series identification from photos', 'Honest advice: premade when it fits']],
  ];
  $faqs = [
    ['How long does a driveshaft repair take?', 'Most joint and bearing replacements are done the same or next day. Re-tubing, shortening and balancing usually leave within the week. Custom new shafts take a few business days once the measurements are confirmed. Call (813) 524-6491 for the current schedule if you have a deadline.'],
    ['Can I ship my driveshaft to you?', 'Yes. Customers nationwide ship shafts to our shop for rebuilds, re-tubing and balancing. Mark the phase on two-piece shafts before disassembly, pack the shaft so the yokes cannot damage the tube, and include your contact details and what you want done. We call with a quote before work starts.'],
    ['Do you balance driveshafts you did not build?', 'Yes. We check runout, phase and joints first because a bent tube or a loose joint cannot be balanced, then balance at speed on the machine and weld the weights.'],
    ['Do you work on aluminum driveshafts?', 'Yes. We build and repair aluminum shafts with aluminum-specific weld yokes and tube. Aluminum shafts that are dented or corroded at the weld are usually re-tubed rather than repaired.'],
  ];
@endphp

@push('schema')
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org',
  '@@type' => 'ItemList',
  '@@id' => $base . '/services#list',
  'name' => 'Driveshaft services at Universal Driveshafts',
  'itemListElement' => collect($services)->values()->map(fn ($s, $i) => [
    '@@type' => 'ListItem', 'position' => $i + 1,
    'item' => [
      '@@type' => 'Service',
      '@@id' => $base . '/services#' . $s[0],
      'name' => $s[2],
      'serviceType' => $s[2],
      'description' => $s[3],
      'url' => $base . '/services#' . $s[0],
      'provider' => ['@@id' => $base . '/#organization'],
      'areaServed' => ['@@type' => 'Country', 'name' => 'United States'],
      'category' => 'Driveshaft service',
    ],
  ])->all(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@include('partials.breadcrumb-schema', ['crumbs' => [['Home', $base . '/'], ['Services', $base . '/services']]])
@endpush

@section('content')
@if(i18n_view('services'))
@include(i18n_view('services'))
@else
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><span aria-current="page">Services</span></nav>
  <div class="pagehead__row">
    <h1>Driveshaft services: custom builds, repair, balancing and conversions</h1>
    <div class="btn-row"><a href="/online-builder" class="btn btn-primary btn-sm"><x-icon name="wrench"/> Price a custom driveshaft</a><a href="tel:+18135246491" class="btn btn-ghost btn-sm"><x-icon name="phone"/> (813) 524-6491</a></div>
  </div>
  <p class="pagehead__sub">Built, repaired and balanced in our own shop in Odessa, FL (Tampa Bay), next to the parts inventory. Ship the shaft to us or bring it in; a rebuild usually leaves the same week.</p>
</div>

<section class="catsec"><div class="wrap">
  <ul class="pg-svcs">
    @foreach($services as $s)
    <li class="pg-svc" id="{{ $s[0] }}"><h3><x-icon :name="$s[1]"/>{{ $s[2] }}</h3><p>{{ $s[3] }}</p><a href="{{ $s[4] }}">{{ $s[5] }} <x-icon name="arrow-right"/></a></li>
    @endforeach
  </ul>
  <details class="morecopy">
    <summary>More about each service: what we check, what you get back</summary>
    <div class="morecopy__body">
      @foreach($services as $s)
      <h3>{{ $s[2] }}</h3>
      <p>{{ $s[6] }}</p>
      @endforeach
    </div>
  </details>
</div></section>

<section class="catsec"><div class="wrap">
  <div class="catsec__head"><h2>Bring it in or ship it to us</h2></div>
  <div class="pg-shop">
    <div class="pg-card">
      <h2>Universal Driveshafts, Odessa FL</h2>
      <p><a href="{{ config('uds.pickup.maps') }}" target="_blank" rel="noopener">11611 Prospect Drive STE 4, Odessa, FL 33556</a>, serving Tampa Bay.<br>Parts and service: <a class="pg-tel" href="tel:+18135246491">(813) 524-6491</a><br>OEM and fleet: <a class="pg-tel" href="tel:+18136445707">(813) 644-5707</a><br>Monday to Friday 8:00 am to 5:00 pm, Saturday 9:00 am to 2:00 pm. Local pickup Monday to Friday 3:00 to 5:00 pm.</p>
      <a href="/contact" class="btn btn-ghost btn-sm">Contact the shop</a>
    </div>
    <div class="pg-card">
      <h2>Shipping a shaft for a rebuild or balance</h2>
      <ul class="pg-checks"><li>Mark the phase on two-piece shafts before taking them apart</li><li>Pack so the yokes cannot dent the tube; cap the slip yoke</li><li>Include your name, phone and what you want done</li><li>We call with a quote before work starts</li><li>Free ground shipping on parts orders over ${{ number_format(config('uds.free_shipping_min')) }} (shafts and tubing excluded)</li></ul>
      <a href="/contact" class="btn btn-primary btn-sm">Contact the shop <x-icon name="arrow-right"/></a>
    </div>
  </div>
</div></section>

<section class="catsec catsec--end"><div class="wrap">
  <div class="catsec__head"><h2>Frequently asked questions</h2></div>
  <div class="pg-faq">@include('partials.faq', ['faqs' => $faqs])</div>
</div></section>
@endif
@endsection
