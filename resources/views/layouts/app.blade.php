<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
  $udsTitle = trim($__env->yieldContent('title', 'Universal Driveshafts'));
  $udsDesc  = trim($__env->yieldContent('desc', __('Driveshaft components and complete driveshafts, u-joints, yokes, center support bearings, flanges and tubing. Built in the USA, shipped fast.')));
  // Only the production host may be indexed. Every other environment stays out, whatever a page asks for.
  $udsProd   = app()->environment('production');
  $udsRobots = $udsProd
      ? (View::hasSection('robots') ? trim(View::getSection('robots')) : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1')
      : 'noindex,nofollow';
  // canonical is always built on APP_URL (never the request host) so www./IP/index.php variants point at the one URL
  $udsBase      = rtrim(config('app.url'), '/');
  $udsCanonical = View::hasSection('canonical')
      ? (preg_match('#^https?://#', trim(View::getSection('canonical'))) ? trim(View::getSection('canonical')) : $udsBase . '/' . ltrim(trim(View::getSection('canonical')), '/'))
      : $udsBase . (request()->getPathInfo() === '/' ? '/' : rtrim(request()->getPathInfo(), '/'));
  $udsOgImage = View::hasSection('ogimage') ? trim(View::getSection('ogimage')) : $udsBase . '/img/og-default.jpg';
  if ($udsOgImage !== '' && $udsOgImage[0] === '/') $udsOgImage = $udsBase . $udsOgImage;
  $udsOgDefault = $udsOgImage === $udsBase . '/img/og-default.jpg';
@endphp
<title>{!! $udsTitle !!}</title>
<meta name="description" content="{!! $udsDesc !!}">
<meta name="robots" content="{{ $udsRobots }}">
<link rel="canonical" href="{{ $udsCanonical }}">
<meta name="theme-color" content="#0b1a33">
<meta property="og:site_name" content="Universal Driveshafts">
<meta property="og:title" content="{!! $udsTitle !!}">
<meta property="og:description" content="{!! $udsDesc !!}">
<meta property="og:type" content="@yield('ogtype','website')">
<meta property="og:url" content="{{ $udsCanonical }}">
<meta property="og:image" content="{{ $udsOgImage }}">
@if($udsOgDefault)<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Universal Driveshafts: driveshaft parts and complete driveshafts, Florida">
@endif<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{!! $udsTitle !!}">
<meta name="twitter:description" content="{!! $udsDesc !!}">
<meta name="twitter:image" content="{{ $udsOgImage }}">
<link rel="icon" href="/favicon.ico?v=2" sizes="48x48">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
<link rel="icon" type="image/png" href="{{ asset('favicon-32.png') }}?v=2" sizes="32x32">
<link rel="icon" type="image/png" href="{{ asset('icon-192.png') }}?v=2" sizes="192x192">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2" sizes="180x180">
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/poppins-700.woff2') }}" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/hankengrotesk-var.woff2') }}" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/poppins-600.woff2') }}" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/ibmplexmono-600.woff2') }}" crossorigin>
<link rel="stylesheet" href="{{ asset('css/uds.css') }}?v={{ @filemtime(public_path('css/uds.css')) }}">
<link rel="stylesheet" href="{{ asset('css/uds-2026.css') }}?v={{ @filemtime(public_path('css/uds-2026.css')) }}">
<script>document.documentElement.classList.add('js')</script>
@stack('head')
@include('partials.schema')
@stack('schema')
@include('partials.analytics')
<script>window.UDS_T=@json((object) \App\Support\JsStrings::dict());window.udsT=function(s,r){var o=(window.UDS_T&&window.UDS_T[s])||s;if(r)for(var k in r)o=o.split(':'+k).join(r[k]);return o;};</script>
</head>
@php
  // Floating WhatsApp / call buttons: never over the cart, checkout or sign-in forms (they covered the CTAs on phones).
  $udsFloaty = !request()->is('cart', 'cart/*', 'checkout', 'checkout/*', 'login', 'register', 'password/*', 'forgot-password', 'reset-password*');
  $udsAssistant = auth()->check() && app(\App\Services\Chatbot\ChatbotService::class)->enabled();
@endphp
<body class="@yield('bodyclass')@if($udsFloaty) has-floaty @endif @if($udsAssistant) has-ua @endif">
<a class="skip" href="#main">{!! __('Skip to content') !!}</a>

