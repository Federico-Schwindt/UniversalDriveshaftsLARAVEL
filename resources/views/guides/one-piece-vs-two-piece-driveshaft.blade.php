@extends('guides.layout')
@php
$faqs = [
  ['Is a one-piece driveshaft better than a two-piece?', 'Neither is better; each is right for a range of lengths. A one-piece has fewer parts, nothing to wear in the middle and is cheaper. A two-piece exists because a single tube over roughly 60 to 65 inches cannot be made light enough and stiff enough to run at highway speed without whipping. Below that length a one-piece wins; above it the two-piece is the engineering answer.'],
  ['Can I convert my two-piece driveshaft to a one-piece?', 'Often, on standard-cab long-bed trucks and some vans where the overall length lands in the mid-60s. It needs a large diameter steel tube (3.500 or more) or aluminum, and the center bearing crossmember stays unused. Over about 70 inches, a one-piece becomes impractical for highway RPM and the two-piece should stay.'],
  ['Why did my truck come with a two-piece driveshaft?', 'Wheelbase. Extended cabs, long beds and vans push the transmission-to-axle distance past the point where a one-piece steel tube of reasonable diameter stays below critical speed. The factory splits the shaft rather than fitting a 4-inch tube under a frame that does not have room for it.'],
  ['Does a two-piece driveshaft vibrate more?', 'Not when it is in phase and the center bearing is healthy. It has more things that can wear (three joints, a bearing, a slip spline) so it needs inspection, and it is sensitive to the bearing height. A worn or sagging center bearing is the most common cause of a two-piece vibration.'],
];
@endphp
@section('article')
@if(i18n_view('guides.one-piece-vs-two-piece-driveshaft'))
@include(i18n_view('guides.one-piece-vs-two-piece-driveshaft'))
@else
<p>Length and speed decide whether a vehicle gets a one-piece or a two-piece driveshaft. A tube that is too long for its diameter will bow and whip at a speed that a highway cruise can reach; the fix is a larger tube, a lighter material or cutting the shaft in two and supporting the middle. That is the whole decision. This guide works through it with real numbers, explains what a two-piece adds (and what can go wrong with it), and covers converting from one to the other. Both are built to order in the <a href="/online-builder">online builder</a>, which has a <a href="/online-builder/two-piece">two-piece mode</a>.</p>

<h2 id="difference">The difference</h2>
<p>A <strong>one-piece</strong> driveshaft is a single tube with a yoke at each end and two u-joints. The slip joint is usually a slip yoke on the transmission output. A <strong>two-piece</strong> driveshaft is two tubes in line. The front section ends in a stub (midship) shaft carrying a center support bearing in a bracket bolted to a frame crossmember; the rear section starts with a slip yoke or splined sleeve that rides on that stub and ends at the pinion. A two-piece therefore has three u-joints, a center bearing, a precision slip spline in the middle and, often, a fixed (non-slip) front yoke or flange because the slip is handled at the stub.</p>

<h2 id="decides">What decides it: length and RPM</h2>
<p>Every tube has a critical speed, approximated by N<sub>c</sub> = 4,720,000 &times; &radic;(D&sup2; + d&sup2;) / L&sup2; (OD, ID and length in inches, result in RPM). Length is squared, so it dominates. Shops keep the shaft under about 80 percent of N<sub>c</sub> at the highest speed it will see. The table shows the longest one-piece tube that stays under that limit at two shaft speeds:</p>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Tube</th><th>Max length at 4,500 RPM</th><th>Max length at 6,000 RPM</th></tr></thead>
<tbody>
<tr><td>2.500 x 0.083 steel</td><td>55 in</td><td>48 in</td></tr>
<tr><td>3.000 x 0.083 steel</td><td>60 in</td><td>52 in</td></tr>
<tr><td>3.500 x 0.083 steel</td><td>65 in</td><td>56 in</td></tr>
<tr><td>3.500 x 0.125 aluminum</td><td>65 in</td><td>56 in</td></tr>
<tr><td>4.000 x 0.125 aluminum</td><td>70 in</td><td>60 in</td></tr>
<tr><td>5.000 x 0.134 steel (medium duty)</td><td>78 in</td><td>67 in</td></tr>
</tbody></table></div>
<p>Lengths are weld-to-weld tube length, rounded down, at 80 percent of calculated critical speed. Read it this way: a half-ton with a 58 inch shaft is a comfortable one-piece in 3.000 steel; an extended-cab long-bed with 84 inches between transmission and pinion cannot be a one-piece in anything that fits under the truck, so it is a two-piece with each half around 42 inches, each of which has a critical speed four times higher than the single 84 inch tube would. The <a href="/guides/choosing-driveshaft-tubing">tubing guide</a> shows the full calculation.</p>

