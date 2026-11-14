<?php

/**
 * On-page SEO copy for the parts catalog: per-category buying guidance, the
 * singular part-type name used in product titles, and nominal dimensions for
 * the u-joint series hubs (/series/{series}). Copy is hand-written and specific
 * to the part type; keep it factual (no superlatives, no fake counts).
 */
return [

    // product_cat slug => [type (singular, for product titles), title (the category as people search it, for its <title>;
    // from Search Console queries where there are any, 2026-11-04 and 2026-11-13 — without one the title leads with the count),
    // desc (optional meta description, {count} = live count), intro (1-2 sentences of buying guidance)]
    'categories' => [
        'u-joints' => [
            'type' => 'U-Joint',
            'title' => 'Driveshaft U-Joints',
            'desc' => '{count} driveshaft u-joints in stock: 1310, 1350, 1410 and more, greaseable or sealed, inside (ISR) and outside snap ring (OSR). Ships from Florida.',
            'intro' => 'A universal joint is sized by its series: measure the bearing cap diameter and the width from snap ring to snap ring (1310 = 1.062" caps × 3.219", 1350 = 1.188" × 3.625", 1410 = 1.188" × 4.188"). Then pick the retention style your yokes use (outside snap ring, inside snap ring or combination) and whether you want a greaseable joint or a sealed lube-for-life joint.',
        ],
        'flange-yokes' => [
            'type' => 'Flange Yoke',
            'title' => 'Driveshaft Flange Yokes',
            'intro' => 'Flange yokes bolt the driveshaft to a companion flange on the axle, transfer case or transmission. Match three things: the u-joint series (cap diameter), the bolt pattern (number of holes, bolt circle diameter and hole size) and the pilot (male or female, and its diameter). The same bolt pattern is often offered in several series, so confirm the joint size first.',
        ],
        'weld-yoke' => [
            'type' => 'Weld Yoke',
            'title' => 'Driveshaft Weld Yokes',
            'intro' => 'A weld yoke (tube yoke) is welded to the driveshaft tube and carries the u-joint. Choose by series and by tube size: the yoke butt diameter must match your tube OD and wall thickness (for example 3.000" × 0.083"). We stock weld yokes for steel and aluminum tube in 1310 through 1810 and SPL series.',
        ],
        'end-yokes' => [
            'type' => 'End Yoke',
            'title' => 'Driveshaft End Yokes',
            'intro' => 'End yokes mount on the splined output or pinion shaft and accept the u-joint. Select by series, spline count and spline diameter (for example 1350 series, 30 splines × 1.375"), then check the hub diameter and whether you need a strap, u-bolt or snap-ring retention style.',
        ],
        'slip-yoke' => [
            'type' => 'Slip Yoke',
            'title' => 'Driveshaft Slip Yokes',
            'desc' => '{count} driveshaft slip yokes in stock by series, spline count and diameter: 1310, 1350, 1410, 1480. Ships from Florida, live price and stock.',
            'intro' => 'A slip yoke is the internally splined yoke of a driveshaft\'s slip joint: it slides on a splined stub shaft (or yoke shaft) so the shaft can change length as the suspension moves, and a dust cap or boot keeps grit out of the splines. Match the u-joint series, the spline count and spline diameter of the stub it rides on, the center-of-joint-to-end length and the dust-cap (center-to-washer) dimension. Yokes that slide on a transmission output shaft are listed under Transmission Yokes.',
        ],
        'transmission-yokes' => [
            'type' => 'Transmission Yoke',
            'title' => 'Transmission Slip Yokes',
            'desc' => '{count} transmission slip yokes (trans yokes) in stock by series and spline: 1310, 1330, 1350, 1410. Match your output shaft; ships from Florida.',
            'intro' => 'A transmission yoke is a slip yoke that slides onto the transmission or transfer-case output shaft; its polished hub runs inside the output seal and the splines let the driveshaft change length. Order by u-joint series (bearing cap diameter), spline count and spline diameter of the output shaft, hub diameter (the seal surface) and the center-of-joint-to-end length. Count the splines and measure the hub on the old yoke before ordering.',
        ],
        'stub-midship-shaft' => [
            'type' => 'Stub Shaft',
            'title' => 'Driveshaft Stub & Midship Shafts',
            'intro' => 'A splined stub shaft is welded into the driveshaft tube and forms the male half of the slip joint: the slip yoke slides on its splines. Midship versions also carry the journal for the center support bearing on two-piece shafts. Match the tube outside diameter and wall, the butt diameter, the spline count, diameter and length, the end-to-weld length and, for midship shafts, the bearing journal diameter.',
        ],
        'rubber-flex-disc' => [
            'type' => 'Flex Disc',
            'title' => 'Driveshaft Flex Discs (Guibo)',
            'intro' => 'Rubber flex discs (giubo couplings) connect the driveshaft to the transmission or differential flange on BMW, Mercedes-Benz, Toyota and other import shafts, absorbing small misalignment and damping vibration. Match the bolt-hole count, bolt circle diameter, bolt-hole diameter, centering (pilot) diameter and disc width to the original.',
        ],
        'pto-end-yokes' => [
            'type' => 'PTO End Yoke',
            'title' => 'PTO End Yokes',
            'intro' => 'PTO end yokes fit power take-off and agricultural drivelines, mostly in the 1000 and 1100 series. Choose by series, then by bore type (round, square, hexagonal or splined), bore size and keyway or set-screw arrangement.',
        ],
        'cv-flange-yokes' => [
            'type' => 'CV Flange Yoke',
            'title' => 'CV Flange Yokes',
            'intro' => 'A CV flange yoke is the flange end of a double-cardan CV joint: it bolts to the companion flange of the transfer case or axle and carries the centering ball or socket. Match the u-joint series, the bolt circle, bolt-hole diameter and pilot (type and diameter), the center-to-face length and the ball stud diameter.',
        ],
        'tri-quad-prongs' => [
            'type' => 'Tri/Quad Prong',
            'title' => 'Tri & Quad Prong Flanges',
            'intro' => 'Tri-prong and quad-prong flanges are the three- or four-arm ends that are welded to the driveshaft tube and bolt to a rubber flex disc (giubo), mostly on BMW, Land Rover, Hyundai and other import shafts. Match the bolt circle, the bolt-hole and pilot diameters, the nose-bushing (centering) diameter, the tube outside diameter and wall, the butt diameter and the face-to-weld length.',
        ],
        'splined-sleeves' => [
            'type' => 'Splined Sleeve',
            'title' => 'Splined Sleeves & Slip Assemblies',
            'intro' => 'A splined sleeve is the internally splined female half of a slip joint that is welded into the driveshaft tube; a yoke shaft slides inside it. Match the spline count and diameter to the yoke shaft it works with, the tube it welds into (butt diameter, tube OD and wall) and the end-to-weld length.',
        ],
        'cv-joint-kit' => [
            'type' => 'CV Joint Kit',
            'title' => 'CV Joint Kits',
            'intro' => 'A CV joint kit is a constant-velocity joint (mostly Rzeppa ball type, plunging or fixed) for import and AWD propeller shafts, supplied with the parts listed for each kit (gasket, snap ring, boot or cap). Match the outer diameter, the bolt circle with its hole count and size, the spline count and diameter, and the outer and inner race widths to the original joint.',
        ],
        'cv-double-joints-heads' => [
            'type' => 'CV Double Joint Head',
            'title' => 'Double Cardan CV Heads',
            'intro' => 'A double-cardan CV head is the complete two-u-joint constant-velocity assembly (with its centering ball and socket) that welds to the end of the tube on lifted, 4x4 and high-angle driveshafts. Order by u-joint series, style (flange or slip yoke end), the flange bolt circle and pilot, and the tube it welds to (tube OD and wall, butt diameter) plus the end-to-weld length.',
        ],
        'companion-flanges' => [
            'type' => 'Companion Flange',
            'title' => 'Companion Flanges',
            'intro' => 'A companion flange mounts on the pinion or output shaft and bolts to the driveshaft flange yoke. Match the spline count and diameter to the shaft, then the bolt pattern (hole count, bolt circle and hole diameter) and pilot to the flange yoke.',
        ],
        'ball-stud-yokes' => [
            'type' => 'Ball Stud Yoke',
            'title' => 'Ball Stud Yokes',
            'intro' => 'A ball stud yoke is the tube (weld) yoke of a double-cardan joint: it is welded to the driveshaft tube and carries the ball stud that rides in the centering socket. Match the u-joint series, the tube outside diameter and wall, the butt diameter, the center-to-weld length and the ball stud diameter and length to the centering yoke and kit you use.',
        ],
        'h-yokes' => [
            'type' => 'H Yoke',
            'title' => 'H Yokes',
            'intro' => 'An H yoke (coupling or center yoke) joins two u-joints back to back in a double-cardan joint. Order by the u-joint series on each side, the center-to-center distance between the joints and the swing diameter.',
        ],
        'cv-link-yoks' => [
            'type' => 'CV Link Yoke',
            'title' => 'CV Link Yokes',
            'intro' => 'A CV link (centering) yoke is the center yoke of a double-cardan CV joint: it links the two u-joints and holds the centering socket that rides on the ball stud. Order the same series as the joints it links and the ball stud diameter it accepts, and check the center-of-joint-to-end length and whether it is greaseable.',
        ],
        'yoke-shafts' => [
            'type' => 'Yoke Shaft',
            'title' => 'Driveshaft Yoke Shafts',
            'intro' => 'A yoke shaft is the externally splined male half of the slip joint, usually on a short couple assembly, with the u-joint yoke machined on one end; it slides inside a splined sleeve welded in the tube. Match the u-joint series, the spline count, diameter and length, and the center-of-joint-to-end length to the shaft you are rebuilding.',
        ],
        'center-support-bearing' => [
            'type' => 'Center Support Bearing',
            'title' => 'Driveshaft Center Support Bearings',
            'desc' => '{count} driveshaft center support bearings in stock, by part number and vehicle (Ford, GM, Dodge, Toyota, Mercedes and more). Ships from Florida.',
            'intro' => 'A center support (carrier) bearing holds the midpoint of a two-piece driveshaft. Identify it by bearing inside diameter (the shaft journal), the center-to-center distance of the mounting holes, and the height from the bearing center to the mounting base; the application column lists the vehicles or series each one fits.',
        ],
        'premade-driveshafts' => [
            'type' => 'Driveshaft',
            'title' => 'Premade Driveshafts',
            'intro' => 'Ready-to-ship premade driveshafts are built, balanced and listed by vehicle, transmission and length. Check the series, tube diameter and material (steel or aluminum), then confirm the overall length and the end types against the shaft coming out of the truck.',
        ],
        'short-couple-driveshafts' => [
            'type' => 'Short Couple Driveshaft',
            'title' => 'Short Couple Driveshafts',
            'intro' => 'Short-couple driveshafts are very short assemblies with a slip joint, used between closely spaced components such as transfer case to axle, transmission to divorced transfer case, or between the axles of tandem heavy trucks. Match the u-joint series and the yoke or flange on each end, then the installed (center-to-center) length.',
        ],
        'unwelded-assemblies' => [
            'type' => 'Unwelded Assembly',
            'title' => 'Unwelded Driveshaft Assemblies',
            'intro' => 'Unwelded assemblies supply the yokes, tube and slip components as a kit for shops that weld and balance in house. Select the series and tube size; cut the tube to your measured length before welding.',
        ],
        'tubing' => [
            'type' => 'DOM Tubing',
            'title' => 'Driveshaft Tubing',
            'intro' => 'Driveshaft tubing is sold by the foot in DOM steel and 6061 aluminum. Pick the outside diameter and wall thickness your weld yokes are machined for (3.000" × 0.083" and 3.500" × 0.083" are the most common on light trucks); heavier wall and larger OD raise critical speed and torque capacity. Tell us the cut length in the order notes.',
        ],
        // small parts (children of the small-parts category)
        'small-parts' => [
            'type' => 'Small Part',
            'intro' => 'Hardware and shop consumables for driveshaft work: snap rings, straps, u-bolts, dust caps, bushings and balance weights. Order by series or by the dimension shown in each row.',
        ],
        'balance-weights' => [
            'type' => 'Balance Weight',
            'title' => 'Driveshaft Balance Weights',
            'intro' => 'Driveshaft balance weights are welded to the tube during balancing. Choose by weight (ounces or pounds), style and material; stainless weights are used on aluminum tube.',
        ],
        'increase-bushings' => [
            'type' => 'Increase Bushing',
            'title' => 'Driveshaft Increase Bushings',
            'intro' => 'Increase bushings are adapter rings that let a weld yoke, ball stud yoke or spline made for one tube size be welded into a larger tube (for example 2.5" to 3.0" OD). Match the bushing\'s inside diameter to the yoke\'s butt diameter and its outside diameter to the inside of the new tube, from-tube and to-tube sizes as listed.',
        ],
        'strap-kits' => [
            'type' => 'Strap Kit',
            'title' => 'U-Joint Strap Kits',
            'intro' => 'Strap kits hold the u-joint bearing caps in a strap-style end yoke. They are series-specific: a 1310 strap will not fit a 1350 yoke. Kits include two straps and four bolts.',
        ],
        'nuts' => [
            'type' => 'Nut',
            'title' => 'Pinion & Yoke Nuts',
            'intro' => 'Pinion and yoke nuts by thread size and grade. Match the thread diameter and pitch to the shaft; use a new nut when the old one was staked or had a locking insert.',
        ],
        'snap-rings' => [
            'type' => 'Snap Ring',
            'title' => 'U-Joint Snap Rings',
            'desc' => '{count} u-joint snap rings in stock for inside (ISR) and outside snap ring (OSR) universal joints, by series and size. Ships from Florida.',
            'intro' => '',
        ],
        'washers-snap-rings' => [
            'type' => 'Snap Ring',
            'title' => 'U-Joint Snap Rings & Washers',
            'desc' => 'U-joint snap rings, retaining clips and washers in stock for inside (ISR) and outside snap ring (OSR) u-joints. Ships from Florida.',
            'intro' => 'Snap rings and washers retain u-joint caps and set bearing position. Snap rings are sized to the series and to the cap groove style (inside or outside); thickness is used to center the joint.',
        ],
        'cv-ball-kit' => [
            'type' => 'CV Ball Kit',
            'title' => 'CV Centering Ball Kits',
            'intro' => 'A CV ball (centering) kit rebuilds the ball and socket that keep a double-cardan joint centered; kits include the socket, spring and seals. Order by the ball stud diameter and the socket bore, and choose greaseable or non-greaseable to match your centering yoke.',
        ],
        'dust-cap-press-on-and-threaded' => [
            'type' => 'Dust Cap',
            'title' => 'Slip Yoke Dust Caps',
            'intro' => 'Slip yoke dust caps keep grit out of the splines. Press-on caps are sized by the yoke boss diameter; threaded caps by the thread size on the yoke. Match the series listed.',
        ],
        'nose-bushings' => [
            'type' => 'Nose Bushing',
            'title' => 'Driveshaft Nose Bushings',
            'intro' => 'Nose bushings (centering bushings) are pressed into the transmission or differential flange and center the driveshaft nose on flex-disc (giubo) and prong-flange shafts, mostly BMW and other imports. Replace them whenever the shaft comes off. Match the outside diameter, the inside diameter and the height.',
        ],
        'u-bolt-kits' => [
            'type' => 'U-Bolt Kit',
            'title' => 'Driveshaft U-Bolt Kits',
            'intro' => 'U-bolt kits clamp the u-joint caps into a u-bolt style end yoke. They are series-specific; each kit includes two u-bolts, lock washers and nuts.',
        ],
        'lube-zerk-fittings' => [
            'type' => 'Zerk Fitting',
            'title' => 'Zerk Grease Fittings',
            'intro' => 'Grease (zerk) fittings for greaseable u-joints and slip yokes, by thread size and angle. Check whether your joint takes a straight or angled fitting.',
        ],
        'hardware' => [
            'type' => 'Hardware',
            'title' => 'Driveline Hardware',
            'intro' => 'Bolts, washers and miscellaneous driveline hardware by thread size and grade.',
        ],
        'keyway' => [
            'type' => 'Key',
            'title' => 'PTO Yoke Keys & Keystock',
            'intro' => 'Keys and keystock for keyed PTO bores. Match the key width and length to the keyway in the yoke.',
        ],
        'boot-kits' => [
            'type' => 'Boot Kit',
            'title' => 'Driveshaft Boot Kits',
            'intro' => 'Boot kits seal a driveshaft\'s slip joint or CV joint against dirt and water and keep the grease in. Match the large and small boot diameters and the length, and use the clamps listed with each kit.',
        ],
        // no buying copy yet: the <title> only (Search Console 2026-11-13: "drive shaft boot", "driveshaft boot")
        'boot-clamps' => ['title' => 'Driveshaft Boot Clamps'],
        'boot-cans-bearing-retainers' => ['title' => 'Boot Cans & Bearing Retainers'],
        'u-joint-lock-straps-wires' => ['title' => 'U-Joint Lock Straps & Wires'],
        'u-joint-seal-protectors' => ['title' => 'U-Joint Seal Protectors'],
        'welch-plugs' => ['title' => 'Welch Plugs'],
    ],

    // top-level catalog groups: one-paragraph intro used in the group title/description
    'groups' => [
        'complete-driveshafts' => 'Balanced, ready-to-install driveshafts: premade shafts by vehicle, short-couple assemblies and certified take-offs. Find yours by year, make and model, or by series and length.',
        'driveshaft-components' => 'Every component of a driveshaft, organized by series: u-joints, weld yokes, slip and end yokes, flange yokes, CV heads, center support bearings and flex discs.',
        'driveshaft-tubing' => 'DOM steel and aluminum driveshaft tubing in the common OD and wall sizes, sold by the foot and cut to your length.',
        'small-parts' => 'The small hardware a driveshaft rebuild needs: snap rings, strap and u-bolt kits, dust caps, balance weights and bushings.',
    ],

    /*
     * U-joint series hubs. Dimensions are the nominal Spicer-style cap (bearing) diameter and
     * snap-ring-to-snap-ring width. Series without reliable published figures carry only a description.
     */
    'series' => [
        '1310' => ['cap' => '1.062"', 'width' => '3.219"', 'desc' => 'The 1310 is the most common light-duty series: factory on most 1/2-ton pickups, Jeeps, Mustangs and many passenger cars. It shares the 1.062" cap with the 1330 but is narrower.'],
        '1330' => ['cap' => '1.062"', 'width' => '3.625"', 'desc' => 'The 1330 uses the same 1.062" caps as the 1310 with a wider 3.625" span, so 1330 yokes clear more angle. Common on Ford 1/2 and 3/4-ton trucks and as a conversion step between 1310 and 1350.'],
        '1350' => ['cap' => '1.188"', 'width' => '3.625"', 'desc' => 'The 1350 is the standard 3/4-ton and performance series: larger 1.188" caps on the same 3.625" width as the 1330. It is the usual upgrade for lifted trucks, off-road builds and street-strip cars.'],
        '1410' => ['cap' => '1.188"', 'width' => '4.188"', 'desc' => 'The 1410 keeps the 1350 cap size and widens the joint to 4.188", adding strength and angle capacity. Common in 1-ton trucks and competition driveshafts, and it accepts 1350 strap kits on the cap side.'],
        '1480' => ['cap' => '1.375"', 'width' => '4.188"', 'desc' => 'The 1480 steps up to 1.375" caps on a 4.188" width. Found on 1-ton and medium-duty trucks (Duramax, Power Stroke, Cummins) and on heavy off-road shafts.'],
        '1550' => ['cap' => '1.375"', 'width' => '4.875"', 'desc' => 'The 1550 shares the 1480 cap at a wider 4.875" span for higher torque and angle. Used on medium-duty trucks, motorhomes and heavy conversion shafts.'],
        '1610' => ['cap' => '1.625"', 'width' => null, 'desc' => 'The 1610 is a medium-duty truck series with 1.625" caps, used on Class 5-7 trucks, buses and heavy equipment.'],
        '1710' => ['cap' => '1.938"', 'width' => null, 'desc' => 'The 1710 is a heavy-duty Class 7-8 truck series with 1.938" caps. It is the base of the 1710 / 1760 / 1810 family that shares many center support bearings.'],
        '1760' => ['cap' => '1.938"', 'width' => null, 'desc' => 'The 1760 is a heavy-duty series that shares the 1.938" cap of the 1710 with heavier yokes, common on Class 8 tractors and vocational trucks.'],
        '1810' => ['cap' => '2.125"', 'width' => null, 'desc' => 'The 1810 is the largest of the common Spicer heavy-duty series, with 2.125" caps, used on Class 8 trucks, heavy haul and off-highway equipment.'],
        '1000' => ['cap' => null, 'width' => null, 'desc' => 'The 1000 series is a light agricultural and PTO series. Most parts in it are PTO end yokes with round, square, hex or splined bores.'],
        '1100' => ['cap' => null, 'width' => null, 'desc' => 'The 1100 is a light-duty industrial and agricultural series, a step up from the 1000, used on PTO shafts and small equipment drivelines.'],
        '1210' => ['cap' => '1.062"', 'width' => null, 'desc' => 'The 1210 uses 1.062" caps like the 1310 but with a narrower span; it appears on older passenger cars and light trucks.'],
        '1251' => ['cap' => null, 'width' => null, 'desc' => 'The 1251 is a Spicer light-duty slip-assembly series, mostly splined sleeves and yoke shafts.'],
        '1300' => ['cap' => null, 'width' => null, 'desc' => 'The 1300 series is a light-duty passenger-car series related to the 1310.'],
        '3R' => ['cap' => '1.125"', 'width' => '2.556"', 'desc' => 'The 3R is the GM Saginaw series (1.125" caps, 2.556" width) used on 1960s-80s GM cars and trucks and on many GM-pattern double-cardan heads; it is often combined with a 1310 or 1350 on conversion joints.'],
        '7260' => ['cap' => '1.078"', 'width' => '2.125"', 'desc' => 'The 7260 is the small Chrysler / Dodge series (1.078" caps, 2.125" width) found on Mopar passenger cars and light trucks.'],
        '7290' => ['cap' => '1.125"', 'width' => '2.625"', 'desc' => 'The 7290 is the larger Chrysler / Dodge series (1.125" caps, 2.625" width) used on Mopar performance cars and Dodge trucks.'],
        'SPL90' => ['cap' => null, 'width' => null, 'desc' => 'Spicer Life SPL90 is a light-duty permanently lubricated series used on late-model GM trucks and SUVs.'],
        'SPL100' => ['cap' => null, 'width' => null, 'desc' => 'Spicer Life SPL100 is a non-greaseable light-duty series used on late-model pickups and vans.'],
        'SPL140' => ['cap' => null, 'width' => null, 'desc' => 'Spicer Life SPL140 is a medium-duty permanently lubricated series used on heavier pickups and chassis cabs.'],
        'SPL170' => ['cap' => null, 'width' => null, 'desc' => 'Spicer Life SPL170 is a heavy-duty truck series common on Class 7-8 tractors and vocational chassis; sealed and greaseable joints are both available.'],
        'SPL250' => ['cap' => null, 'width' => null, 'desc' => 'Spicer Life SPL250 is a heavy-duty Class 8 series, a step up from the SPL170, used on linehaul and heavy vocational trucks.'],
        'SPL350' => ['cap' => null, 'width' => null, 'desc' => 'Spicer Life SPL350 is the largest Spicer Life series, used on heavy haul and off-highway drivelines.'],
    ],
];