@php
  $stores = config('uds.stores');
  $isAdmin = auth()->check() && auth()->user()->is_admin;
  // $udsWished (product ids on the wishlist) comes from the global view
  // composer in AppServiceProvider — shared there rather than here because a
  // @extends child view renders before this layout, so a var defined only in
  // this file would not exist yet when the PDP / catalog table use it.
  $wishCount = count($udsWished ?? []);
  $cartCount = app(\App\Services\CartService::class)->count();
  // vehicle-finder totals, same cached numbers as the home page (makes = the finder's make list, shafts = sellable part numbers)
  $udsFinder = $udsFinder ?? \Illuminate\Support\Facades\Cache::remember('uds_finder_totals', 3600, function () {
      try {
          return ['makes' => count(app(\App\Services\DriveshaftFinder::class)->makes()),
                  'shafts' => \App\Services\DriveshaftFinder::fitments()->distinct()->count('ds_fitments.part_number')];
      } catch (\Throwable $e) { return ['makes' => 43, 'shafts' => 4400]; }
  });
@endphp

@if($ann = setting('storefront.announcement'))<div class="uannounce">{{ $ann }}</div>@endif
@if(request()->is('cart','checkout') && ($cn = setting('storefront.holiday_notice')))<div class="uannounce uannounce--warn">{{ $cn }}</div>@endif
<div class="upromo" @if(!setting('promo.enabled', true)) hidden @endif>
  <div class="wrap">
    <p class="upromo__msg"><x-icon name="truck"/> @if($pl = setting('promo.link'))<a href="{{ $pl }}">@endif<span @class(['upromo__long' => !setting('promo.text')])>{{ __(setting('promo.text') ?: 'U.S. free shipping on orders over $'.number_format(config('uds.free_shipping_min')).' (excluding tubes and driveshafts)') }}</span>@if($pl)</a>@endif
      @if(!setting('promo.text'))<a class="upromo__short" href="{{ $pl ?: '/shipping-policy' }}">{{ __('Free U.S. shipping on parts over $:min', ['min' => number_format(config('uds.free_shipping_min'))]) }}</a>@endif</p>
    <div class="upromo__meta">
      @if(config('uds.show_locations'))
      <div class="upromo__stores">
        <button type="button" class="upromo__storebtn" aria-expanded="false"><x-icon name="pin"/> {!! __('Our locations') !!} <x-icon name="caret-down"/></button>
        <div class="upromo__storemenu">
          @foreach ($stores as $s)
            <div class="ustore">
              <b>{{ $s['name'] }}, FL</b>
              <span>{{ $s['sub'] }}</span>
              <span class="ustore__addr">{{ $s['addr'] }}</span>
              <span class="ustore__hrs">{!! __('Mon to Fri 8:00am to 5:00pm &middot; Sat 9:00am to 2:00pm') !!}</span>
              <a href="tel:{{ preg_replace('/[^0-9]/','',$s['phone']) }}" class="ustore__tel">{{ $s['phone'] }}</a>
              @if(!$s['soon'])<a class="ustore__map" href="{{ $s['maps'] }}" target="_blank" rel="noopener">{!! __('Open in Maps') !!} <x-icon name="arrow-up-right"/></a>@endif
            </div>
          @endforeach
        </div>
      </div>
      @endif
      <a href="/track-order">{!! __('Track order &amp; returns') !!}</a>
      <a href="/wholesale">{!! __('Wholesale') !!}</a>
      @include('partials.lang-switch')
    </div>
  </div>
</div>

