@extends('guides.layout')
@php
$faqs = [
  ['What is the difference between a slip yoke and an end yoke?', 'A slip yoke has an internal spline and slides on the transmission or transfer case output shaft, so it carries the u-joint and provides the length change. An end yoke has a splined bore and a nut, and is bolted permanently onto a pinion, transfer case or transmission output; it carries a u-joint but does not slide.'],
  ['How do I identify a flange yoke?', 'Four numbers: the bolt circle diameter (center to center across opposite holes), the number and size of holes, the pilot diameter and whether the pilot is male (raised ring) or female (recess). Then the u-joint series, from the cap size. Our flange yoke listings carry all five.'],
  ['Can I weld a yoke to the tube myself?', 'Weld yokes are designed to be welded, but the shaft must then be straightened to under 0.010 inch runout and balanced at speed. A shaft welded without a fixture almost always needs both. We sell weld yokes for people who have the equipment; for everyone else the online builder prices the finished assembly.'],
  ['What slip yoke spline does my transmission have?', 'Count the splines on the output shaft and caliper the major diameter. Common light-truck sizes are 1.250 x 16 (TH350, 4L60, many Fords), 1.375 x 16 (NP205, 4L80, Dodge), 1.500 x 16 (Ford truck, NV4500), 27 spline (TH350/700R4 1.125 dia), 32 spline (4L80E, Ford 1.375 dia). Our slip yoke names list the spline and the centerline-to-end length.'],
];
@endphp
@section('article')
@if(i18n_view('guides.slip-yoke-vs-weld-yoke-vs-flange-yoke'))
@include(i18n_view('guides.slip-yoke-vs-weld-yoke-vs-flange-yoke'))
@else
<p>Strip a driveshaft down and it is a tube with a yoke welded to each end, and a u-joint in each yoke connecting it to the vehicle. The yoke types differ in <strong>how they attach to the vehicle</strong>: a <em>slip yoke</em> slides on a spline, a <em>flange yoke</em> bolts to a flange, an <em>end yoke</em> is nutted onto a pinion or output shaft, and a <em>weld yoke</em> is the half of the pair that lives on the tube. The size of every one of them is set by the <a href="/guides/u-joint-series-identification-chart">u-joint series</a>. Get the type right and the series right and the shaft bolts up.</p>

<h2 id="map">Where each yoke lives</h2>
<p>Follow a typical rear-wheel-drive truck from the transmission back:</p>
<ol class="guide-steps">
<li><strong>Transmission output shaft</strong>: externally splined. A <strong>slip yoke</strong> slides over it and carries the front u-joint. On some vehicles the transmission instead has an <strong>end yoke</strong> or <strong>flange</strong> bolted to it and the slip is inside the shaft.</li>
<li><strong>Front of the tube</strong>: a <strong>weld yoke</strong> (tube yoke) welded in, carrying the other half of the front u-joint. On a flange-ended shaft the weld yoke mates to a <strong>flange yoke</strong> instead of a slip yoke.</li>
<li><strong>Rear of the tube</strong>: another weld yoke, or a <strong>slip stub</strong> on a two-piece.</li>
<li><strong>Pinion</strong>: a <strong>pinion end yoke</strong> (strap, u-bolt or bolted-cap style) or a <strong>companion flange</strong> that mates to a flange yoke on the shaft.</li>
</ol>
<p>Two-piece shafts add a <strong>stub (midship) shaft</strong> carrying the center bearing and a <strong>slip yoke or splined sleeve</strong> on the rear shaft that rides on it. Industrial and PTO shafts replace yokes with keyed hubs, splined sleeves or quick-disconnect PTO yokes, but the logic is identical.</p>