<h2 id="two-piece">What a two-piece adds</h2>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Component</th><th>Purpose</th><th>Wear and failure</th></tr></thead>
<tbody>
<tr><td><a href="/shop/driveshaft-components/center-support-bearing">Center support bearing</a></td><td>Holds the middle of the driveline at the designed height in a rubber cushion</td><td>Bearing growls; rubber sags and changes the joint angles</td></tr>
<tr><td><a href="/shop/driveshaft-components/stub-midship-shaft">Stub (midship) shaft</a></td><td>Carries the bearing and the slip spline the rear shaft rides on</td><td>Spline wear, bearing journal scoring</td></tr>
<tr><td>Third u-joint</td><td>Joins the two sections just behind the bearing</td><td>Same as any joint; runs at a small angle set by bearing height</td></tr>
<tr><td>Rear slip yoke or <a href="/shop/driveshaft-components/splined-sleeves">splined sleeve</a></td><td>Length change between sections</td><td>Rotational play; must be reassembled in phase</td></tr>
<tr><td>Bracket and shims</td><td>Mount and height adjustment on the crossmember</td><td>Loose bolts; lost shims after service</td></tr>
</tbody></table></div>
<p>The extra parts are the reason a two-piece costs more and needs more attention. They are also the reason it works: each section is short enough to run fast with a small tube, and the center bearing keeps the joint angles small and constant. A two-piece that vibrates almost always has a bearing, phase or angle problem; the <a href="/guides/center-support-bearing-replacement">bearing guide</a> and <a href="/guides/driveshaft-vibration-and-noise-diagnosis">vibration guide</a> cover both.</p>

<h2 id="convert">Converting two-piece to one-piece</h2>
<p>People convert for three reasons: the center bearing has failed repeatedly, the truck has been lifted and the two-piece angles are now hard to manage, or they want to remove parts. It is feasible when the overall length (transmission output to pinion, at ride height) is within reach of a one-piece tube from the table above. In practice that means standard-cab long-bed and extended-cab short-bed trucks in the 62 to 70 inch range, done in 3.500 steel or 4.000 aluminum. Beyond about 70 inches the one-piece either needs a tube too large for the tunnel or a lower RPM limit than highway driving allows.</p>
<p>The conversion needs: the overall length measured correctly (see <a href="/guides/how-to-measure-a-driveshaft">the measuring guide</a>), the transmission output spline for a new slip yoke, the pinion yoke series or flange pattern, and a check of the operating angles, because the one-piece now runs a single straight line from transmission to pinion and the pinion angle may need to change. The crossmember and bracket stay empty. The <a href="/online-builder">one-piece builder</a> handles the order once the numbers are in hand.</p>
<p>Converting the other way, one-piece to two-piece, is rare and only makes sense on a vehicle that has a factory crossmember to carry the bearing, typically a long-wheelbase chassis that originally had a two-piece.</p>

<h2 id="compare">Side by side</h2>
<div class="vs2 guide-vs">
  <div class="vs2__col">
    <span class="kicker">One-piece</span>
    <h3>Simple, light, cheaper</h3>
    <ul class="checks"><li>Two u-joints, one slip joint</li><li>No center bearing to wear</li><li>Lower cost, faster build</li><li>Limited by length: about 60 to 65 in steel, 70 in aluminum</li><li>Angles set entirely by transmission and pinion</li></ul>
  </div>
  <div class="vs2__col">
    <span class="kicker">Two-piece</span>
    <h3>Long wheelbases, any speed</h3>
    <ul class="checks"><li>Three u-joints, center bearing, slip spline</li><li>Each section short and stiff</li><li>Smaller tube fits a tight tunnel</li><li>Higher cost, more wear items</li><li>Bearing height sets the middle angles</li></ul>
  </div>
</div>

<h2 id="order">Specifying either one</h2>
<p>For a one-piece, we need the length (joint center to joint center or flange face), the transmission spline or front flange pattern, the pinion yoke series or rear flange pattern, and the tube diameter you have room for. For a two-piece, add the center bearing position from each end, the stub shaft spline and the bracket style. The <a href="/driveshaft-designer">driveshaft designer</a> has a drawing for each, with every dimension labeled; fill it in and send it to the shop, or go straight to the <a href="/online-builder">online builder</a> for a price. If the truck is stock, a <a href="/catalog/driveshafts">vehicle lookup</a> may find a premade shaft in either configuration already on the shelf.</p>
@endif
@endsection