<header class="uhead" id="uhead">
  <div class="wrap">
    <a class="uhead__logo" href="/" aria-label="{!! __('Universal Driveshafts home') !!}">
      <img src="{{ asset('img/uds-logo.webp') }}" alt="{!! __('Universal Driveshafts') !!}" width="237" height="38">
    </a>

    <nav class="umenu" aria-label="{!! __('Primary') !!}">
      <div class="umenu__item has-menu">
        <a class="umenu__link" href="/shop" data-match="/shop,/parts,/product,/product-category">{!! __('Catalog') !!} <x-icon name="caret-down"/></a>
        <div class="umega umega--shop">
          <div class="umega__col">
            <p class="umega__title">{!! __('Shop by group') !!}</p>
            <a href="/shop/complete-driveshafts"><b>{!! __('Complete driveshafts') !!}</b><span>{!! __('Assembled and balanced') !!}</span></a>
            <a href="/shop/driveshaft-components"><b>{!! __('Driveshaft components') !!}</b><span>{!! __('U-joints, yokes, bearings') !!}</span></a>
            <a href="/shop/custom-cut-tubing"><b>{!! __('Custom cut tubing') !!}</b><span>{!! __('Steel and aluminium, to length') !!}</span></a>
            <a href="/shop/small-parts"><b>{!! __('Small parts') !!}</b><span>{!! __('Straps, bolts, snap rings') !!}</span></a>
          </div>
          <div class="umega__col">
            <p class="umega__title">{!! __('Popular categories') !!}</p>
            <a href="/shop/driveshaft-components/u-joints"><b>{!! __('Universal joints') !!}</b><span>{!! __('179 parts') !!}</span></a>
            <a href="/shop/driveshaft-components/flange-yokes"><b>{!! __('Flange yokes') !!}</b><span>{!! __('243 parts') !!}</span></a>
            <a href="/shop/driveshaft-components/center-support-bearing"><b>{!! __('Center support bearings') !!}</b><span>{!! __('324 parts') !!}</span></a>
          </div>
          <a class="umega__feat" href="/shop">
            <img src="{{ asset('img/photos/steel-tube.webp') }}" alt="" loading="lazy">
            <b>{!! __('The full catalog') !!}</b>
            <span>{{ __(':parts parts across :cats categories', ['parts' => number_format($udsCatalog['parts']), 'cats' => $udsCatalog['cats']]) }}</span>
            <x-icon name="arrow-right"/>
          </a>
        </div>
      </div>

      <div class="umenu__item has-menu">
        <a class="umenu__link" href="/online-builder" data-match="/online-builder,/driveshaft-designer,/catalog/driveshafts,/driveshafts,/custom-off-road-driveshafts,/industrial-driveshafts,/heavy-duty-driveshafts,/pto-agriculture,/premade-driveshafts">{!! __('Driveshafts') !!} <x-icon name="caret-down"/></a>
        <div class="umega umega--shop">
          <div class="umega__col">
            <p class="umega__title">{!! __('Built to order') !!}</p>
            <a href="/online-builder"><b>{!! __('Online driveshaft builder') !!}</b><span>{!! __('Spec it and see the price') !!}</span></a>
            <a href="/driveshaft-designer"><b>{!! __('Driveshaft designer') !!}</b><span>{!! __('Draw it, add measurements, print') !!}</span></a>
            <a href="/custom-off-road-driveshafts"><b>{!! __('Off-road &amp; performance') !!}</b><span>{!! __('High angle, high torque') !!}</span></a>
            <a href="/heavy-duty-driveshafts"><b>{!! __('Heavy duty') !!}</b><span>{!! __('Class 6 to 8, vocational') !!}</span></a>
          </div>
          <div class="umega__col">
            <p class="umega__title">{!! __('Ready to ship') !!}</p>
            <a href="/catalog/driveshafts"><b>{!! __('Find by vehicle') !!}</b><span>{{ __('Year, make and model: :n driveshafts', ['n' => number_format($udsFinder['shafts'])]) }}</span></a>
            <a href="/driveshafts"><b>{!! __('Driveshafts by make &amp; model') !!}</b><span>{!! __('Browse every fitment we stock') !!}</span></a>
            <a href="/premade-driveshafts"><b>{!! __('Premade driveshafts') !!}</b><span>{!! __('Common fitments in stock') !!}</span></a>
            <a href="/industrial-driveshafts"><b>{!! __('Industrial') !!}</b><span>{!! __('Pumps, compressors, drives') !!}</span></a>
            <a href="/pto-agriculture"><b>{!! __('PTO &amp; agriculture') !!}</b><span>{!! __('Tractor and implement') !!}</span></a>
          </div>
          <a class="umega__feat" href="/online-builder">
            <img src="{{ asset('img/photos/tile-complete-driveshafts.webp') }}" alt="" loading="lazy" width="1000" height="750">
            <b>{!! __('Build one to spec') !!}</b>
            <span>{!! __('Six measurements and a live price') !!}</span>
            <x-icon name="arrow-right"/>
          </a>
        </div>
      </div>

      <a class="umenu__link" href="/services" data-match="/services">{!! __('Services') !!}</a>
      <a class="umenu__link" href="/about-us" data-match="/about,/about-us">{!! __('About') !!}</a>
    </nav>

    <div class="uhead__actions">
      <button type="button" class="iconbtn" data-open-search aria-label="{!! __('Search parts') !!}" title="{!! __('Search parts') !!}">
        <x-icon name="search"/>
      </button>
      <a class="iconbtn iconbtn--desk" href="{{ auth()->check() ? ($isAdmin ? '/admin' : '/account') : '/login' }}" aria-label="{{ auth()->check() ? __('My account') : __('Sign in') }}" title="{{ auth()->check() ? __('My account') : __('Sign in') }}">
        <x-icon name="user"/>
      </a>
      <a class="iconbtn iconbtn--desk" href="/wishlist" aria-label="{!! __('Wishlist') !!}" title="{!! __('Wishlist') !!}">
        <x-icon name="heart"/><span class="dot" data-wish-count @if($wishCount==0) hidden @endif>{{ $wishCount }}</span>
      </a>
      <a class="iconbtn iconbtn--desk iconbtn--cart" href="/cart" aria-label="{!! __('Cart') !!}" title="{!! __('Cart') !!}">
        <x-icon name="cart"/><span class="dot" data-cart-count @if($cartCount==0) hidden @endif>{{ $cartCount }}</span>
      </a>
      <a class="btn btn-primary btn-sm uhead__cta" href="/online-builder">{!! __('Online builder') !!}</a>
      <button type="button" class="uburger" id="uburger" aria-label="{!! __('Open menu') !!}" aria-expanded="false" aria-controls="udrawer"><x-icon name="menu"/></button>
    </div>
  </div>
