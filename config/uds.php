<?php
return [
    // Scheduled PTI jobs (routes/console.php): only one server may run them, they share PTI's single cart. Off on dev.udshafts.com.
    'pti_schedule' => (bool) env('PTI_SCHEDULE', true),
    // Google tags carried over from Site Kit on the old WordPress site (2026-11-04): GA4 property 472848382, Ads conversion ID.
    // On by default in production only, so staging never pollutes the numbers.
    'analytics_enabled' => env('ANALYTICS_ENABLED', env('APP_ENV') === 'production'),
    // Pre-launch guard (App\Support\MailGuard): outside production, e-mail/SMS only to admins, the shop inboxes and these lists
    'mail_guard' => env('MAIL_GUARD', env('APP_ENV') !== 'production'),
    'mail_allow' => env('MAIL_ALLOW', ''),
    'sms_allow' => env('SMS_ALLOW', ''),
    'ga4' => env('GA4_ID', 'G-MG6P2JC6GL'),
    'google_ads' => env('GOOGLE_ADS_ID', '18204111895'),
    'fb_pixel' => env('FB_PIXEL_ID', '1092382942820747'),
    'recaptcha_enabled' => env('RECAPTCHA_ENABLED', false),
    // contact, fitment and driveshaft-designer submissions ("Send it to the shop") go to this inbox (owner, 2026-11-14)
    'inquiry_email' => env('UDS_INQUIRY_EMAIL', 'inquiry@universaldriveshafts.com'),
    // admin sign-in needs a code e-mailed to the admin (AdminTwoFactor); off only in the test suite
    'admin_2fa' => (bool) env('ADMIN_2FA', true),
    'recaptcha_site' => env('RECAPTCHA_SITE', ''),
    'recaptcha_secret' => env('RECAPTCHA_SECRET', ''),

    'gmaps_key' => env('GOOGLE_MAPS_KEY', ''),
    'google_client_id' => env('GOOGLE_CLIENT_ID', ''),
 'phone_oem' => '+1 (813) 644-5707',
 'phone_parts' => '+1 (813) 524-6491',
 'email' => 'inquiry@universaldriveshafts.com',
 'instagram' => 'https://www.instagram.com/universaldriveshafts',
 'facebook' => 'https://www.facebook.com/universaldriveshafts',
 'tiktok' => 'https://www.tiktok.com/@uds247',
 'categories' => [
  ['Universal Joints','u-joints',179,'/uploads/wp/2025/02/Diseno-sin-titulo-40.png'],
  ['Center Support Bearing','center-support-bearing',324,'/uploads/wp/2025/03/Diseno-sin-titulo-9.png'],
  ['Flange Yokes','flange-yokes',243,'/uploads/wp/2025/10/Diseno-sin-titulo-3.webp'],
  ['Weld Yokes','weld-yoke',192,'/uploads/wp/2025/03/Diseno-sin-titulo-2.png'],
  ['Stub & Midship Shaft','stub-midship-shaft',170,'/uploads/wp/2025/03/Diseno-sin-titulo-21.png'],
  ['Transmission Yokes','transmission-yokes',168,'/uploads/wp/2025/03/Diseno-sin-titulo-1.png'],
  ['End Yokes','end-yokes',147,'/uploads/wp/2025/03/Diseno-sin-titulo-12.png'],
  ['Slip Yoke','slip-yoke',101,'/uploads/wp/2025/03/Diseno-sin-titulo-15.png'],
  ['Rubber Flex Disc','rubber-flex-disc',92,'/uploads/wp/2025/03/Diseno-sin-titulo-4.png'],
  ['Premade Driveshafts','premade-driveshafts',61,'/uploads/wp/2025/01/Untitled-design-18-2.png'],
  ['Tubing','tubing',55,'/uploads/wp/2025/03/Diseno-sin-titulo-13.png'],
  ['Yoke Shafts','yoke-shafts',54,'/uploads/wp/2025/03/Diseno-sin-titulo-3.png'],
  ['PTO End Yokes','pto-end-yokes',47,'/uploads/wp/2025/03/Diseno-sin-titulo-5.png'],
  ['Balance Weights','balance-weights',46,'/uploads/wp/2025/02/Diseno-sin-titulo-41.png'],
  ['CV Flange Yokes','cv-flange-yokes',43,'/uploads/wp/2025/03/Diseno-sin-titulo-7.png'],
  ['Tri & Quad Prongs','tri-quad-prongs',40,'/uploads/wp/2025/01/432324234ed-1-600x404-1-150x150-1.jpg'],
  ['Increase Bushings','increase-bushings',33,'/uploads/wp/2025/02/Diseno-sin-titulo-2025-02-10T053410.601.png'],
  ['Strap Kits','strap-kits',31,'/uploads/wp/2025/02/Diseno-sin-titulo-2025-02-10T053742.635.png'],
  ['Short Couple Driveshafts','short-couple-driveshafts',31,'/uploads/wp/2022/12/Take-off-driveshaft-1.png'],
  ['Splined Sleeves & Slip Assemblies','splined-sleeves',30,'/uploads/wp/2025/10/Diseno-sin-titulo-2.webp'],
  ['CV Joint Kits','cv-joint-kit',29,'/uploads/wp/2025/03/Diseno-sin-titulo-20.png'],
  ['Companion Flanges','companion-flanges',25,'/uploads/wp/2025/10/MDS27-746-4.webp'],
  ['CV Double Joint Heads','cv-double-joints-heads',25,'/uploads/wp/2025/03/Diseno-sin-titulo-10.png'],
  ['Ball Stud Yokes','ball-stud-yokes',22,'/uploads/wp/2025/03/Diseno-sin-titulo-8.png'],
  ['Nuts','nuts',20,'/uploads/wp/2025/02/Diseno-sin-titulo-2025-02-10T053650.067.png'],
  ['Washers & Snap Rings','washers-snap-rings',18,'/uploads/wp/2025/02/Diseno-sin-titulo-2025-02-10T054013.194.png'],
  ['All Small Parts','small-parts',14,'/uploads/wp/2025/02/Diseno-sin-titulo-41.png'],
  ['CV Ball Kits','cv-ball-kit',12,'/uploads/wp/2025/02/Diseno-sin-titulo-2025-02-10T053246.670.png'],
  ['Certified Driveshafts','certifed-driveshafts',12,'/uploads/wp/2026/04/take-off-spl170.png'],
  ['Nose Bushing','nose-bushings',10,'/uploads/wp/2025/02/Diseno-sin-titulo-2025-02-10T053506.887.png'],
  ['Dust Cap Press On & Threaded','dust-cap-press-on-and-threaded',10,'/uploads/wp/2025/05/Diseno-sin-titulo-53.png'],
  ['U Bolt Kits','u-bolt-kits',9,'/uploads/wp/2025/02/Diseno-sin-titulo-2025-02-10T053905.605.png'],
  ['Lube Zerk Fittings','lube-zerk-fittings',8,'/uploads/wp/2025/02/small-parts-4.png'],
  ['H Yokes','h-yokes',7,'/uploads/wp/2025/03/Diseno-sin-titulo-17.png'],
  ['CV Link Yokes','cv-link-yoks',6,'/uploads/wp/2025/03/Diseno-sin-titulo-18.png'],
  ['High Performance Flange Yokes','high-performance-flange-yokes',6,'/uploads/wp/2025/04/Diseno-sin-titulo-37.png'],
  ['Hardware','hardware',3,'/uploads/wp/2025/02/Diseno-sin-titulo-39.png'],
  ['Keyway','keyway',2,'/uploads/wp/2026/02/2-3-9991X-4.webp'],
  ['Unwelded Assemblies','unwelded-assemblies',2,'/uploads/wp/2026/05/unwelded-assemblies.gif'],
 ],
 'bestsellers' => [
  ['5-1310XU Universal Joint 1310 Series HD','5-1310x-universal-joint-1310',11.50,'/uploads/wp/2025/01/Diseno-sin-titulo-74.png'],
  ['3-1448XU Conversion U-Joint 1480→1410','3-1448x-universal-joint-1410x1480',17.49,'/uploads/wp/2026/03/3-1448_1_final-1.png'],
  ['5-3000X Universal Joint 3C HD','5-3000x-universal-joint-3-c',29.73,'/uploads/wp/2025/01/image-XNZHFzSyN-transformed-1.jpeg'],
  ['3-2-2209 1350 Toyota Flange U-Joint','3-2-2209-flange-yoke-1350',46.93,'/uploads/wp/2025/01/Diseno-sin-titulo-63.png'],
  ['3133-311 Splined Slip Assembly','3133-311-splined-slip-assembly',53.30,'/uploads/wp/2025/01/image-5lc-I6qhi-transformed-1.jpeg'],
  ['2526-960 Tri & Quad Prong','2526-960-tri-prong-driveshaft',62.03,'/uploads/wp/2026/04/2526-960_img1.jpg'],
  ['2726-258 Tri & Quad Prong','2726-258-tri-prong-driveshaft',72.66,'/uploads/wp/2025/01/uf.jpeg'],
  ['3182-311 Yoke Shaft 1310 Series','3182-311-yoke-shaft-1310',74.53,'/uploads/wp/2025/01/zt.jpeg'],
 ],
 'free_shipping_min' => 1000,

 // Default Schedule B / HS heading for customs declarations: parts of motor vehicles.
 'customs_hs_code' => '870899',

 // What the shop will not ship, in one place: both the checkout quote and the admin
 // label modal read it, so a carrier or service is never offered in one and not the other.
 'shipping_exclude' => [
   'carriers' => ['usps', 'dhl_ecs', 'canada_post', 'usa_export_pba'],
   // matched case-insensitively against the carrier's service code
   'services' => ['groundsaver'],
 ],
 // CAN-SPAM: marketing e-mails (cart reminders, newsletter) must carry a real postal address. The owner chose
// the shop's address for these only (2026-11-01); everywhere else the public location stays "Florida".
'postal_address' => env('UDS_POSTAL_ADDRESS', 'Universal Driveshafts LLC, 11611 Prospect Drive STE 4, Odessa, FL 33556'),

'pickup' => [
   'label' => 'Local pickup (Mon to Fri, 3 to 5 pm)',   // no city: the address only shows once the customer picks pickup
   'address' => '11611 Prospect Drive STE 4, Odessa FL 33556',
   'hours' => 'Monday to Friday, 3:00 to 5:00 pm',
   'phone' => '(813) 524-6491',
   'phone_href' => 'tel:+18135246491',
   'maps' => 'https://maps.google.com/?q=11611+Prospect+Drive+STE+4+Odessa+FL+33556',
 ],
 'stores' => [
   ['name'=>'Odessa','sub'=>'OEM Driveshafts and Parts','addr'=>'11611 Prospect Drive STE 4, Odessa FL 33556','phone'=>'(813) 524-6491','hours'=>'Mon–Fri 8:00am–5:00pm · Sat 9:00am–2:00pm','maps'=>'https://maps.google.com/?q=11611+Prospect+Drive+STE+4+Odessa+FL+33556','soon'=>false],
   ['name'=>'Tampa','sub'=>'WorkShop, Production Facility, Customized Driveshafts','addr'=>'Coming Soon','phone'=>'(813) 644-5707','hours'=>'Mon–Fri 8:00am–5:00pm · Sat 9:00am–2:00pm','maps'=>'','soon'=>true],
 ],
    // low-stock / weekly reports; defaults to the shop inbox (same as 'email' above), never a personal address
    'inventory_alert_email' => env('INVENTORY_ALERT_EMAIL', 'inquiry@universaldriveshafts.com'),
    // abandoned-cart reminder text: off until checkout collects marketing-SMS consent (TCPA); the text carries "Reply STOP"
    'cart_reminder_sms' => env('CART_REMINDER_SMS', false),
    'show_locations' => env('SHOW_LOCATIONS', false),
];
