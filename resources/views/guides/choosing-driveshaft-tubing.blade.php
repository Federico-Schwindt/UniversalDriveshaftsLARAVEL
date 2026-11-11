@extends('guides.layout')
@php
$faqs = [
  ['Is aluminum driveshaft tubing stronger than steel?', 'No, but it is not weaker in the way people expect either. Aluminum has about one third the stiffness and one third the density of steel, so for the same dimensions the critical speed is nearly identical. Aluminum shafts are made larger in diameter and thicker in wall to carry the same torque, and the result weighs roughly half as much. The benefits are lower weight and lower rotating inertia, not higher strength.'],
  ['What tube diameter do I need?', 'Pick the smallest diameter that keeps your maximum RPM under about 75 to 80 percent of critical speed for your length, then check the torque rating of the weld yokes available for that size. For most trucks under 60 inches long, 3.000 x 0.083 steel is the answer. Over about 62 inches at highway RPM, step up to 3.500, go aluminum at 3.500 to 4.000, or split the shaft.'],
  ['What is DOM tubing?', 'Drawn Over Mandrel: an electric-resistance-welded tube that is then cold drawn over a mandrel to size, which removes the weld flash, tightens the wall tolerance and produces the straight, round, uniform-wall tube a driveshaft needs to balance. Seamless and plain ERW tubing are not substitutes.'],
  ['Can I buy a short piece of tubing?', 'Yes. Our tubing is sold by the foot and cut to the length you order, so a 4-foot shaft costs four feet of tube. Tell us the finished weld-to-weld length and we will cut to that plus a trimming allowance.'],
];
@endphp
@section('article')
@if(i18n_view('guides.choosing-driveshaft-tubing'))
@include(i18n_view('guides.choosing-driveshaft-tubing'))
@else
<p>The tube is the longest, lightest and most misunderstood part of a driveshaft. It is not sized for torque first; it is sized for <strong>speed</strong>. Every tube has a rotational speed at which it begins to bow outward under its own centrifugal load and whip. That is the <strong>critical speed</strong>, and it depends on the tube's diameter, wall and above all its length. Choosing driveshaft tubing is the job of keeping the shaft well below that speed while carrying the torque, and the math takes two minutes. Our <a href="/shop/driveshaft-tubing/tubing">cut-to-length DOM tubing</a> is sold by the foot in the sizes discussed here.</p>

<h2 id="materials">DOM steel vs aluminum</h2>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Property</th><th>DOM steel (1020 / 1026)</th><th>6061-T6 aluminum</th><th>What it means</th></tr></thead>
<tbody>
<tr><td>Density</td><td>0.283 lb/in³</td><td>0.098 lb/in³</td><td>Aluminum tube of the same size weighs about one third</td></tr>
<tr><td>Stiffness (E)</td><td>29,000 ksi</td><td>10,000 ksi</td><td>Critical speed depends on E/density, which is nearly equal for both</td></tr>
<tr><td>Typical sizes</td><td>2.000 to 3.500 OD, 0.065 to 0.125 wall</td><td>3.000 to 4.000 OD, 0.125 wall</td><td>Aluminum is made bigger and thicker to match torque</td></tr>
<tr><td>Finished shaft weight, 55 in long, 3 in class</td><td>About 22 lb</td><td>About 13 lb</td><td>Less rotating mass; marginal fuel and response gain</td></tr>
<tr><td>Damage tolerance</td><td>Dents and keeps running</td><td>Dents, gouges and corrosion matter more</td><td>Steel for rocks and work trucks</td></tr>
<tr><td>Weld yoke availability</td><td>Every series</td><td>Fewer, aluminum-specific yokes</td><td>Steel shafts are easier to repair and convert</td></tr>
<tr><td>Cost</td><td>Lower</td><td>Higher material and welding cost</td><td>Aluminum adds to the build price</td></tr>
</tbody></table></div>
<p>The practical rule: <strong>steel</strong> for anything that works for a living, sees rocks, or must be repaired cheaply in the field; <strong>aluminum</strong> when the shaft is long and the speed is high and you want one-piece simplicity without the weight of a 3.500 steel tube, or when rotating mass matters (road racing, drag racing). Carbon fiber exists for racing budgets and is not covered here.</p>

<h2 id="critical">Critical speed: the formula</h2>
<p>For a hollow steel or aluminum tube supported at both ends, the first critical speed in RPM is closely approximated by:</p>
<div class="guide-formula u-mono">N<sub>c</sub> = 4,720,000 &times; &radic;(D&sup2; + d&sup2;) / L&sup2;</div>
<p>where <strong>D</strong> is the tube outside diameter in inches, <strong>d</strong> the inside diameter (OD minus twice the wall) and <strong>L</strong> the tube length in inches. Three things fall out of it:</p>
<ul>
<li>Length is <strong>squared</strong>. Doubling the length cuts the critical speed to one quarter. Nothing else in the formula comes close.</li>
<li>Diameter helps roughly in proportion. Going from 3.000 to 3.500 OD raises critical speed about 17 percent.</li>
<li>Wall thickness barely matters. A thicker wall adds weight and torque capacity, not whip resistance; d gets smaller as D stays put.</li>
</ul>
<p>Never run at critical. Shops size shafts so the <strong>maximum operating speed</strong> (highest engine RPM in top gear, including overdrive, times the final drive ratio if you want to be conservative, or simply the shaft RPM at your top road speed) stays under about <strong>75 to 80 percent</strong> of N<sub>c</sub>. Shaft RPM is engine RPM divided by the transmission ratio in that gear, which in top gear is close to engine RPM.</p>

