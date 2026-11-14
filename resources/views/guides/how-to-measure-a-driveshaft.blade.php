@extends('guides.layout')
@php
$faqs = [
  ['Do I measure a slip yoke driveshaft compressed or installed?', 'Measure it the way the shop that builds it asks. Our online builder wants the slip yoke fully collapsed (bottomed out on the transmission) so the compressed length is known exactly; we then set the running engagement when we cut the tube. If you can only measure installed, say so and give ride-height conditions.'],
  ['What if I do not have the old driveshaft?', 'Measure the vehicle instead: from the transmission or transfer case output (face of the seal or center of the output yoke u-joint) to the center of the pinion yoke u-joint or the face of the pinion flange, at ride height with the suspension loaded. Also record the output spline count and diameter, and the pinion yoke series or flange bolt pattern. The builder accepts a vehicle-based measurement.'],
  ['How accurate does a driveshaft measurement need to be?', 'Within 1/8 inch. A slip yoke gives roughly an inch of travel, but that travel is for suspension movement, not for measurement error. Flange-to-flange shafts have no slip at all, so an error there means the shaft does not bolt up.'],
  ['Which measurement do I give for a two-piece driveshaft?', 'All of them: A (overall), B1 and B2 (each end to the center bearing), C1 and C2 (each shaft), W1 and W2 (each tube) and the tube diameter. The center bearing position is the critical one because the bracket location on the crossmember cannot move.'],
  ['Can you measure it for me?', 'Yes. Bring the shaft or the vehicle to our shop, or ship the shaft to us. We measure, build and balance in house and can usually turn a one-piece around in a few days.'],
];
@endphp
@section('article')
@if(i18n_view('guides.how-to-measure-a-driveshaft'))
@include(i18n_view('guides.how-to-measure-a-driveshaft'))
@else
<p>A driveshaft is built to a length and nothing else matters if that length is wrong. Shops across the country measure the same way, using the same reference points, because the parts that make up the shaft (yokes, tube, u-joints) are all defined from the <strong>centerline of the u-joint cross</strong> and the <strong>face of a flange</strong>. Learn those two references and every measurement below falls into place. This guide uses the same letters as our <a href="/driveshaft-designer">driveshaft designer</a>, so you can draw the shaft, type the numbers into it and print or send the sheet.</p>

<h2 id="refs">The references: A, B, C, W and T</h2>
<p>Five letters describe any shaft. A one-piece uses C, W and T. A two-piece adds A and B, and numbers the front shaft 1 and the rear shaft 2.</p>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Ref</th><th>What it is</th><th>Measured from</th><th>Measured to</th></tr></thead>
<tbody>
<tr><td>A</td><td>Overall length of the complete assembly</td><td>Front end reference (joint center or flange face)</td><td>Rear end reference</td></tr>
<tr><td>B1 / B2</td><td>Front end to center bearing; center bearing to rear end</td><td>End reference</td><td>Center of the bearing (middle of the rubber cushion)</td></tr>
<tr><td>C1 / C2</td><td>Length of each individual shaft</td><td>Joint center or flange face</td><td>Joint center or flange face on the other end of that shaft</td></tr>
<tr><td>W1 / W2</td><td>Tube length, weld to weld</td><td>Front weld seam</td><td>Rear weld seam</td></tr>
<tr><td>T</td><td>Tube outside diameter</td><td colspan="2">Caliper across the tube, away from the weld and any balance weights</td></tr>
</tbody></table></div>
<p>Two rules govern the end references. If the end has a <strong>u-joint</strong> (slip yoke, end yoke, weld yoke with a cross in it) the reference is the <strong>center of the cross</strong>, which you locate as the centerline of the bearing caps. If the end is a <strong>flange</strong> (flange yoke, CV flange, companion flange) the reference is the <strong>flange face</strong> that bolts to the mating part. Never measure to the end of a yoke ear or the lip of a cap.</p>