</header>

<div class="udrawer" id="udrawer" aria-hidden="true">
  <div class="udrawer__scrim"></div>
  <nav class="udrawer__panel" aria-label="{!! __('Mobile menu') !!}">
    <div class="udrawer__top">
      <img src="{{ asset('img/uds-logo.webp') }}" alt="{!! __('Universal Driveshafts') !!}" width="187" height="30">
      <button type="button" class="udrawer__close" data-drawer-close aria-label="{!! __('Close menu') !!}"><x-icon name="x"/></button>
    </div>
    <form class="search usearch" action="/search" method="get" role="search">
      <x-icon name="search"/>
      <input type="search" name="q" placeholder="{!! __('Part number, e.g. 5-1310XU') !!}" aria-label="{!! __('Search parts') !!}">
      <button type="submit" aria-label="{!! __('Search') !!}">{!! __('Search') !!}</button>
    </form>
    <a href="/online-builder" class="udrawer__cta"><x-icon name="wrench"/> {!! __('Online driveshaft builder') !!}</a>
    <a href="/shop" class="udrawer__link"><x-icon name="package"/> {!! __('Parts catalog') !!}</a>
    <a href="/catalog/driveshafts" class="udrawer__link"><x-icon name="crosshair"/> {!! __('Find driveshaft by vehicle') !!}</a>
    <details class="md-group">
      <summary>{!! __('Driveshafts') !!}</summary>
      <a href="/online-builder">{!! __('Online driveshaft builder') !!}</a>
      <a href="/driveshaft-designer">{!! __('Driveshaft designer') !!}</a>
      <a href="/catalog/driveshafts">{!! __('Find by vehicle') !!}</a>
      <a href="/driveshafts">{!! __('Driveshafts by make &amp; model') !!}</a>
      <a href="/premade-driveshafts">{!! __('Premade driveshafts') !!}</a>
      <a href="/custom-off-road-driveshafts">{!! __('Off-road &amp; performance') !!}</a>
      <a href="/heavy-duty-driveshafts">{!! __('Heavy duty') !!}</a>
      <a href="/industrial-driveshafts">{!! __('Industrial') !!}</a>
      <a href="/pto-agriculture">{!! __('PTO &amp; agriculture') !!}</a>
    </details>
    <details class="md-group">
      <summary>{!! __('Browse by category') !!}</summary>
      <a href="/shop">{!! __('Full catalog') !!}</a>
      <a href="/shop/complete-driveshafts">{!! __('Complete driveshafts') !!}</a>
      <a href="/shop/driveshaft-components">{!! __('Driveshaft components') !!}</a>
      <a href="/shop/custom-cut-tubing">{!! __('Custom cut tubing') !!}</a>
      <a href="/shop/small-parts">{!! __('Small parts') !!}</a>
      <a href="/quick-order">{!! __('Quick order') !!}</a>
    </details>
    <details class="md-group">
      <summary>{!! __('Resources') !!}</summary>
      <a href="/driveshaft-designer">{!! __('Driveshaft designer') !!}</a>
      <a href="/services">{!! __('Services') !!}</a>
    </details>
    @if(config('uds.show_locations'))
    <details class="md-group">
      <summary>{!! __('Our locations') !!}</summary>
      @foreach ($stores as $s)
        <div class="udrawer__store">
          <b>{{ $s['name'] }}, FL</b>
          <span>{{ $s['sub'] }}</span>
          <span>{{ $s['addr'] }}</span>
          <a href="tel:{{ preg_replace('/[^0-9]/','',$s['phone']) }}">{{ $s['phone'] }}</a>
        </div>
      @endforeach
    </details>
    @endif
    <a href="/services" class="udrawer__link"><x-icon name="gauge"/> {!! __('Services') !!}</a>
    <a href="/about-us" class="udrawer__link"><x-icon name="factory"/> {!! __('About us') !!}</a>
    <a href="/track-order" class="udrawer__link"><x-icon name="package"/> {!! __('Track order &amp; returns') !!}</a>
    <div class="udrawer__lang">@include('partials.lang-switch', ['class' => 'langsw--drawer'])</div>
    <a href="/wholesale" class="udrawer__link"><x-icon name="certificate"/> {!! __('Wholesale') !!}</a>
    <div class="udrawer__acct">
      <a href="{{ auth()->check() ? ($isAdmin ? '/admin' : '/account') : '/login' }}">
        <x-icon name="user"/> <span>{{ auth()->check() ? __('My account') : __('Sign in') }}</span>
      </a>
      <a href="/wishlist">
        <x-icon name="heart"/> <span>{!! __('Wishlist') !!}</span>
        @if($wishCount)<b>{{ $wishCount }}</b>@endif
      </a>
      <a href="/cart" data-open-cart>
        <x-icon name="cart"/> <span>{!! __('Cart') !!}</span>
        <b data-cart-count @if(!$cartCount) hidden @endif>{{ $cartCount }}</b>
      </a>
      @auth<form method="POST" action="/logout" style="margin:0">@csrf<button type="submit" style="display:flex;align-items:center;gap:.75rem;width:100%;padding:.85rem 1rem;border:1px solid var(--line);border-radius:var(--r-sm);background:var(--paper-2);color:var(--ink);font-family:var(--font-h);font-weight:600;font-size:.94rem;text-align:left;cursor:pointer"><x-icon name="arrow-right"/> <span>{{ __('Sign out') }}</span></button></form>@endauth
    </div>
  </nav>
