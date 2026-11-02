@extends('layouts.app')
@section('title', __('About Universal Driveshafts, Florida | UDS'))
@section('desc', __('Universal Driveshafts is a driveshaft shop and parts supplier in Florida, founded in 2018. We build, repair and balance driveshafts and stock the parts.'))
@section('canonical','/about-us')

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
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@include('partials.breadcrumb-schema', ['crumbs' => [['Home', $base . '/'], ['About us', $base . '/about-us']]])
@endpush

@section('content')
@if(i18n_view('about'))
@include(i18n_view('about'))
@else
<section class="phero"><div class="wrap">
  <nav class="ucrumb" aria-label="Breadcrumb"><a href="/">Home</a><x-icon name="caret-right"/><span aria-current="page">About us</span></nav>
  <div class="accentline"></div>
  <h1>A driveshaft shop in Florida that also stocks the parts</h1>
  <p class="lead">Universal Driveshafts was founded in 2018 to do two things under one roof: build and repair driveshafts, and keep the u-joints, yokes, center support bearings and tubing to do it on the shelf. We supply OEMs, racing teams, fleets and people rebuilding one truck in their own garage.</p>
</div></section>

<section class="section"><div class="wrap">
  <div class="split">
    <div class="txt">
      <h2>What we actually do</h2>
      <p>We are a driveshaft shop first. Custom one- and two-piece driveshafts are built to measurement in steel or aluminum; worn shafts are rebuilt with new joints, bearings and tube; every shaft is straightened and balanced at speed before it leaves. Our technicians diagnose driveline problems rather than selling parts against a number, and if a different u-joint series or a different yoke is the right answer, we say so.</p>
      <p>We are also a parts supplier. The <a href="/shop">catalog</a> holds thousands of universal joints, slip yokes, weld yokes, flange yokes, end yokes, center support bearings and cut-to-length DOM tubing, indexed by series and dimension and shipped nationwide from the same building the shop works in. Machining, welding, assembly and balancing happen next to the inventory, which is why a rebuild can leave the same week instead of the same month.</p>
      <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.75rem">
        <a href="/services" class="btn btn-primary">What we offer <x-icon name="arrow-right"/></a>
      </div>
    </div>
    <div>
      <video controls playsinline preload="none" poster=""
             style="width:100%;max-height:520px;border-radius:var(--r-lg);border:1px solid var(--line);box-shadow:var(--sh-2);background:#000"
             aria-label="Inside the Universal Driveshafts shop">
        <source src="https://universaldriveshafts.com/wp-content/uploads/2025/01/ssstik.io_@uds247_1736520583506.mp4" type="video/mp4">
        <p class="vfallback">Your browser cannot play this video. <a href="https://universaldriveshafts.com/wp-content/uploads/2025/01/ssstik.io_@uds247_1736520583506.mp4">Download it instead</a>.</p>
      </video>
    </div>
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>Who we build for</h2><p>The same shaft gets the same process whether it is one of a hundred for a fleet or the only one for a Saturday project.</p></div>
  <div class="why-grid" style="margin-top:0">
    <div class="why-card"><h3>OEMs and manufacturers</h3><p>Production and replacement driveshafts for equipment builders, with repeatable specs and part numbers that reorder cleanly.</p></div>
    <div class="why-card"><h3>Fleets and work trucks</h3><p>Two-piece rebuilds, center support bearings and heavy-duty series for trucks that cannot sit. <a href="/heavy-duty-driveshafts">Heavy duty</a> and <a href="/industrial-driveshafts">industrial</a> shafts included.</p></div>
    <div class="why-card"><h3>Racers and performance</h3><p>Aluminum and large-series steel shafts, sealed solid-body joints and CV heads, specified for the RPM and angle they will actually see.</p></div>
    <div class="why-card"><h3>Owners and restorers</h3><p>One correct shaft for a lifted truck, a swapped car or an old Bronco, and the guides to measure it right the first time.</p></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="secthead"><h2>What customers say</h2></div>
  <div class="tgrid">
    <div class="tcard"><p>Amazing experience and great price, the drive shaft was flawless and runs smooth. They even cleaned up the few surface rust spots on the drive shaft and spray painted it. Hard to find good businesses like this anymore.</p><div class="twho"><b>Dean B.</b><span>Florida</span></div></div>
    <div class="tcard"><p>Best customer service around. They stand by their products and go above and beyond for their customers. Highly recommend for any drive shaft needs, custom or stock.</p><div class="twho"><b>Jay Palmer</b><span>Customer</span></div></div>
    <div class="tcard"><p>I called these guys up and told them what I needed. They said it would not be a problem. 24 to 48 hours later my driveshaft was done, balanced, shortened and painted. Only shop in the area that would touch my BMW driveshaft.</p><div class="twho"><b>Andrew Bishay</b><span>Customer</span></div></div>
    <div class="tcard"><p>This team is top notch. Did the impossible to make it possible, cannot thank them enough. Got the old Bronco rolling on 44s again.</p><div class="twho"><b>Phillip Walker</b><span>Customer</span></div></div>
  </div>
</div></section>

{{-- TODO: real facility photography wanted for this page. The previous images
     (obrero.png, fabricacion-de-automoviles.webp) were blank placeholder files. --}}
<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>How we work</h2><p>Four things we hold to on every job, whether it is a single u-joint or a fleet order.</p></div>
  <div class="why-grid" style="margin-top:0">
    <div class="why-card"><h3>Engineering before parts</h3><p>We diagnose the driveline problem first. If a different series or a different yoke is the right answer, we will say so.</p></div>
    <div class="why-card"><h3>Built for the duty cycle</h3><p>Race car or construction plant, the shaft is specified for the torque, angle and speed it will actually see.</p></div>
    <div class="why-card"><h3>Balanced, then shipped</h3><p>Every assembled shaft is spun and corrected in house. Vibration is a manufacturing problem, not your problem.</p></div>
    <div class="why-card"><h3>Reachable afterwards</h3><p>The number on the site reaches the shop. If something is not right, call it in and we will sort it.</p></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>Talk to the shop</h2><p>Based in Florida. We help customers in person and ship nationwide.</p></div>
  <div class="vs2">
    <div class="vs2__col">
      <span class="kicker">Florida</span>
      <h3>Universal Driveshafts</h3>
      <p style="margin:0;color:var(--ink-2);line-height:1.7">Parts and service: <a href="tel:+18135246491">(813) 524-6491</a><br>OEM and fleet: <a href="tel:+18136445707">(813) 644-5707</a><br>Monday to Friday 8:00 am to 5:00 pm, Saturday 9:00 am to 2:00 pm</p>
      <a href="/contact" class="btn btn-ghost">Contact the shop</a>
    </div>
    <div class="vs2__col">
      <span class="kicker">Nationwide</span>
      <h3>Ships anywhere in the U.S.</h3>
      <p style="margin:0;color:var(--ink-2);line-height:1.7">Parts and complete driveshafts ship by UPS, FedEx or freight. Local pickup is free; choose it at checkout.</p>
      <a href="/shipping-policy" class="btn btn-ghost">Shipping and pickup</a>
    </div>
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="ctaband">
    <h2>Bring it in, or send us the numbers</h2>
    <p>Visit our shop for measuring, balancing or a rebuild, or start a build online and see the price. Not sure what you have? The guides explain how to measure it and identify it.</p>
    <div class="cta">
      <a href="/online-builder" class="btn btn-primary">Open the online builder <x-icon name="arrow-right"/></a>
      <a href="/contact" class="btn btn-ghost">Contact the shop</a>
    </div>
  </div>
</div></section>
@endif
@endsection