<div class="guide-note"><b>Why the cross center?</b> Every yoke in the catalog is dimensioned as "centerline to end" (a slip yoke listed as 5.000 CL to end puts the cross 5 inches from the spline end). When you give us a joint-center length, we subtract the yoke dimensions and cut the tube exactly.</div>

<h2 id="tools">What you need</h2>
<ul>
<li>A steel tape or, better, a rigid rule for anything under 24 inches. Fabric tapes stretch.</li>
<li>A caliper for the tube diameter and spline diameter. A tape wrapped around the tube gives circumference, not diameter.</li>
<li>Paint marker or chalk to mark the phase of the yokes before anything comes apart.</li>
<li>The vehicle at <strong>ride height</strong> with the suspension loaded, if you are measuring on the vehicle. Jack stands under the axle, not the frame.</li>
</ul>

<h2 id="one-piece">Measuring a one-piece shaft</h2>
<p>Three cases cover almost every one-piece shaft. Identify the ends first, then measure.</p>
<h3>Slip yoke at the transmission, end yoke or flange at the axle</h3>
<ol class="guide-steps">
<li>Push the slip yoke all the way onto the transmission output until it bottoms out. This is how our <a href="/online-builder">online builder</a> expects the length: fully collapsed.</li>
<li>Measure <strong>C</strong> from the center of the front u-joint cross to the center of the rear cross (end yoke at the pinion) or to the rear flange face (flange yoke at the pinion).</li>
<li>Measure <strong>W</strong> weld seam to weld seam along the tube.</li>
<li>Caliper <strong>T</strong>, the tube outside diameter. Common sizes are 2.000, 2.500, 3.000 and 3.500 inches for steel and 3.500 to 4.000 for aluminum.</li>
<li>Record the slip yoke spline: count the splines and caliper the major diameter. 1.250 x 16, 1.375 x 16 and 1.500 x 16 cover most light trucks.</li>
</ol>
<h3>Flange to flange (no slip yoke)</h3>
<p>Many imports, late-model trucks and all industrial shafts bolt a flange at each end. The slip travel lives inside the shaft (a splined slip assembly) or there is none. Measure <strong>C</strong> face to face with the shaft installed, at ride height. There is no collapsed or extended position to worry about, but there is also no forgiveness: the number must be right to the sixteenth. Record each flange's <strong>bolt circle diameter</strong>, <strong>hole count and size</strong>, and <strong>pilot diameter</strong> (the raised or recessed ring that centers the flange). The <a href="/guides/slip-yoke-vs-weld-yoke-vs-flange-yoke">yoke guide</a> explains how to read a flange.</p>
<h3>Measuring on the vehicle with no shaft</h3>
<p>When the old shaft is gone or is the wrong one, measure the vehicle. Suspension loaded, measure from the transmission output to the pinion: center of the output yoke cross to center of the pinion yoke cross, or seal face to flange face if there is no slip yoke. Tell us which references you used. We need the output shaft spline (count and diameter) and the pinion yoke series or flange pattern as well.</p>

<h2 id="two-piece">Measuring a two-piece shaft</h2>
<p>A two-piece has a front shaft that ends in a <strong>center support bearing</strong> on a stub (midship) shaft, and a rear shaft that slips onto that stub. The bracket for the bearing bolts to a crossmember, so its position is fixed by the vehicle. That is why we need both B dimensions and not just the overall A.</p>
<ol class="guide-steps">
<li>Mark the phase: a paint stripe across the splined slip joint between the two shafts so the rear yoke goes back in the same rotational position.</li>
<li>Measure <strong>A</strong>, front reference to rear reference, with the slip joint in its installed position.</li>
<li>Measure <strong>B1</strong> from the front reference to the center of the support bearing (the midpoint of the rubber, not the bracket face). Measure <strong>B2</strong> from the bearing center to the rear reference. B1 + B2 should equal A; if it does not, re-measure.</li>
<li>Measure <strong>C1</strong>, the front shaft from its front reference to the center of the cross at its rear u-joint (behind the bearing), and <strong>C2</strong>, the rear shaft from that same cross center to the rear reference.</li>
<li>Measure <strong>W1</strong> and <strong>W2</strong>, weld to weld on each tube, and caliper <strong>T</strong> on each. Front and rear tubes are often different diameters.</li>
<li>Record the stub shaft spline (count and diameter) and the bearing's bracket style. The <a href="/guides/center-support-bearing-replacement">bearing guide</a> covers identification.</li>
</ol>
<p>If you only have the vehicle, locate the bearing crossmember and measure from the transmission output to the center of the crossmember bolt pattern for B1, then from there to the pinion for B2. The <a href="/driveshaft-designer/2">two-piece drawing in the designer</a> labels every one of these so nothing is missed.</p>

