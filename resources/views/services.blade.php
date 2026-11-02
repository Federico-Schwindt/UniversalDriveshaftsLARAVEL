@extends('layouts.app')
@section('title', __('Driveshaft Repair, Balancing & Custom Builds | UDS'))
@section('desc', __('Driveshaft services in Florida: custom driveshafts built to measure, repair and rebuild, u-joint and center bearing replacement, shortening, balancing.'))
@section('canonical','/services')

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
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@include('partials.breadcrumb-schema', ['crumbs' => [['Home', $base . '/'], ['Services', $base . '/services']]])
@endpush

@section('content')
@if(i18n_view('services'))
@include(i18n_view('services'))
@else
<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><span aria-current="page">Services</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">Driveshaft shop, Florida</span>
      <h1>Driveshaft services: custom builds, repair, balancing and conversions</h1>
      <p class="lead">Universal Driveshafts builds, repairs and balances driveshafts in our own shop in Florida, next to the parts inventory. Machining, welding, assembly and balancing happen in house, which is why a rebuild leaves the same week and a custom shaft is priced before you order.</p>
      <div class="phero__cta">
        <a href="/online-builder" class="btn btn-primary btn-lg"><x-icon name="wrench"/> Price a custom driveshaft</a>
        <a href="tel:+18135246491" class="btn btn-ghost btn-lg"><x-icon name="phone"/> (813) 524-6491</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">8</b><span>in-house services</span></li>
        <li><b class="u-mono">{{ number_format($udsCatalog['parts'] ?? 2600) }}</b><span>parts on the shelf next to the shop</span></li>
        <li><b class="u-mono">2018</b><span>serving OEMs, fleets and owners since</span></li>
      </ul>
    </div>
    <div class="phero__media phero__media--photo"><picture><source srcset="{{ asset('img/photos/band-fabrication.webp') }}" type="image/webp"><img src="{{ asset('img/photos/band-fabrication.jpg') }}" alt="Driveshaft fabrication at the Universal Driveshafts shop in Florida" width="1200" height="900" loading="eager" fetchpriority="high"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>What we do, in house</h2><p>Eight services, one shop. Every shaft that leaves is straightened and balanced, whether we built it or rebuilt it.</p></div>
  <div class="svcs">
    @foreach($services as $s)
    <div class="svc" id="{{ $s[0] }}"><div class="ic"><x-icon :name="$s[1]"/></div><h3>{{ $s[2] }}</h3><p>{{ $s[3] }}</p><a href="{{ $s[4] }}" class="go">{{ $s[5] }} <x-icon name="arrow-right"/></a></div>
    @endforeach
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>Each service in detail</h2><p>What happens to your shaft, what we check before we start, and what you get back.</p></div>
  <div class="vs2 svc-detail">
    @foreach($services as $s)
    <div class="vs2__col">
      <span class="kicker u-mono">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
      <h3>{{ $s[2] }}</h3>
      <p style="margin:0;color:var(--ink-2);line-height:1.6">{{ $s[6] }}</p>
      <ul class="checks">@foreach($s[7] as $c)<li>{{ $c }}</li>@endforeach</ul>
    </div>
    @endforeach
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>How a shop job works</h2><p>Whether you ship it, bring it or build it online, the shaft goes through the same three stages.</p></div>
  <ol class="steps3">
    <li><span class="steps3__n u-mono">1</span><b>Measure and identify</b><p>Use the measuring guide or <a href="/driveshaft-designer">designer</a>, or let us measure the shaft or the vehicle at the shop. We confirm the series, yokes and length before any cutting.</p></li>
    <li><span class="steps3__n u-mono">2</span><b>Build or rebuild</b><p>Tube cut and yokes welded in a fixture, or worn joints, bearings and yokes replaced. Straightened to under 0.010 inch runout.</p></li>
    <li><span class="steps3__n u-mono">3</span><b>Balance, paint, ship</b><p>Balanced at speed with welded weights, painted, and shipped nationwide from Florida or ready for pickup Monday to Friday.</p></li>
  </ol>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>Call the shop or ship the shaft to us</h2><p>Free local pickup (choose it at checkout); rebuilds and balancing by freight from anywhere in the U.S.</p></div>
  <div class="vs2">
    <div class="vs2__col">
      <span class="kicker">Talk to the shop</span>
      <h3>Universal Driveshafts, Florida</h3>
      <p style="margin:0;color:var(--ink-2);line-height:1.7">Parts and service: <a href="tel:+18135246491">(813) 524-6491</a><br>OEM and fleet: <a href="tel:+18136445707">(813) 644-5707</a><br>Monday to Friday 8:00 am to 5:00 pm, Saturday 9:00 am to 2:00 pm<br>Local pickup Monday to Friday 3:00 to 5:00 pm</p>
      <a href="/contact" class="btn btn-ghost">Contact the shop</a>
    </div>
    <div class="vs2__col">
      <span class="kicker">Ship it to us</span>
      <h3>Nationwide rebuilds and balancing</h3>
      <ul class="checks"><li>Mark the phase on two-piece shafts before taking them apart</li><li>Pack so the yokes cannot dent the tube; cap the slip yoke</li><li>Include your name, phone and what you want done</li><li>We call with a quote before work starts</li><li>Free ground shipping on parts orders over ${{ number_format(config('uds.free_shipping_min')) }} (shafts and tubing excluded)</li></ul>
      <a href="/contact" class="btn btn-primary">Contact the shop <x-icon name="arrow-right"/></a>
    </div>
  </div>
</div></section>

<section class="section"><div class="wrap wrap--narrow">
  <div class="s-head"><h2>Frequently asked questions</h2></div>
  @include('partials.faq', ['faqs' => $faqs])
</div></section>

<section class="section section--tint"><div class="wrap"><div class="ctaband">
  <h2>Bring it in or ship it to us</h2>
  <p>Send us the shaft for measuring, balancing or a rebuild, or spec a new one online and see the price before you commit. Florida, (813) 524-6491.</p>
  <div class="cta"><a href="/online-builder" class="btn btn-primary">Open the online builder <x-icon name="arrow-right"/></a><a href="/contact" class="btn btn-ghost">Talk to a tech</a></div>
</div></div></section>
@endif
@endsection
