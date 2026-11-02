@extends('guides.layout')
@php
$faqs = [
  ['How do I tell a 1310 from a 1330 u-joint?', 'Both have 1.062 inch caps. Measure the width across the caps, snap ring groove to snap ring groove: a 1310 is 3.219 inches, a 1330 is 3.622 inches. If the shaft has a 1330 at one end and 1310 at the other, it was most likely built with a combination joint and the yokes will tell you which is which.'],
  ['Can I put a greaseable u-joint where a sealed one was?', 'Yes, if the series is the same. The grease fitting sits in the body of the cross and does not change the cap or width dimensions. Orient the zerk so it is reachable and so it points toward the shaft centerline where possible; that puts the strongest section of the cross in line with the load.'],
  ['What does a conversion u-joint do?', 'It has two caps of one series and two caps of another, so a 1350 yoke can run against a 1310 yoke, or a 1310 yoke against a 3R/S44 yoke. It is the normal way to join a transmission yoke of one series to an axle yoke of another without changing either. Strength follows the smaller series.'],
  ['What is the difference between 3R and S44?', 'They are the same joint envelope under two names: 3R is the Spicer designation and S44 is Saginaw. Both use 1.125 inch caps on a 2.556 inch width with inside snap rings, and parts listed as 3R/S44 fit either.'],
];
@endphp
@section('article')
@if(i18n_view('guides.u-joint-series-identification-chart'))
@include(i18n_view('guides.u-joint-series-identification-chart'))
@else
<p>Every driveshaft u-joint is identified by two numbers: the <strong>bearing cap diameter</strong> and the <strong>width across the caps</strong>, measured from snap ring groove to snap ring groove (or lock ring to lock ring on outside-ring joints). The series name (1310, 1350, 7290 and so on) is shorthand for that pair of dimensions. Measure a cap, look it up in the chart below and you know which joint, which yokes and which strength class you are dealing with. Every u-joint we sell is filed under its series in the <a href="/shop/driveshaft-components/u-joints">universal joints category</a>.</p>

<h2 id="chart">The series chart</h2>
<div class="dtable-wrap"><table class="dtable guide-chart">
<thead><tr><th>Series</th><th>Cap dia (in)</th><th>Width (in)</th><th>Snap ring</th><th>Typical use</th></tr></thead>
<tbody>
<tr><td><a href="/series/1210">1210</a></td><td>1.062</td><td>2.625</td><td>Outside</td><td>Small cars, light SUVs, Nissan/Subaru flange shafts</td></tr>
<tr><td><a href="/series/1310">1310</a></td><td>1.062</td><td>3.219</td><td>Outside (inside on 5-153X)</td><td>Jeep, half-ton trucks, most light-duty shafts, Ford 8.8 flange yokes</td></tr>
<tr><td><a href="/series/1330">1330</a></td><td>1.062</td><td>3.622</td><td>Outside</td><td>Half-ton and some 3/4-ton trucks, Ford and Dodge pickups</td></tr>
<tr><td><a href="/series/1350">1350</a></td><td>1.188</td><td>3.622</td><td>Outside</td><td>3/4-ton trucks, Toyota flange shafts, performance and off-road builds</td></tr>
<tr><td><a href="/series/1410">1410</a></td><td>1.188</td><td>4.188</td><td>Outside</td><td>One-ton trucks, racing, high-torque street cars</td></tr>
<tr><td><a href="/series/1480">1480</a></td><td>1.375</td><td>4.188</td><td>Outside</td><td>One-ton and medium duty, drag race</td></tr>
<tr><td><a href="/series/1550">1550</a></td><td>1.375</td><td>4.875</td><td>Outside</td><td>Medium duty, Class 5 to 6, heavy off-road</td></tr>
<tr><td><a href="/series/7260">7260</a></td><td>1.078</td><td>2.125</td><td>Inside</td><td>Chrysler small: Dart, Duster, A-body, Jeep CJ rear</td></tr>
<tr><td><a href="/series/7290">7290</a></td><td>1.125</td><td>2.625</td><td>Inside</td><td>Chrysler large: B/E-body, Dodge trucks, Ram, Dakota</td></tr>
<tr><td><a href="/series/3r">3R / S44</a></td><td>1.125</td><td>2.556</td><td>Inside</td><td>GM Saginaw: Chevelle, Camaro, C10, S10, Blazer</td></tr>
</tbody></table></div>
<p class="guide-scrollhint" aria-hidden="true">Swipe the chart sideways for snap ring style and typical use.</p>
<p>Widths are nominal. A used joint will measure a few thousandths under; a new one may read a few thousandths over because the snap ring groove edge is chamfered. Tolerance is not the problem; the problem is the pairs that share a cap diameter and differ only in width (1310 vs 1330, 1350 vs 1410, 1480 vs 1550, 7290 vs 3R). Always measure both.</p>