<h2 id="ends">Yokes, flanges and spline data</h2>
<p>Length alone does not define the shaft. Each end has to mate with the vehicle, so collect this with the measurements:</p>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>End type</th><th>What to record</th><th>How</th></tr></thead>
<tbody>
<tr><td>Slip yoke</td><td>Spline count, major diameter, u-joint series</td><td>Count splines, caliper across the tips; measure the u-joint cap (<a href="/guides/u-joint-series-identification-chart">series chart</a>)</td></tr>
<tr><td>End yoke (pinion or T-case)</td><td>U-joint series, strap or u-bolt style</td><td>Cap diameter and width; note if caps are held by straps, u-bolts or bolted caps</td></tr>
<tr><td>Flange yoke</td><td>Bolt circle, hole count and size, pilot diameter, male or female pilot</td><td>Measure center to center across opposite holes; caliper the pilot</td></tr>
<tr><td>CV / double cardan</td><td>Series, centering ball style, flange pattern if flanged</td><td>Measure the joint caps; photograph the head</td></tr>
</tbody></table></div>
<p>Photos help. One from the side showing the whole shaft, one of each end square-on, and one of any tags or stamped numbers on the tube or yoke. Spicer, Neapco and OEM shafts often carry a part number that tells us the series instantly.</p>

<h2 id="mistakes">Mistakes that cost a rebuild</h2>
<ul class="guide-x">
<li><strong>Measuring to the ends of the yoke ears.</strong> Ears vary by series and manufacturer. The cross center does not.</li>
<li><strong>Measuring a slip yoke half-extended</strong> without saying so. The length then depends on where the spline happened to sit that day.</li>
<li><strong>Vehicle on a frame lift</strong> with the axle hanging. The driveshaft is at full extension; at ride height it is shorter by the suspension travel.</li>
<li><strong>Circumference instead of diameter.</strong> A 2.500 tube measures 7.85 around. Giving us 7.85 as the diameter is an obvious error; giving us 3.000 after a bad conversion is not.</li>
<li><strong>Forgetting the phase mark</strong> on a two-piece. The shaft goes back together 180 degrees out and the vibration gets blamed on the new part.</li>
<li><strong>Not stating the u-joint series.</strong> 1310 and 1330 share the same cap diameter and look identical from three feet away.</li>
</ul>

<h2 id="send">Sending the numbers</h2>
<p>You have three ways to get the measurements to us. The <a href="/online-builder">online builder</a> takes the length and yoke choices and shows the price of the assembled, balanced shaft immediately. The <a href="/driveshaft-designer">driveshaft designer</a> lets you draw the exact shaft, click each dimension to type it in, then print the sheet or send it to the shop with your contact details. Or <a href="/contact">call or email</a> the shop with the numbers and photos and we will quote it. If the vehicle is a common fitment, check the <a href="/catalog/driveshafts">vehicle finder</a> first; a <a href="/shop/complete-driveshafts">premade driveshaft</a> may already exist at the factory length, usually shipping the same day when ordered before 2 pm EST.</p>
@endif
@endsection
