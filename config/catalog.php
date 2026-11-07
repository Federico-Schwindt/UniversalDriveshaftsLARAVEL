<?php

/**
 * UDS catalog structure — maps the 4 top-level groups to the real
 * WooCommerce product categories in the uds_shop database.
 * Group -> subcategory grid -> product table (PTI style).
 */
return [
    // products whose photo on the live store shows a different part: the hourly sync does not copy it,
    // the page shows no photo until the right one is uploaded (owner, 2026-10-24). wp product id => note
    'wrong_images' => [
        63835 => '3102-80 flange yoke showed a stub shaft',
    ],

    // product/category attachment files (wp_postmeta _wp_attached_file): the local mirror of the old WordPress
    // uploads (catalog:localize-images, App\Support\WpUploads), served by this site
    'uploads' => '/uploads/wp/',

    'groups' => [
        'complete-driveshafts' => [
            'label' => 'Complete Driveshafts',
            'blurb' => 'Ready-to-ship premade, certified and short-couple driveshafts. Know the vehicle? Find the exact shaft by year, make and model.',
            'categories' => ['premade-driveshafts', 'short-couple-driveshafts', 'unwelded-assemblies'],
        ],
        'driveshaft-components' => [
            'label' => 'Driveshaft Components',
            'blurb' => 'Yokes, u-joints, flanges, center support bearings and every component in between.',
            'categories' => [
                'flange-yokes', 'weld-yoke', 'u-joints', 'end-yokes', 'slip-yoke', 'transmission-yokes',
                'stub-midship-shaft', 'rubber-flex-disc', 'pto-end-yokes', 'cv-flange-yokes', 'tri-quad-prongs',
                'splined-sleeves', 'cv-joint-kit', 'cv-double-joints-heads', 'companion-flanges', 'ball-stud-yokes',
                'h-yokes', 'cv-link-yoks', 'yoke-shafts', 'center-support-bearing',
            ],
        ],
        'custom-cut-tubing' => [
            'label' => 'Custom Cut Tubing',
            'blurb' => 'Steel and aluminum driveshaft tubing, cut to your length.',
            'categories' => ['tubing'],
        ],
        'small-parts' => [
            'label' => 'Small Parts',
            'blurb' => 'Balance weights, straps, bushings, hardware, snap rings and shop consumables.',
            'children_of' => 'small-parts',
        ],
    ],

    // filters offered on the product table
    'filters' => [
        'brand'  => ['label' => 'Brand',  'tax' => 'pa_brand'],
        'series' => ['label' => 'Series', 'tax' => 'pa_series'],
        'style'  => ['label' => 'Style',  'tax' => 'pa_style'],
    ],
];
