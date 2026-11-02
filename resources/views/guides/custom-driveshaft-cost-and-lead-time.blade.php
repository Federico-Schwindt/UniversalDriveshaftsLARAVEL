@extends('guides.layout')
@php
$faqs = [
  ['How much does a custom driveshaft cost?', 'A steel one-piece in 1310 or 1330 series with a slip yoke and weld yokes is the least expensive build; 1350 and 1410 series, aluminum tube, flange ends, CV heads and two-piece construction each add to it. The online builder shows the exact price for your choices before you order, with the balancing included.'],
  ['How long does a custom driveshaft take?', 'Most one-piece steel shafts are built, balanced and shipped within a few business days of the order being confirmed; two-piece shafts and shafts needing special yokes take longer. Premade shafts for common fitments ship the same day when ordered before 2pm ET. Call for the current schedule if you have a deadline.'],
  ['Is balancing included in the price?', 'Yes. Every shaft we build is straightened and balanced at speed before it ships. Painting is included as well. There is no cheaper unbalanced option because an unbalanced shaft is not a finished product.'],
  ['Why is a two-piece driveshaft so much more than a one-piece?', 'It is two shafts plus a center support bearing, a stub shaft with a precision slip spline, two extra u-joints and a second set of yokes, and both halves have to be balanced individually and as an assembly. Roughly double the material and more than double the labor.'],
  ['Can I save money by supplying my own yokes?', 'Sometimes. If your existing slip yoke and flange are in good condition we can build the tube to them, which removes those parts from the price. Worn yokes are not worth reusing; a new shaft built around a yoke with oval ears will vibrate on day one.'],
];
@endphp
@section('article')
@if(i18n_view('guides.custom-driveshaft-cost-and-lead-time'))
@include(i18n_view('guides.custom-driveshaft-cost-and-lead-time'))
@else
<p>A custom driveshaft is priced from a short list of choices, and once you know what each one adds there are no surprises. This guide lays out the price drivers, what is included in the number, the actual component prices from our catalog so you can see where the money goes, and the lead time you should expect. The <a href="/online-builder">online builder</a> does this arithmetic live: pick duty, series, material and yokes, enter the measurements and the price of the balanced, finished shaft is on the screen.</p>