</div>

<main id="main">
@yield('content')
</main>

<footer class="ufoot">
  <div class="wrap">
    <div class="ufoot__grid">
      <div class="ufoot__brand">
        <img src="{{ asset('img/uds-logo.webp') }}" alt="{!! __('Universal Driveshafts') !!}" width="187" height="30">
        <p>{!! __('Driveshaft components and complete driveshafts. U-joints, yokes, center support bearings, flange yokes, tubing and finished assemblies, built and balanced in the USA.') !!}</p>
        <div class="ufoot__contact">
          <a href="tel:18135246491"><x-icon name="phone"/> {!! __('(813) 524-6491') !!}</a>
          <a href="tel:18136445707"><x-icon name="phone"/> {!! __('(813) 644-5707 &nbsp;OEM &amp; fleet') !!}</a>
          <a href="mailto:{{ config('uds.email') }}"><x-icon name="mail"/> {{ config('uds.email') }}</a>
        </div>
      </div>
      <div>
        <h2 class="ufoot__h">{!! __('Shop') !!}</h2>
        <ul>
          <li><a href="/shop">{!! __('All parts') !!}</a></li>
          <li><a href="/shop/driveshaft-components/u-joints">{!! __('Universal joints') !!}</a></li>
          <li><a href="/shop/driveshaft-components/flange-yokes">{!! __('Flange yokes') !!}</a></li>
          <li><a href="/catalog/driveshafts">{!! __('Driveshafts by vehicle') !!}</a></li>
          <li><a href="/driveshafts">{!! __('Driveshafts by make &amp; model') !!}</a></li>
          <li><a href="/premade-driveshafts">{!! __('Premade driveshafts') !!}</a></li>
          <li><a href="/quick-order">{!! __('Quick order') !!}</a></li>
        </ul>
      </div>
      <div>
        <h2 class="ufoot__h">{!! __('Resources') !!}</h2>
        <ul>
          <li><a href="/driveshaft-designer">{!! __('Driveshaft designer') !!}</a></li>
          <li><a href="/heavy-duty-driveshafts">{!! __('Heavy duty driveshafts') !!}</a></li>
          <li><a href="/custom-off-road-driveshafts">{!! __('Off-road driveshafts') !!}</a></li>
          <li><a href="/wholesale">{!! __('Wholesale accounts') !!}</a></li>
        </ul>
      </div>
      <div>
        <h2 class="ufoot__h">{!! __('Order') !!}</h2>
        <ul>
          <li><a href="/online-builder">{!! __('Online driveshaft builder') !!}</a></li>
          <li><a href="/online-builder/two-piece">{!! __('Two-piece builder') !!}</a></li>
          <li><a href="/driveshaft-designer">{!! __('Driveshaft designer') !!}</a></li>
          <li><a href="/quick-order">{!! __('Quick order by part number') !!}</a></li>
          <li><a href="/track-order">{!! __('Track an order') !!}</a></li>
          <li><a href="/rma">{!! __('Returns &amp; RMA') !!}</a></li>
        </ul>
      </div>
      <div>
        <h2 class="ufoot__h">{!! __('Company') !!}</h2>
        <ul>
          <li><a href="/about-us">{!! __('About us') !!}</a></li>
          <li><a href="/services">{!! __('Services') !!}</a></li>
          <li><a href="/contact">{!! __('Contact') !!}</a></li>
          <li><a href="/return-policy">{!! __('Returns &amp; warranty') !!}</a></li>
        </ul>
      </div>
    </div>

    <div class="unews">
      <div class="unews__txt"><b>{!! __('Driveline news, new parts and the occasional deal.') !!}</b><span>{!! __('A couple of e-mails a month, from the shop floor in Florida. Unsubscribe any time.') !!}</span></div>
      <form class="unews__form" method="POST" action="/newsletter" data-newsletter>@csrf<input type="text" name="website" tabindex="-1" autocomplete="off" class="unews__hp" aria-hidden="true"><label class="sr-only" for="unews-email">{!! __('E-mail') !!}</label><input id="unews-email" class="unews__in" type="email" name="email" placeholder="{!! __('you@company.com') !!}" required><button class="btn btn-primary" type="submit">{!! __('Subscribe') !!}</button></form>
      @if(session('newsletter_ok'))<p class="unews__ok">{{ session('newsletter_ok') }}</p>@endif
    </div>
    <p class="ufoot__legal">{!! __('Spicer') !!}<sup>{!! __('&reg;') !!}</sup>{!! __(', Dana') !!}<sup>{!! __('&reg;') !!}</sup>{!! __(', Neapco') !!}<sup>{!! __('&reg;') !!}</sup> {!! __('and all other manufacturer names, logos, series designations and part numbers shown on this site are trademarks of their respective owners and are used for identification and cross-reference purposes only. Universal Driveshafts LLC is an independent company and is not affiliated with, sponsored by, endorsed by or licensed by any of them. Parts sold under a UDS part number are UDS-brand replacement parts. Interchange data and images are for reference; verify fitment before ordering.') !!} <a href="/copyright">{!! __('Copyright &amp; trademark notice') !!}</a></p>

    <div class="ufoot__bar">
      <span>&copy; {{ date('Y') }} Universal Driveshafts LLC. {{ __('Built in the USA.') }}</span>
      @include('partials.lang-switch', ['class' => 'langsw--foot'])
      <nav aria-label="{!! __('Legal') !!}">
        <a href="/terms">{!! __('Terms of use') !!}</a>
        <a href="/privacy-policy">{!! __('Privacy') !!}</a>
        <a href="/return-policy">{!! __('Returns &amp; warranty') !!}</a>
        <a href="/copyright">{!! __('Copyright &amp; DMCA') !!}</a>
      </nav>
      <div class="ufoot__social">
        <a href="{{ config('uds.instagram') }}" target="_blank" rel="noopener" aria-label="{!! __('Instagram') !!}"><x-icon name="instagram"/></a>
        <a href="{{ config('uds.facebook') }}" target="_blank" rel="noopener" aria-label="{!! __('Facebook') !!}"><x-icon name="facebook"/></a>
        <a href="{{ config('uds.tiktok') }}" target="_blank" rel="noopener" aria-label="{!! __('TikTok') !!}"><x-icon name="tiktok"/></a>
      </div>
    </div>
  </div>
