@php
  /* Sitewide structured data. Kept in one place so the graph stays consistent
     and there is exactly one Organization node for the whole site. Pages push
     their own nodes (Product, BreadcrumbList, FAQPage…) through @stack('schema'). */
  $base   = rtrim(config('app.url'), '/');
  $stores = config('uds.stores');
  $logo   = $base . '/img/uds-logo.webp';
  $social = array_values(array_filter([config('uds.instagram'), config('uds.facebook'), config('uds.tiktok')]));
  $e164   = fn (string $p) => '+1' . substr(preg_replace('/[^0-9]/', '', $p), -10);

  // the shop's address is public for local search (owner, 2026-11-04: "la correcta es la de Odessa"; was state only before)
  $addr = ['@type' => 'PostalAddress', 'streetAddress' => '11611 Prospect Drive STE 4', 'addressLocality' => 'Odessa', 'addressRegion' => 'FL', 'postalCode' => '33556', 'addressCountry' => 'US'];
  $hours = [
    ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday'], 'opens' => '08:00', 'closes' => '17:00'],
    ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Saturday'], 'opens' => '09:00', 'closes' => '14:00'],
  ];
  $mainPhone = config('uds.phone_parts', '(813) 524-6491');
  $oemPhone  = config('uds.phone_oem', '(813) 644-5707');

  $org = [
    '@type'       => 'Organization',
    '@id'         => $base . '/#organization',
    'name'        => 'Universal Driveshafts',
    'alternateName' => 'UDS',
    // store-wide merchant policies (Google reads these from the Organization node for every product)
    'hasMerchantReturnPolicy' => [
      '@type' => 'MerchantReturnPolicy', 'applicableCountry' => 'US', 'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
      'merchantReturnDays' => 14, 'returnMethod' => 'https://schema.org/ReturnByMail', 'returnFees' => 'https://schema.org/RestockingFees', 'restockingFee' => 20, // /return-policy: 14 days, 20% restocking
      'refundType' => 'https://schema.org/FullRefund', 'merchantReturnLink' => $base . '/return-policy',
    ],
    'shippingDetails' => [
      '@type' => 'OfferShippingDetails', 'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'US'],
      'deliveryTime' => ['@type' => 'ShippingDeliveryTime',
        'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 0, 'maxValue' => 1, 'unitCode' => 'DAY'],
        'transitTime' => ['@type' => 'QuantitativeValue', 'minValue' => 1, 'maxValue' => 5, 'unitCode' => 'DAY']],
    ],
    'legalName'   => 'Universal Driveshafts LLC',
    'url'         => $base . '/',
    'logo'        => ['@type' => 'ImageObject', '@id' => $base . '/#logo', 'url' => $logo, 'contentUrl' => $logo, 'width' => 781, 'height' => 125, 'caption' => 'Universal Driveshafts'],
    'image'       => ['@id' => $base . '/#logo'],
    'email'       => config('uds.email'),
    'telephone'   => $e164($mainPhone),
    'address'     => $addr,
    'areaServed'  => ['@type' => 'Country', 'name' => 'US'],
    'foundingDate'=> '2018',
    // what the business is an authority on: helps search and AI answers tie the brand to these topics
    'knowsAbout'  => ['Driveshafts', 'Universal joints (u-joints)', 'Driveshaft yokes', 'Center support bearings', 'CV joints', 'Driveshaft balancing', 'Custom driveshafts', 'Driveshaft repair', 'Industrial driveshafts'],
    'description' => 'Driveshaft parts and complete driveshafts: u-joints, yokes, center support bearings, flanges and tubing, plus custom driveshafts machined, welded and balanced in Florida.',
    'sameAs'      => $social,
    'contactPoint' => [
      ['@type' => 'ContactPoint', 'telephone' => $e164($mainPhone), 'contactType' => 'sales', 'areaServed' => 'US', 'availableLanguage' => ['English']],
      ['@type' => 'ContactPoint', 'telephone' => $e164($oemPhone), 'contactType' => 'customer service', 'areaServed' => 'US', 'availableLanguage' => ['English']],
    ],
  ];

  $website = [
    '@type' => 'WebSite',
    '@id'   => $base . '/#website',
    'url'   => $base . '/',
    'name'  => 'Universal Driveshafts',
    'inLanguage' => 'en-US',
    'publisher' => ['@id' => $base . '/#organization'],
    'potentialAction' => [
      '@type'       => 'SearchAction',
      'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => $base . '/search?q={search_term_string}'],
      'query-input' => 'required name=search_term_string',
    ],
  ];

  // the shop itself, for Google Maps / local results ("driveshaft repair near me"): same name, address and phone as the
  // Google Business Profile, opening hours of the counter
  $shop = [
    '@type' => ['AutoPartsStore', 'AutoRepair'],
    '@id' => $base . '/#shop',
    'name' => 'Universal Driveshafts',
    'url' => $base . '/',
    'image' => $logo,
    'logo' => ['@id' => $base . '/#logo'],
    'telephone' => $e164($mainPhone),
    'email' => config('uds.email'),
    'address' => $addr,
    'hasMap' => config('uds.pickup.maps'),
    'openingHoursSpecification' => $hours,
    'priceRange' => '$$',
    'areaServed' => [['@type' => 'City', 'name' => 'Tampa'], ['@type' => 'AdministrativeArea', 'name' => 'Tampa Bay'], ['@type' => 'Country', 'name' => 'US']],
    'parentOrganization' => ['@id' => $base . '/#organization'],
    'sameAs' => $social,
  ];

  $graph = [$org, $website, $shop];
@endphp
<script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