<h2 id="drivers">The five price drivers</h2>
<ol class="guide-steps">
<li><strong>Series.</strong> The u-joint series sets the size of every yoke and joint. 1310 and 1330 parts are the cheapest and most plentiful; 1350 adds a little; 1410, 1480 and 1550 add more because the forgings are bigger and the yokes fewer. Choose the series the vehicle already has unless you are solving a strength problem.</li>
<li><strong>Material.</strong> Steel DOM tube is the baseline. Aluminum tube costs more per foot, needs aluminum-specific weld yokes and more careful welding; expect a meaningful premium for the same length. The <a href="/guides/choosing-driveshaft-tubing">tubing guide</a> explains when it is worth it.</li>
<li><strong>One piece or two.</strong> A two-piece is two shafts, a center bearing, a stub shaft and extra joints. It is the right answer for long wheelbases but it is a different price class. See <a href="/guides/one-piece-vs-two-piece-driveshaft">one- vs two-piece</a>.</li>
<li><strong>End fittings.</strong> A slip yoke and a weld yoke at the pinion are the simplest. Flange yokes cost more than weld yokes; CV (double cardan) heads cost more than either; a splined slip assembly inside a flange-to-flange shaft adds again.</li>
<li><strong>Length.</strong> Tube is sold by the foot, so a 70 inch shaft costs a little more in tube than a 50 inch one. Length can also force the material or the two-piece decision above, which matters far more than the tube itself.</li>
</ol>
<p>What does <em>not</em> move the price: the measurements themselves (any length within a tube's range costs the same labor), paint, and balancing, which are in every build.</p>

<h2 id="included">What is included</h2>
<ul class="guide-x">
<li>Tube cut to your measurement from DOM steel or 6061 aluminum</li>
<li>Weld yokes pressed and welded in a fixture, in the series you chose</li>
<li>New u-joints at both ends (greaseable or sealed, your call)</li>
<li>Slip yoke, flange yoke or CV head as specified</li>
<li>Straightening to under 0.010 inch runout</li>
<li>High-speed balancing on the machine, weights welded</li>
<li>Paint, and packing for freight</li>
</ul>
<p>Not included unless you add them: a pinion end yoke or companion flange for the vehicle side (you usually already have one), strap or u-bolt kits, and a center support bearing bracket for a two-piece if yours is damaged. All are available as <a href="/shop/driveshaft-components">components</a>.</p>

<h2 id="components">Component prices, from the catalog</h2>
<p>These are live catalog prices for typical 1310-class parts, so you can see what the raw material of a one-piece shaft costs before labor:</p>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Part</th><th>Example</th><th>Catalog price</th></tr></thead>
<tbody>
<tr><td>Slip yoke, 1310, 1.250 x 16</td><td><a href="/parts/2-3-1421KX">2-3-1421KX</a></td><td>$23.68</td></tr>
<tr><td>Weld yoke, 1310, 2.000 x 0.083 tube</td><td><a href="/parts/2-28-357">2-28-357</a></td><td>$12.14</td></tr>
<tr><td>Weld yoke, 1310, 2.000 x 0.120 tube</td><td><a href="/parts/2-28-1757">2-28-1757</a></td><td>$11.21</td></tr>
<tr><td>U-joint, 1310, heavy duty</td><td><a href="/parts/5-1310XU">5-1310XU</a></td><td>$11.50 each</td></tr>
<tr><td>Flange yoke, 1310, 3.750 BCD</td><td><a href="/parts/2-2-479">2-2-479</a></td><td>$14.52</td></tr>
<tr><td>DOM tube, 2.000 x 0.095</td><td><a href="/parts/TU295">TU295</a></td><td>$17.19 per foot</td></tr>
<tr><td>DOM tube, 3.500 x 0.083</td><td><a href="/parts/TU3583">TU3583</a></td><td>$19.71 per foot</td></tr>
<tr><td>Center support bearing, GM</td><td><a href="/parts/210527XU">210527XU</a></td><td>$20.22</td></tr>
</tbody></table></div>
<p>Prices shown were current when this guide was written; the part pages are authoritative. The lesson is that parts are the smaller half of a custom shaft. The larger half is the fixture welding, straightening and balancing that turns them into something that runs smooth at 4,000 RPM, and that labor is roughly the same whether the parts are 1310 or 1410.</p>

<h2 id="examples">Three example builds</h2>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Build</th><th>Spec</th><th>Price class</th><th>Typical lead time</th></tr></thead>
<tbody>
<tr><td>Lifted half-ton, rear</td><td>One-piece, 1350, 3.000 x 0.083 steel, slip yoke front, weld yoke rear, 58 in</td><td>Baseline for a custom shaft</td><td>A few business days</td></tr>
<tr><td>Street/strip car</td><td>One-piece, 1350, 3.500 x 0.125 aluminum, slip yoke front, flange or weld yoke rear, 52 in</td><td>Baseline plus aluminum premium</td><td>A few business days, subject to aluminum yoke stock</td></tr>
<tr><td>Long-bed 3/4-ton</td><td>Two-piece, 1350, 3.500 steel both sections, center support bearing, slip stub, 86 in overall</td><td>Roughly double the one-piece</td><td>About a week</td></tr>
</tbody></table></div>
<p>Enter any of these in the <a href="/online-builder">builder</a> and the exact figure appears. For the two-piece, use the <a href="/online-builder/two-piece">two-piece builder</a>, which asks for both bearing dimensions.</p>

<h2 id="lead">Lead time</h2>
<p>Three clocks run on a custom order. <strong>Confirmation:</strong> we check your measurements and yoke choices against the vehicle, which is same-day when the numbers are complete and takes longer when we have to ask questions; the <a href="/guides/how-to-measure-a-driveshaft">measuring guide</a> gets you through this step in one pass. <strong>Build:</strong> a one-piece steel shaft with stocked yokes is typically cut, welded, straightened, balanced and painted within a few business days; aluminum and two-piece builds take longer; shafts needing a yoke we do not stock wait for that yoke. <strong>Shipping:</strong> ground freight from Florida reaches most of the eastern U.S. in two to three days and the west coast in five. Local customers can pick up at the shop. If you have a hard date, call (813) 524-6491 before ordering and we will tell you honestly whether it can be met.</p>

<h2 id="premade">When premade is cheaper</h2>
<p>If the vehicle is stock or near stock, a <a href="/shop/complete-driveshafts">premade driveshaft</a> built to the factory length is cheaper than a custom one and ships the same day. Check the <a href="/catalog/driveshafts">vehicle finder</a> first. Custom earns its price when the vehicle is lifted, lowered, has a swapped transmission or axle, a non-standard wheelbase, or a duty cycle the factory shaft was never built for. If the old shaft is good apart from a worn joint or bearing, a repair is cheaper than either; the <a href="/guides/replace-or-repair-a-driveshaft">repair-or-replace guide</a> helps with that call.</p>
@endif
@endsection
