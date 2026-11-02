@extends('guides.layout')
@php
$faqs = [
  ['Can a bad u-joint cause vibration at highway speed only?', 'Yes. A joint with a small amount of play is quiet at low speed but the play lets the shaft move off its balanced center as RPM rises, so the vibration appears at 50 to 70 mph and grows with speed. A clunk on gear changes usually accompanies it.'],
  ['Why does the vibration change when I let off the throttle?', 'Throttle changes the load on the u-joints and the pinion angle. A vibration that comes in under power and fades on coast points to operating angle or pinion angle (the axle rotates nose-up under torque on leaf springs). A vibration that is the same loaded or coasting is more likely balance or runout.'],
  ['How do I know if the driveshaft is out of balance or just out of phase?', 'Look at the yokes from the end. If the ears at each end are not in line with each other the shaft is out of phase; that happens when a two-piece slip joint is reassembled wrong or a tube has been re-welded carelessly. A phased shaft that still shakes has a balance, runout or joint problem.'],
  ['Is a humming noise the center support bearing?', 'A hum or rumble that rises with road speed, in any gear, with a two-piece driveshaft, is usually the center bearing. Confirm it by grabbing the shaft either side of the bearing and checking for play, and by looking for a torn or sagging rubber cushion.'],
  ['Can you balance my driveshaft?', 'Yes. Ship it or bring it to our shop. We check runout, phasing and joints first, then balance on the machine at speed. If the tube is bent or a yoke is worn we will tell you before we spend time balancing a shaft that cannot be fixed that way.'],
];
@endphp
@section('article')
@if(i18n_view('guides.driveshaft-vibration-and-noise-diagnosis'))
@include(i18n_view('guides.driveshaft-vibration-and-noise-diagnosis'))
@else
<p>A driveshaft does one thing at a time: it turns at a speed set by the wheels and gear ratio. That makes its vibrations predictable. Anything the shaft causes will track <strong>road speed</strong>, not engine speed, and will be there in every gear at the same mph. Start by confirming that, then use the symptom to pick the component. The order below goes from most likely to least, and from cheapest to check to most expensive.</p>

<h2 id="first">First: is it the driveshaft?</h2>
<p>Three quick tests separate the driveshaft from tires, engine and transmission.</p>
<ol class="guide-steps">
<li><strong>Speed or RPM?</strong> Find the speed where it is worst, then shift to a different gear at the same road speed. If the vibration stays, it is downstream of the transmission: driveshaft, axle or tires. If it changes with engine RPM, it is engine, converter or transmission.</li>
<li><strong>Coast in neutral.</strong> At the bad speed, shift to neutral and coast. A driveshaft balance or runout vibration continues unchanged; a u-joint angle vibration usually eases because the torque load is gone; a tire problem is identical either way.</li>
<li><strong>Frequency.</strong> A driveshaft spins 3 to 4 times faster than the wheels (the axle ratio). Tire imbalance is a slow shake at 55 to 65 mph you can feel in the seat and steering wheel. Driveshaft imbalance is a faster buzz felt in the floor, console and shifter, and it often makes a mirror blur.</li>
</ol>

<h2 id="symptoms">Symptom table</h2>
<div class="dtable-wrap"><table class="dtable">
<thead><tr><th>Symptom</th><th>Most likely cause</th><th>Confirm by</th></tr></thead>
<tbody>
<tr><td>Buzz that grows with speed, same in every gear</td><td>Imbalance, lost weight, bent tube</td><td>Look for a bare spot where a weight was; check runout</td></tr>
<tr><td>Vibration under power only, fades on coast</td><td>Operating angles, pinion angle</td><td>Measure angles at ride height (<a href="/guides/driveshaft-angles-and-pinion-angle">angle guide</a>)</td></tr>
<tr><td>Clunk shifting into drive or reverse, or on throttle tip-in</td><td>Worn u-joint, worn slip spline, loose pinion nut</td><td>Rock the shaft by hand in neutral; watch each joint for movement</td></tr>
<tr><td>Squeak at walking pace, often one squeak per revolution</td><td>Dry u-joint, failed seal</td><td>Rust-colored dust at the caps</td></tr>
<tr><td>Rumble or hum rising with speed, two-piece shaft</td><td>Center support bearing</td><td>Grab the shaft each side of the bearing and check for play</td></tr>
<tr><td>Shudder taking off from a stop, 5 to 20 mph</td><td>Bound-up slip yoke, failed CV centering ball, excessive angle</td><td>Check slip yoke grease and travel; check the double cardan head</td></tr>
<tr><td>Vibration appeared after lift, lowering or axle swap</td><td>Angles, length, or a shaft now running too long at speed</td><td>Measure angles and overall length against the <a href="/guides/choosing-driveshaft-tubing">critical speed</a></td></tr>
<tr><td>Vibration appeared after reassembling a two-piece shaft</td><td>Out of phase</td><td>Sight down the shaft: yoke ears at each end should be in line</td></tr>
</tbody></table></div>