<h2 id="example">A worked example</h2>
<p>A half-ton pickup with a 56 inch (weld to weld) one-piece shaft, 3.000 OD x 0.083 wall steel tube, that will cruise at 2,400 RPM and see 5,500 RPM in top gear during a hard pull.</p>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Step</th><th>Value</th></tr></thead>
<tbody>
<tr><td>d = 3.000 &minus; 2 &times; 0.083</td><td>2.834 in</td></tr>
<tr><td>D&sup2; + d&sup2; = 9.000 + 8.032</td><td>17.03</td></tr>
<tr><td>&radic;17.03</td><td>4.127</td></tr>
<tr><td>L&sup2; = 56&sup2;</td><td>3,136</td></tr>
<tr><td>N<sub>c</sub> = 4,720,000 &times; 4.127 / 3,136</td><td><strong>6,210 RPM</strong></td></tr>
<tr><td>80 % of N<sub>c</sub></td><td>4,970 RPM</td></tr>
</tbody></table></div>
<p>The 5,500 RPM peak is over the 80 percent line. Options, in order of cost: step to <strong>3.500 x 0.083</strong> steel (N<sub>c</sub> about 7,250, 80 percent is 5,800 RPM: passes), go to <strong>3.500 x 0.125 aluminum</strong> (nearly the same N<sub>c</sub> at half the weight), or build a <strong>two-piece</strong> which halves each L and quadruples N<sub>c</sub>. If the same truck had a 64 inch shaft, 3.000 steel gives only 4,750 RPM critical and even 3.500 gives 5,550; at that length a two-piece or a 4.000 aluminum tube is the realistic answer, which is why long-wheelbase trucks leave the factory with two-piece shafts. The <a href="/guides/one-piece-vs-two-piece-driveshaft">one- vs two-piece guide</a> continues that decision.</p>

<h2 id="sizes">Common sizes and what they fit</h2>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Tube (OD x wall)</th><th>Material</th><th>Typical application</th><th>Matching series</th></tr></thead>
<tbody>
<tr><td>2.000 x 0.083 / 0.095 / 0.120</td><td>Steel</td><td>Short shafts: Jeep, front shafts, two-piece rear sections, small cars</td><td>1310, 1330, 3R, 7260</td></tr>
<tr><td>2.500 x 0.083</td><td>Steel</td><td>Mid-length car and light truck shafts</td><td>1310, 1330, 1350, 7290</td></tr>
<tr><td>3.000 x 0.083</td><td>Steel</td><td>The default for half-ton and 3/4-ton trucks to about 60 in</td><td>1310, 1330, 1350, 1410</td></tr>
<tr><td>3.500 x 0.083</td><td>Steel</td><td>Long one-piece truck shafts, 3/4- and one-ton</td><td>1350, 1410, 1480</td></tr>
<tr><td>3.500 x 0.065</td><td>Steel</td><td>Light, long shafts where torque is moderate</td><td>1350</td></tr>
<tr><td>3.500 x 0.125</td><td>Aluminum</td><td>Performance cars, half-ton trucks to about 65 in</td><td>1350, 1410</td></tr>
<tr><td>4.000 x 0.125</td><td>Aluminum</td><td>Long truck shafts, high RPM street cars</td><td>1350, 1410, 1480</td></tr>
<tr><td>4.000 to 5.000 x 0.134 to 0.180</td><td>Steel</td><td>Medium and heavy duty, industrial</td><td>1550, 1610, 1710, SPL series</td></tr>
</tbody></table></div>
<p>Our stock steel is DOM, sold by the foot: for example <a href="/parts/TU3583">TU3583</a> is 3.500 x 0.083 and <a href="/parts/TU2120">TU2120</a> is 2.000 x 0.125. Wall choice within a diameter is about torque and dent resistance: 0.083 is the general-purpose wall, 0.065 saves weight on long shafts in light duty, 0.120 and 0.125 are for short, high-torque shafts and for welding to heavier yokes.</p>

<h2 id="yokes">Matching tube to weld yokes</h2>
<p>A weld yoke is machined for one tube size. Its <strong>butt diameter</strong> equals the tube's inside diameter so it slides in with a light fit, and the shoulder sets the weld position. A 3.000 x 0.083 tube has a 2.834 ID and takes a yoke listed for 3.000 x 0.083; put that yoke in a 3.000 x 0.120 tube (ID 2.760) and it will not enter. Every <a href="/shop/driveshaft-components/weld-yoke">weld yoke</a> in the catalog states the tube OD and wall it fits and the butt diameter, so once you have picked the tube you can filter yokes to it. Aluminum tubes need aluminum weld yokes; steel yokes cannot be welded to aluminum tube. The <a href="/guides/slip-yoke-vs-weld-yoke-vs-flange-yoke">yoke guide</a> covers the rest of the yoke decision.</p>

<h2 id="cut">Ordering cut tubing</h2>
<p>If you are building the shaft yourself, order tubing by the foot from the <a href="/shop/driveshaft-tubing/tubing">tubing category</a>: pick the OD and wall, enter the length in feet, and we cut it. Order the finished weld-to-weld length plus a couple of inches for squaring the ends. Driveshaft tube is cut square, deburred, and the yokes are pressed in and welded in a fixture, after which the shaft must be straightened to under 0.010 inch runout and balanced at speed. If you do not have a fixture and a balancer, the better path is to let us build the whole shaft: the <a href="/online-builder">online builder</a> picks the tube to suit the length and series you choose and shows the price of the balanced assembly. Not sure which way to go? <a href="/contact">Call the shop</a> with your length and RPM and we will run the numbers with you.</p>
@endif
@endsection
