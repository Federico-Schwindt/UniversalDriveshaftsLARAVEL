<!doctype html>
<html lang="{{ \App\Http\Middleware\SetLocale::tag(app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
  /* Storefront layout (2026 redesign). Same SEO rules as layouts/app: per-URL overrides from Admin → SEO,
     only production is indexable, canonical always on APP_URL. */
  $udsTitle = trim($__env->yieldContent('title', 'Universal Driveshafts'));
  $udsDesc  = trim($__env->yieldContent('desc', __('Driveshaft components and complete driveshafts, u-joints, yokes, center support bearings, flanges and tubing. Built in the USA, shipped fast.')));
  $udsOwn = app()->getLocale() === 'en' && !request()->getQueryString() ? \App\Support\Seo::page(request()->getPathInfo()) : null;
  if (!empty($udsOwn['title'])) $udsTitle = e($udsOwn['title']);
  if (!empty($udsOwn['description'])) $udsDesc = e($udsOwn['description']);
  $udsProd   = app()->environment('production');
  $udsRobots = $udsProd
      ? (!empty($udsOwn['robots']) ? $udsOwn['robots'] : (View::hasSection('robots') ? trim(View::getSection('robots')) : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'))
      : 'noindex,nofollow';
  $udsBase      = rtrim(config('app.url'), '/');
  $udsCanonical = View::hasSection('canonical')
      ? (preg_match('#^https?://#', trim(View::getSection('canonical'))) ? trim(View::getSection('canonical')) : $udsBase . '/' . ltrim(trim(View::getSection('canonical')), '/'))
      : $udsBase . (request()->getPathInfo() === '/' ? '/' : rtrim(request()->getPathInfo(), '/'));
  $udsOgImage = View::hasSection('ogimage') ? trim(View::getSection('ogimage')) : $udsBase . '/img/og-default.jpg';
  if ($udsOgImage !== '' && $udsOgImage[0] === '/') $udsOgImage = $udsBase . $udsOgImage;
  $v = fn ($f) => @filemtime(public_path($f));
@endphp
<title>{!! $udsTitle !!}</title>
<meta name="description" content="{!! $udsDesc !!}">
<meta name="robots" content="{{ $udsRobots }}">
<link rel="canonical" href="{{ $udsCanonical }}">
<meta name="theme-color" content="#0b111c">
<meta property="og:site_name" content="Universal Driveshafts">
<meta property="og:title" content="{!! $udsTitle !!}">
<meta property="og:description" content="{!! $udsDesc !!}">
<meta property="og:type" content="@yield('ogtype','website')">
<meta property="og:url" content="{{ $udsCanonical }}">
<meta property="og:image" content="{{ $udsOgImage }}">
<meta property="og:locale" content="en_US">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{!! $udsTitle !!}">
<meta name="twitter:description" content="{!! $udsDesc !!}">
<meta name="twitter:image" content="{{ $udsOgImage }}">
<link rel="icon" href="/favicon.ico?v=2" sizes="48x48">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=2">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v=2" sizes="180x180">
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/store/barlow-sc-700.woff2') }}" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/hankengrotesk-var.woff2') }}" crossorigin>
@stack('preload')
<link rel="stylesheet" href="{{ asset('css/st-shell.css') }}?v={{ $v('css/st-shell.css') }}">
<link rel="stylesheet" href="{{ asset('css/store.css') }}?v={{ $v('css/store.css') }}">
<script>document.documentElement.classList.add('js')</script>
@stack('head')
@include('partials.schema')
@stack('schema')
@include('partials.analytics')
<script>window.UDS_T=@json((object) \App\Support\JsStrings::dict());window.udsT=function(s,r){var o=(window.UDS_T&&window.UDS_T[s])||s;if(r)for(var k in r)o=o.split(':'+k).join(r[k]);return o;};</script>
</head>
@php
  $udsAssistant = auth()->check() && app(\App\Services\Chatbot\ChatbotService::class)->enabled();
@endphp
<body class="st @yield('bodyclass') @if($udsAssistant) has-ua @endif">
<a class="st-skip" href="#main">{!! __('Skip to content') !!}</a>
@include('partials.store.header')
<main id="main">
@yield('content')
</main>
@include('partials.store.footer')
@unless(request()->is('cart', 'cart/*', 'checkout', 'checkout/*', 'login', 'register'))
<div class="st-fab" data-st-fab>
  <div class="st-fab__menu" id="st-fab-m" hidden>
    <a href="https://wa.me/18136445707" target="_blank" rel="noopener"><x-icon name="whatsapp"/> {{ __('WhatsApp a tech') }}</a>
    <a href="tel:+18135246491"><x-icon name="phone"/> {{ __('Call (813) 524-6491') }}</a>
  </div>
  <button type="button" class="st-fab__btn" aria-expanded="false" aria-controls="st-fab-m"><x-icon name="headset"/><span>{{ __('Ask a tech') }}</span></button>
</div>
@endunless
@include('partials.cart-drawer')
@if($udsAssistant)@include('partials.assistant')@endif
<script src="{{ asset('js/cart-drawer.js') }}?v={{ $v('js/cart-drawer.js') }}" defer></script>
<script src="{{ asset('js/store.js') }}?v={{ $v('js/store.js') }}" defer></script>
@stack('scripts')
@include('partials.ai-translation-notice')
</body>
</html>