</footer>

@if($udsFloaty)
{{-- Desktop: two round buttons. Phones (and next to the Ask UDS launcher): one "Contact" button that opens them. --}}
<div class="floaty is-idle" data-floaty>
  <div class="floaty__menu" id="floaty-menu">
    <a class="fbtn fbtn-wa" href="https://wa.me/18136445707" target="_blank" rel="noopener" aria-label="{!! __('Chat on WhatsApp') !!}" title="{!! __('Chat on WhatsApp') !!}"><x-icon name="whatsapp"/><span class="floaty__lbl" aria-hidden="true">{!! __('WhatsApp') !!}</span></a>
    <a class="fbtn fbtn-tel" href="tel:18135246491" aria-label="{!! __('Call Universal Driveshafts') !!}" title="{!! __('Call (813) 524-6491') !!}"><x-icon name="phone"/><span class="floaty__lbl" aria-hidden="true">{!! __('(813) 524-6491') !!}</span></a>
  </div>
  <button type="button" class="fbtn floaty__toggle" aria-expanded="false" aria-controls="floaty-menu" aria-label="{{ __('Contact us') }}" title="{{ __('Contact us') }}"><x-icon name="headset"/><x-icon name="x"/></button>
</div>
<script>(function(){var f=document.querySelector('[data-floaty]');if(!f)return;var t=f.querySelector('.floaty__toggle');
 function set(o){f.classList.toggle('is-open',o);t.setAttribute('aria-expanded',o?'true':'false');}
 t.addEventListener('click',function(){set(!f.classList.contains('is-open'));if(f.classList.contains('is-open'))f.querySelector('.floaty__menu a').focus();});
 document.addEventListener('keydown',function(e){if(e.key==='Escape'&&f.classList.contains('is-open')){set(false);t.focus();}});
 document.addEventListener('click',function(e){if(!f.contains(e.target))set(false);});
 function idle(){f.classList.toggle('is-idle',window.scrollY<240&&!f.classList.contains('is-open'));}
 idle();window.addEventListener('scroll',idle,{passive:true});})();</script>