<h2 id="slip">Slip yokes</h2>
<p>A slip yoke is a yoke with a long internally splined barrel. It provides the <strong>length change</strong> the driveline needs as the suspension moves and lets the shaft be removed by sliding it off. Specifying one takes four values:</p>
<ul>
<li><strong>Spline count and major diameter</strong>: 1.250 x 16, 1.375 x 16, 1.500 x 16, 1.125 x 27, 1.375 x 32 and so on. This must match the output shaft exactly.</li>
<li><strong>U-joint series</strong>: 1310, 1330, 1350, 1410, 3R, 7290... The ears are machined for one series.</li>
<li><strong>Centerline to end</strong> (CL to end): distance from the u-joint cross center to the end of the barrel. Longer barrels give more slip travel and reach outputs that sit deep in a tailhousing. Common values are 5.000, 5.500, 6.000 and 7.375 inches; a <a href="/parts/2-3-1421KX">2-3-1421KX</a> is a 1310 series 1.250 x 16 at 5 inches CL to end.</li>
<li><strong>Seal and plug</strong>: most have a welch plug in the ear end to keep transmission fluid in; some are open-ended for sealed outputs. Dust caps and boots are small parts.</li>
</ul>
<p>Slip yokes wear two ways: the spline gets rotational play, and the barrel outer surface wears where the tailhousing seal rides, causing a leak. Either means a new yoke. Browse <a href="/shop/driveshaft-components/slip-yoke">slip yokes</a> by spline and series.</p>

<h2 id="weld">Weld yokes</h2>
<p>The weld yoke (tube yoke) is the part of the shaft itself. It has a machined <strong>butt diameter</strong> that fits inside the tube and a shoulder the tube butts against, which is then welded all around. It is specified by:</p>
<ul>
<li><strong>Tube OD and wall</strong>: a yoke is made for one tube size, for example 2.000 x 0.120 or 3.000 x 0.083. The butt diameter equals the tube inside diameter (a 2.000 x 0.120 tube has a 1.760 ID, so <a href="/parts/2-28-1757">2-28-1757</a> lists a 1.766 butt).</li>
<li><strong>U-joint series</strong>, as always.</li>
<li><strong>Centerline to weld</strong>: cross center to the weld shoulder. This is the number a shop subtracts from your overall measurement when it cuts the tube. The <a href="/parts/2-28-417U">2-28-417</a> lists 1.688 CL to weld.</li>
</ul>
<p>Weld yokes are the cheapest part of a shaft and the one that fails least; they are replaced when the ears are worn oval, when converting a shaft to another series, or when re-tubing. Listings are under <a href="/shop/driveshaft-components/weld-yoke">weld yokes</a>, filtered by tube size. If you are matching a tube, the <a href="/guides/choosing-driveshaft-tubing">tubing guide</a> explains which OD and wall pair with which yoke.</p>

<h2 id="flange">Flange yokes and companion flanges</h2>
<p>A flange yoke carries a u-joint on one side and a flat, bolted flange on the other. The vehicle side is a <strong>companion flange</strong> (on a pinion, transmission, transfer case or industrial gearbox). The two are a matched pair and are specified by the <strong>flange pattern</strong>:</p>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Dimension</th><th>How to measure</th><th>Example</th></tr></thead>
<tbody>
<tr><td>Bolt circle diameter (BCD)</td><td>Center to center across two opposite holes; for 3- or 6-hole patterns use the circle through the hole centers</td><td>3.750 (Ford, Toyota 1350 pattern), 3.500 (Ford 8.8 small pattern)</td></tr>
<tr><td>Hole count and size</td><td>Count; caliper hole diameter; note threaded vs clearance</td><td>4 x 0.438, 4 x 10 mm, 6 x 12 mm</td></tr>
<tr><td>Pilot diameter</td><td>Caliper the raised ring (male) or the recess (female)</td><td>2.750 male (70 mm), 57 mm male (Nissan/Subaru)</td></tr>
<tr><td>Face to joint center</td><td>Flange face to cross center</td><td>Sets how much shaft length the yoke uses</td></tr>
<tr><td>Series</td><td>Cap diameter of the joint</td><td>1310, 1350, 1410</td></tr>
</tbody></table></div>
<p>The <a href="/parts/2-2-479">2-2-479</a> reads exactly this way: 1310 series, 4 x 0.438 holes on a 3.750 BCD, 2.750 male pilot. Flange shafts dominate imports, late-model trucks and industrial drives because the flange carries high torque and lets the shaft be removed without disturbing the pinion. They have no slip travel of their own, so a flange-to-flange shaft either has a splined slip assembly in the tube or is used where length does not change. The <a href="/shop/driveshaft-components/flange-yokes">flange yoke category</a> is our largest, 243 patterns and counting, alongside <a href="/shop/driveshaft-components/companion-flanges">companion flanges</a>. CV (double cardan) flange yokes for high-angle front shafts are listed separately.</p>

