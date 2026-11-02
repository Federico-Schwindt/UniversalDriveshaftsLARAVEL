@extends('guides.layout')
@php
$faqs = [
  ['Is it worth repairing a driveshaft?', 'Usually, if the tube is straight and undented. U-joints, a center support bearing, a slip yoke and a balance are all repairs that cost a fraction of a new shaft and leave you with a factory tube. If the tube is dented, bent or twisted, or a yoke has oval ears, the repair approaches the cost of a new shaft and a new one is the better buy.'],
  ['Can a bent driveshaft be straightened?', 'Minor runout, a few hundredths of an inch, can be straightened in a press and balanced out. A visibly bowed tube or one that has been bent in a crash or by a failed joint has yielded and will not hold straight; it gets re-tubed or replaced.'],
  ['Can you re-tube my driveshaft and keep my yokes?', 'Yes, if the yokes are good. Re-tubing cuts the old tube off, presses and welds the yokes into new DOM tube at your length, then straightens and balances. It is the standard repair for a dented or rusted tube and also how a shaft is shortened or lengthened.'],
  ['How long can I drive on a worn u-joint?', 'A squeaking joint has weeks; a clunking joint has days. Once the needle bearings break up the cap wears through the cross in a few hundred miles, the joint lets go and the shaft drops or whips, which can take out the transmission tailhousing, the floor or the fuel line. Replace it when it squeaks.'],
];
@endphp
@section('article')
@if(i18n_view('guides.replace-or-repair-a-driveshaft'))
@include(i18n_view('guides.replace-or-repair-a-driveshaft'))
@else
<p>A driveshaft is a few wear parts hung on a tube that does not wear. Almost every fault is one of those parts, and replacing the part is a repair that leaves you with the factory tube and the factory balance point. A few faults are the tube or the yokes themselves, and those are replacements. The job is telling them apart, which takes ten minutes and a flashlight. This guide gives you the inspection, sorts the findings into repair and replace, and compares costs so you can decide before you order anything.</p>

<h2 id="inspect">The inspection</h2>
<p>Vehicle in neutral, parking brake off, wheels chocked, rear wheels off the ground on stands under the axle if you can. Work from the transmission back.</p>
<ol class="guide-steps">
<li><strong>Slip yoke.</strong> Grab the shaft near the front and twist against the transmission: rotational play at the spline is wear. Check the barrel for a groove where the seal rides and for a leak. Make sure it has travel left: push the shaft forward and back.</li>
<li><strong>Front u-joint.</strong> Twist and lift the shaft against the slip yoke. Any movement, any rust dust at the caps, any wetness or squeak is a worn joint. Look at the yoke ears: a bright, peened or oval cap hole means the yoke is gone too.</li>
<li><strong>Tube.</strong> Run a hand and the light along the full length. Dents, gouges, a crease, missing balance weights (a clean rectangle or a weld scar), heavy rust scale, a weld that looks repaired, or a visible bow. Spin the shaft by hand with a pointer held near the tube: more than about 0.010 inch of wobble at the ends is runout.</li>
<li><strong>Center support bearing</strong> (two-piece). Grab either side and feel for play; inspect the rubber for cracks and sag; spin and listen for grit.</li>
<li><strong>Rear u-joint and pinion yoke.</strong> Same test as the front. Check that the pinion yoke itself has no play on the pinion (a loose pinion nut mimics a bad joint).</li>
<li><strong>Phase.</strong> Sight down the shaft; the yoke ears at each end should be in line. On a two-piece, check the marks at the slip joint.</li>
</ol>
<p>Write down what you found. The <a href="/guides/driveshaft-vibration-and-noise-diagnosis">vibration guide</a> connects each finding to the symptom you drove in with.</p>

<h2 id="repair">Faults that are repairs</h2>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Finding</th><th>Repair</th><th>Parts</th><th>Notes</th></tr></thead>
<tbody>
<tr><td>Worn or dry u-joint, yoke ears good</td><td>Replace joint(s)</td><td><a href="/shop/driveshaft-components/u-joints">U-joint</a> in the right series, $10 to $50</td><td>Replace both; the other is the same age. Identify the series with the <a href="/guides/u-joint-series-identification-chart">chart</a></td></tr>
<tr><td>Center support bearing noisy or sagging</td><td>Replace bearing</td><td><a href="/shop/driveshaft-components/center-support-bearing">Bearing</a>, $20 to $60</td><td>Mark phase first. <a href="/guides/center-support-bearing-replacement">Step-by-step</a></td></tr>
<tr><td>Slip yoke spline play or seal groove</td><td>Replace slip yoke</td><td><a href="/shop/driveshaft-components/slip-yoke">Slip yoke</a>, $20 to $80</td><td>Check the transmission output spline too</td></tr>
<tr><td>Lost balance weight, no other damage</td><td>Rebalance</td><td>Shop labor</td><td>Includes runout check and straightening of minor bow</td></tr>
<tr><td>Minor runout, straight tube otherwise</td><td>Straighten and balance</td><td>Shop labor</td><td>Up to a few hundredths; more means re-tube</td></tr>
<tr><td>Out of phase (two-piece reassembled wrong)</td><td>Re-index at the slip joint</td><td>None</td><td>Free, and it is the first thing to check after any work</td></tr>
<tr><td>Worn weld yoke ears, tube good</td><td>Replace weld yoke</td><td><a href="/shop/driveshaft-components/weld-yoke">Weld yoke</a>, $11 to $40, plus labor</td><td>Shop job: press out, weld in, straighten, balance</td></tr>
<tr><td>Dented or rusted tube, yokes good</td><td>Re-tube</td><td><a href="/shop/custom-cut-tubing/tubing">DOM tube</a> by the foot, plus labor</td><td>Also how a shaft is shortened or lengthened</td></tr>
</tbody></table></div>
<p>Joints and bearings are owner-level jobs with a press or a large vise and the right sockets. Anything that involves welding or balancing is a shop job, and a shop that does driveshafts daily does it faster and straighter than a general repair shop with a hammer.</p>