<h2 id="measure">How to measure a cap</h2>
<ol class="guide-steps">
<li><strong>Cap diameter.</strong> With the joint out of the yoke, caliper straight across one bearing cap. If the joint is still in the yoke, measure the hole in the yoke ear instead; it is the same nominal dimension (1.062, 1.078, 1.125, 1.188 or 1.375).</li>
<li><strong>Width.</strong> On an outside snap ring joint, measure from the <em>inside face</em> of one lock ring groove to the inside face of the opposite groove; in practice, caliper across the two caps with the rings removed and you have it. On an inside snap ring joint, measure the overall length across the two caps, end to end, because the rings sit inside the yoke ear and the cap ends are the reference.</li>
<li><strong>Cross-check against the yoke.</strong> Measure the outside of the two yoke ears across the joint. For outside-ring joints, this is roughly the width minus the groove depth; for inside-ring joints, the ears sit between the caps and the overall cap length is slightly greater than the ear spread.</li>
</ol>
<div class="guide-note"><b>No caliper?</b> A 1 1/16 inch open-end wrench slips over a 1.062 cap and binds on a 1.125 cap. It is not a measurement, but it separates 1310/1330 from 3R/7290 at the bench.</div>

<h2 id="snap-rings">Inside vs outside snap rings</h2>
<p>An <strong>outside snap ring</strong> (OSR) joint has a groove near the open end of each cap; a C-shaped ring snaps into that groove on the outside of the yoke ear and holds the cap in. Spicer and most aftermarket light-truck joints are OSR. An <strong>inside snap ring</strong> (ISR) joint uses a flat ring that sits in a groove machined in the yoke ear, inboard of the cap; the cap is pushed in from the outside until it bottoms on the ring. GM Saginaw (3R/S44), Chrysler (7260/7290) and many import joints are ISR. The style does not change strength, but it changes which yoke the joint fits: an OSR cap in an ISR yoke has nothing to hold it. Our part names carry OSR or ISR in the description; for example the <a href="/parts/5-92XU">5-92X</a> is listed as an ISR-style joint.</p>

<h2 id="grease">Greaseable vs non-greaseable</h2>
<p>A <strong>greaseable</strong> joint has a zerk fitting in the body of the cross (or on one cap) and should be lubricated at service intervals, which flushes contaminated grease out past the seals. A <strong>non-greaseable</strong> (sealed, lubed-for-life) joint has a solid cross with no fitting. Because there is no drilled passage, a sealed cross of the same series is stronger, which is why OEMs and racers use them. The tradeoff is that once water gets past the seal there is no way to purge it, and the joint runs until it fails.</p>
<ul>
<li><strong>Daily driver, towing, farm, salt-belt winters:</strong> greaseable. Service it when you change oil.</li>
<li><strong>Race car, high-horsepower street car, anything you will not maintain:</strong> non-greaseable, highest-strength version available.</li>
<li><strong>Off-road with water crossings:</strong> greaseable, and purge after every wet trip.</li>
</ul>
<p>Both styles are the same dimensions within a series, so the choice is yours at ordering time. Where we stock both, the part page says which it is; our UDS-branded joints carry a <span class="u-mono">U</span> suffix (for example <a href="/parts/5-1310XU">5-1310XU</a>).</p>

<h2 id="conversion">Conversion joints</h2>
<p>A conversion (combination) joint has two caps of one series on one axis and two of another on the other axis. It lets a transmission yoke of one series drive a shaft built in another, or mate a shaft to an axle yoke it was never meant for. Common ones: 1310 to 1350, 1330 to 1350, 1350 to 1410, 1410 to 1480, and 1310 to 3R/S44 (the <a href="/parts/5-3022x">5-3022X</a> in our catalog). A conversion joint is rated to the weaker series and is slightly less tolerant of high operating angles, so treat it as a solution for one end of a shaft, not both.</p>

<h2 id="pick">Picking the right joint</h2>
<p>Once you have the series, the remaining decisions are style and grade:</p>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Decision</th><th>Options</th><th>Pick this when</th></tr></thead>
<tbody>
<tr><td>Retention</td><td>OSR or ISR</td><td>Whatever the yoke was machined for; it is not a choice</td></tr>
<tr><td>Lubrication</td><td>Greaseable or sealed</td><td>Sealed for max strength; greaseable for long service life with maintenance</td></tr>
<tr><td>Grade</td><td>Standard, heavy duty, forged/solid-body</td><td>HD or solid-body for towing, lifted trucks and performance</td></tr>
<tr><td>Brand</td><td>UDS (U suffix), Spicer, Neapco, GMB</td><td>UDS for value with the same dimensions; OEM brand when a spec sheet demands it</td></tr>
</tbody></table></div>
<p>If the shaft is vibrating or clunking and the joints are the suspect, read the <a href="/guides/driveshaft-vibration-and-noise-diagnosis">vibration diagnosis guide</a> before ordering. If the yoke ears are worn oval, new joints will not fix it; the <a href="/guides/replace-or-repair-a-driveshaft">repair-or-replace guide</a> covers that call. Need a whole shaft in a given series? The <a href="/online-builder">online builder</a> lets you pick the series first and prices the assembly.</p>
@endif
@endsection