<h2 id="end">End yokes and transmission yokes</h2>
<p>An end yoke has a splined bore and is nutted onto a shaft: the pinion of the axle (pinion yoke), the output of a transfer case, or the output of a transmission that does not use a slip yoke. It carries the fixed half of the u-joint. Specification is spline count and diameter, u-joint series, and the retention style: <strong>strap</strong> (two small straps and four bolts), <strong>u-bolt</strong> (two u-bolts with nuts), or <strong>bolted caps</strong> (caps with lugs that bolt to the yoke, common on medium duty). The strap or u-bolt kit is a separate small part and is worth replacing with the joint. End yokes are filed under <a href="/shop/driveshaft-components/end-yokes">end yokes</a> and <a href="/shop/driveshaft-components/transmission-yokes">transmission yokes</a>.</p>

<h2 id="table">Specification table</h2>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Yoke</th><th>Lives on</th><th>Specified by</th><th>Provides</th></tr></thead>
<tbody>
<tr><td>Slip yoke</td><td>Transmission / T-case output, or rear shaft of a two-piece</td><td>Spline, series, CL to end</td><td>Length change, u-joint</td></tr>
<tr><td>Weld yoke</td><td>Each end of the tube</td><td>Tube OD x wall, series, CL to weld</td><td>Tube-to-joint connection</td></tr>
<tr><td>Flange yoke</td><td>Shaft end, bolts to companion flange</td><td>BCD, holes, pilot, series</td><td>Bolted connection, high torque</td></tr>
<tr><td>Companion flange</td><td>Pinion / gearbox / T-case</td><td>Spline, BCD, holes, pilot</td><td>Mating face for a flange yoke</td></tr>
<tr><td>End yoke</td><td>Pinion, T-case, transmission</td><td>Spline, series, strap / u-bolt / bolted cap</td><td>Fixed half of a u-joint</td></tr>
<tr><td>CV / double cardan head</td><td>Shaft end, high-angle side</td><td>Series, centering style, flange pattern if any</td><td>Constant velocity through large angles</td></tr>
</tbody></table></div>

<h2 id="choose">Choosing for a build</h2>
<p>When you spec a shaft in the <a href="/online-builder">online builder</a>, the yoke questions are, in order: what is on the transmission (spline for a slip yoke, or flange pattern), what is on the pinion (end yoke series, or flange pattern), and what series you want the shaft built in. The builder offers matching yokes at each step and prices the result. Three rules of thumb:</p>
<ul>
<li>Match the <strong>series at each end to the yoke already on the vehicle</strong>, or use a conversion joint at one end. Do not try to convert both ends.</li>
<li>If the vehicle has a <strong>flange</strong>, keep the flange. Converting a flanged pinion to a yoke means a new companion flange and often a different pinion nut and seal.</li>
<li>For <strong>lifted or high-angle</strong> applications, put a CV (double cardan) head on the transfer case end and a single joint at the pinion; the <a href="/guides/driveshaft-angles-and-pinion-angle">angle guide</a> explains why.</li>
</ul>
<p>Still unsure which yoke you are looking at? Photograph each end square-on and <a href="/contact">send it to the shop</a>. We identify yokes from photos every day.</p>
@endif
@endsection