<h2 id="replace">Faults that mean replacement</h2>
<ul class="guide-x">
<li><strong>Tube bent, creased or twisted.</strong> Steel that has yielded does not stay straight after straightening. A twisted tube (spiral marks, visible wind-up) has been overloaded and is cracked internally even if it looks whole.</li>
<li><strong>Deep dents.</strong> A dent moves metal out of the balanced circle and thins the wall at the edges. Shallow dings can be balanced around; anything you can lay a finger in cannot.</li>
<li><strong>Multiple worn yokes plus a worn tube.</strong> When the repair list is new tube, two weld yokes, a slip yoke and three joints, you have described a new shaft at a higher labor cost.</li>
<li><strong>Cracked yoke or weld.</strong> A crack at the ear, the barrel or the weld is a failure in progress. Replace the yoke; if the weld area of the tube is cracked, replace the shaft.</li>
<li><strong>Series too small for the use.</strong> A 1310 shaft behind a built engine, under a lifted and tired truck, or on a trailer-tower that keeps eating joints is not a repair problem. Upgrading the series is a new shaft.</li>
<li><strong>Wrong length.</strong> After a lift, a lowering, a transmission or axle swap, the old shaft is the wrong part. Re-tubing to a new length is possible if the yokes suit the new vehicle; otherwise build new.</li>
</ul>

<h2 id="cost">Cost comparison</h2>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Scenario</th><th>Repair route</th><th>Replace route</th><th>Call</th></tr></thead>
<tbody>
<tr><td>Clunk, one worn joint, tube straight</td><td>Two joints, $25 to $100 in parts, an afternoon</td><td>New shaft, several hundred</td><td>Repair</td></tr>
<tr><td>Hum from center bearing, 150k miles</td><td>Bearing $20 to $60 plus the two joints next to it</td><td>New two-piece, roughly double a one-piece</td><td>Repair</td></tr>
<tr><td>Vibration, lost weight, otherwise fine</td><td>Shop balance</td><td>New shaft</td><td>Repair</td></tr>
<tr><td>Dented tube from a rock, yokes fine</td><td>Re-tube and balance</td><td>New shaft, slightly more</td><td>Either; re-tube if the yokes are a hard-to-find series</td></tr>
<tr><td>Dented tube, oval ears, slip yoke worn</td><td>Re-tube, two yokes, slip yoke, joints, balance</td><td>New shaft</td><td>Replace</td></tr>
<tr><td>Lifted 4 inches, now vibrates</td><td>Nothing to repair; the length and angles changed</td><td>New shaft, likely CV front</td><td>Replace</td></tr>
</tbody></table></div>
<p>The general rule shops use: if the repair bill is approaching 60 to 70 percent of a new shaft, replace. You get new everything, a current-production balance and a warranty on the assembly, not just the part. The <a href="/guides/custom-driveshaft-cost-and-lead-time">cost guide</a> explains what goes into the new-shaft price.</p>

<h2 id="upgrade">When to upgrade instead</h2>
<p>A repeat failure is information. A joint that lasts a year is running at too much angle, too much torque for its series, or without grease. A tube that keeps losing weights was never straight. If the vehicle has changed (power, lift, tires, towing) since the shaft was made, use the replacement as a chance to move up a series, go to a larger or aluminum tube, add a CV head on the high-angle end or convert to a flange where the pinion offers one. The <a href="/guides/driveshaft-angles-and-pinion-angle">angle guide</a> and <a href="/guides/choosing-driveshaft-tubing">tubing guide</a> cover each decision; the <a href="/online-builder">online builder</a> prices the result.</p>

<h2 id="next">Next step</h2>
<p><strong>Repair:</strong> identify the series and order the parts from the <a href="/shop/driveshaft-components">components catalog</a>, or ship the shaft to us; we press joints and bearings, re-tube, straighten and balance in house and return it the same week. <strong>Replace:</strong> if the vehicle is stock, the <a href="/catalog/driveshafts">vehicle finder</a> may show a <a href="/shop/complete-driveshafts">premade shaft</a> on the shelf. Otherwise measure it (<a href="/guides/how-to-measure-a-driveshaft">guide</a>) and use the <a href="/online-builder">online builder</a>. Unsure after the inspection? Photograph the findings and <a href="/contact">send them to the shop</a>, or call (813) 524-6491. We would rather tell you it is a $12 joint than sell you a shaft.</p>
@endif
@endsection