<h2 id="ujoints">Worn u-joints</h2>
<p>A u-joint wears at the needle bearings in each cap. The first sign is a dry squeak as the grease is gone; the next is play, which you feel as a clunk when torque reverses (into gear, off throttle to on throttle). Play lets the shaft move off center, and a shaft that is not centered is no longer balanced, so a worn joint eventually becomes a vibration too.</p>
<p>To check: transmission in neutral, parking brake off, wheels chocked. Grab the shaft next to each joint and try to twist it against the yoke, then push it up and down. Any movement you can see or feel is too much. Look at each cap for a reddish-brown ring of rust dust, which is needle bearings grinding themselves to powder. Check the yoke ears for an oval hole where a loose cap has been hammering. Replacement joints are listed by series under <a href="/shop/driveshaft-components/u-joints">universal joints</a>; identify the series with the <a href="/guides/u-joint-series-identification-chart">chart</a>. If the ears are worn, the yoke must be replaced, not just the joint.</p>

<h2 id="balance">Balance and lost weights</h2>
<p>A driveshaft is balanced on a machine by welding small weights to the tube near each end. Look for a clean rectangle or a weld scar with no weight on it: the weight has come off. Mud and undercoating packed onto one side of the tube also throw the balance off, as does a tube that has been dented by a rock or a jack. A dent changes the wall thickness distribution and cannot be balanced out reliably.</p>
<p>Runout is the other balance killer. Spin the shaft on the vehicle (rear wheels off the ground, in neutral, by hand) with a dial indicator or a fixed pointer against the tube. More than about 0.010 inch total movement near the ends or 0.015 in the middle means the tube or a yoke is bent, and no amount of weight will make it smooth. A shop will straighten minor runout before balancing; major runout means a new tube.</p>

<h2 id="phasing">Phasing</h2>
<p>The two u-joints on a shaft must be installed so their yokes are <strong>in line</strong> with each other (in phase). Each joint speeds up and slows down twice per revolution when it runs at an angle; with the yokes aligned, the second joint cancels what the first one did. Rotate one joint 90 degrees and the effects add instead of cancel, and the shaft shakes at twice its rotating speed. A one-piece shaft can only go out of phase if it has been cut and re-welded. A two-piece shaft goes out of phase whenever the splined slip joint between the halves is reassembled without matching the marks. Sight down the shaft from one end: the ears of the front yoke and the rear yoke should line up. If they are 90 degrees apart, the fix is free.</p>

<h2 id="angles">Operating angles</h2>
<p>The angle a u-joint works through is the difference between the shaft centerline and the yoke it drives. Too much angle, or angles at the two ends that are not equal and opposite, produce a vibration that comes in under load and that gets worse with speed. Lifted trucks, lowered cars and anything with a swapped axle or transmission are the usual suspects. The <a href="/guides/driveshaft-angles-and-pinion-angle">angle guide</a> covers measuring and correcting; the short version is that each joint should run between 1 and 3 degrees, the two should match within half a degree, and the pinion should be set so that it rotates into alignment under load.</p>

<h2 id="csb">Center support bearing</h2>
<p>On a two-piece shaft, the center support bearing holds the middle of the driveline in a rubber cushion. When the bearing itself fails it hums or growls with road speed. When the rubber fails the shaft sags or jumps, which changes the angles on both joints next to it and produces a vibration or a shudder on takeoff. Check it with the shaft unloaded: grab either side of the bearing and feel for play; look at the rubber for cracks, tears or a shaft that sits visibly off center in the bracket. Replacement is a straightforward job covered in the <a href="/guides/center-support-bearing-replacement">center support bearing guide</a>, and we stock <a href="/shop/driveshaft-components/center-support-bearing">bearings for most trucks</a> by shaft diameter.</p>

<h2 id="inspect">Ten-minute inspection</h2>
<ul class="guide-x">
<li>Rust dust or wetness at any u-joint cap</li>
<li>Any felt play at a joint when the shaft is rocked</li>
<li>Oval or peened yoke ears</li>
<li>Bare spot where a balance weight was welded</li>
<li>Dents, gouges or a visible bow in the tube</li>
<li>Yoke ears at both ends in line (phase)</li>
<li>Slip yoke spline: dry, rusty or with visible rotational play</li>
<li>Center bearing rubber intact, shaft centered in the bracket</li>
<li>Pinion nut tight, pinion flange has no play</li>
<li>Angles measured at ride height, written down</li>
</ul>
<p>If the inspection turns up a worn part on a straight tube, the shaft is repairable: joints, bearing, slip yoke and balance are all shop jobs. If it turns up a dented tube or worn ears, the <a href="/guides/replace-or-repair-a-driveshaft">repair-or-replace guide</a> will help you decide, and the <a href="/online-builder">online builder</a> prices a replacement from your measurements. Not sure what you are looking at? <a href="/contact">Send photos</a>; we look at driveshafts all day.</p>
@endif
@endsection
