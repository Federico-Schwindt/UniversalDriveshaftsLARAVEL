<?php

/*
 * Measurement columns of each category table, in order, as they stood on 2026-11-11 (before PTI's attributes were
 * copied in). CatalogService::categorySchema() keeps these first and in this order, so filling data never reshuffles a
 * table or the letters of its "how to measure" drawing; a newly populated measurement only joins when a slot is free.
 */

return [
    'balance-weights' => ['pa_weight'],
    'ball-stud-yokes' => ['pa_ball-stud-length-dia', 'pa_butt-dia', 'pa_tube-dia-wall', 'pa_center-u-j-to-weld'],
    'boot-cans-bearing-retainers' => ['pa_stub-diam', 'pa_boot-diam', 'pa_height', 'pa_use-with'],
    'boot-clamps' => ['pa_diameter', 'pa_type-sp'],
    'boot-kits' => ['pa_length', 'pa_lg-dia-boot', 'pa_sm-dia-boot', 'pa_boot-part-no', 'pa_lg-clamp-part-no', 'pa_sm-clamp-part-no'],
    'center-support-bearing' => ['pa_bearing-id', 'pa_ctr-to-ctr-of-mount-holes', 'pa_application', 'pa_center-bearing-to-base'],
    'certifed-driveshafts' => ['pa_cl-to-cb'],
    'companion-flanges' => ['pa_bolt-circle-dia', 'pa_pilot-dia', 'pa_through-hole', 'pa_hub-dia', 'pa_bolt-holes', 'pa_splines-count'],
    'cv-ball-kit' => ['pa_ball-dia', 'pa_seal-height', 'pa_seal-o-d', 'pa_stud-bore-dia'],
    'cv-double-joints-heads' => ['pa_style-series', 'pa_tube-dia-wall', 'pa_butt-dia', 'pa_bolt-hole-dia', 'pa_end-to-weld', 'pa_bolt-circle-dia'],
    'cv-flange-yokes' => ['pa_bolt-circle-dia', 'pa_bolt-hole-dia', 'pa_c-l-to-face', 'pa_pilot-dia', 'pa_pilot-type', 'pa_ball-stud-dia'],
    'cv-joint-kit' => ['pa_splines-count', 'pa_outer-dia', 'pa_splines-dia', 'pa_inner-race-width', 'pa_bolt-hole-dia', 'pa_outer-race-width'],
    'cv-link-yoks' => ['pa_ball-stud-dia', 'pa_bolt-hole-dia', 'pa_center-u-joint-to-end'],
    'dust-cap-press-on-and-threaded' => ['pa_type-sp', 'pa_inside-dia', 'pa_seal-od-id', 'pa_seal-type', 'pa_washer-od-id', 'pa_diameter'],
    'end-yokes' => ['pa_cj-to-end', 'pa_hub-dia', 'pa_bore-type', 'pa_splines-count', 'pa_through-hole', 'pa_splines-dia'],
    'flange-yokes' => ['pa_bolt-circle-dia', 'pa_bolt-hole-dia', 'pa_pilot-dia', 'pa_cj-to-flat', 'pa_bolt-holes'],
    'h-yokes' => ['pa_cj-to-cj', 'pa_swing-dia'],
    'hardware' => ['pa_thread-size', 'pa_length-under-head', 'pa_self-locking', 'pa_grade', 'pa_type-sp', 'pa_hex-size'],
    'high-performance-flange-yokes' => ['pa_bolt-circle-dia', 'pa_bolt-hole-dia', 'pa_bolt-holes', 'pa_cj-to-flat', 'pa_pilot-dia'],
    'increase-bushings' => ['pa_outer-dia', 'pa_inside-dia', 'pa_from-tube', 'pa_to-tube', 'pa_length'],
    'lube-zerk-fittings' => ['pa_hex', 'pa_overall-len', 'pa_thread-size'],
    'nose-bushings' => ['pa_od', 'pa_inside-dia', 'pa_height'],
    'nuts' => ['pa_self-locking', 'pa_thickness', 'pa_thread-size', 'pa_hex'],
    'premade-driveshafts' => ['pa_vehicle', 'pa_d-year', 'pa_tube-diameter', 'pa_transmission', 'pa_material', 'pa_model'],
    'pto-end-yokes' => ['pa_bore-type', 'pa_cj-to-end', 'pa_hub-dia', 'pa_through-hole', 'pa_bore-dia', 'pa_keyway'],
    'rubber-flex-disc' => ['pa_bolt-circle-dia', 'pa_bolt-hole-count', 'pa_bolt-hole-dia', 'pa_application', 'pa_pilot-dia', 'pa_disc-width'],
    'short-couple-driveshafts' => ['pa_cl-to-cl', 'pa_flange-to-flange'],
    'slip-yoke' => ['pa_center-u-joint-to-end', 'pa_splines-count', 'pa_splines-dia'],
    'small-parts' => ['pa_type-sp'],
    'snap-rings' => ['pa_color', 'pa_thickness', 'pa_fits-brg-dia'],
    'splined-sleeves' => ['pa_material', 'pa_butt-dia', 'pa_splines-count', 'pa_splines-dia', 'pa_fits-to-tube', 'pa_end-to-weld'],
    'strap-kits' => ['pa_center-to-center', 'pa_thread-dia', 'pa_fits-cap-dia', 'pa_bolt-length', 'pa_bolt-torque'],
    'stub-midship-shaft' => ['pa_butt-dia', 'pa_end-to-weld', 'pa_splines-count-dia', 'pa_tube-dia', 'pa_splines-length', 'pa_tube-wall'],
    'transmission-yokes' => ['pa_hub-dia', 'pa_splines-count-dia', 'pa_center-u-joint-to-end', 'pa_counter-bore-dia'],
    'tri-quad-prongs' => ['pa_bolt-circle-dia', 'pa_bolt-hole-dia', 'pa_butt-dia', 'pa_face-to-weld', 'pa_nose-bushing-dia', 'pa_tube-dia-wall'],
    'tubing' => ['pa_material', 'pa_tube-length', 'pa_tube-wall', 'pa_tube-dia'],
    'u-bolt-kits' => ['pa_thread-dia'],
    'u-joint-seal-protectors' => ['pa_brg-dia', 'pa_ujoint-number'],
    'u-joints' => ['pa_cap-dia-1', 'pa_lube', 'pa_width-1'],
    'washers-snap-rings' => ['pa_thickness', 'pa_inside-dia', 'pa_outside-dia'],
    'weld-yoke' => ['pa_center-u-j-to-weld', 'pa_tube-dia-wall', 'pa_material', 'pa_butt-dia'],
    'yoke-shafts' => ['pa_splines-count', 'pa_splines-dia', 'pa_c-l-to-end', 'pa_splines-length', 'pa_boot-cap-part'],
];