@endif

@include('partials.cart-drawer')
<script src="{{ asset('js/cart-drawer.js') }}?v={{ @filemtime(public_path('js/cart-drawer.js')) }}" defer></script>
<script src="{{ asset('js/uds.js') }}?v={{ @filemtime(public_path('js/uds.js')) }}" defer></script>
<script src="{{ asset('js/search.js') }}?v={{ @filemtime(public_path('js/search.js')) }}" defer></script>
<script src="{{ asset('js/phone.js') }}?v={{ @filemtime(public_path('js/phone.js')) }}" defer></script>
@if($udsAssistant)@include('partials.assistant')@endif
@stack('scripts')
{{-- right after switching away from English: the text on the site is machine-translated (owner, 2026-10-24) --}}
@if(app()->getLocale() !== 'en' && in_array(request()->query('lang'), \App\Http\Middleware\SetLocale::SUPPORTED, true))
<div class="aitr" role="status" data-aitr>
  <svg class="aitr__ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6"/></svg>
  <p>{!! __('This translation was made with AI and may contain errors. If something is unclear, call (813) 524-6491 or switch to English.') !!}</p>
  <button type="button" class="aitr__x" aria-label="{{ __('Close') }}" data-aitr-x>&times;</button>
</div>
<script>(function(){var t=document.querySelector('[data-aitr]');if(!t)return;var x=function(){t.classList.add('is-out');setTimeout(function(){t.remove();},250);};t.querySelector('[data-aitr-x]').addEventListener('click',x);setTimeout(x,12000);
 try{var u=new URL(location.href);u.searchParams.delete('lang');history.replaceState(null,'',u.pathname+u.search+u.hash);}catch(e){}})();</script>
@endif
</body>
</html>
