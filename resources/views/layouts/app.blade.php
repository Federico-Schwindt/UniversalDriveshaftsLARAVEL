<!doctype html>
<html lang="{{ \App\Http\Middleware\SetLocale::tag(app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
  $udsTitle = trim($__env->yieldContent('title', 'Universal Driveshafts'));
  $udsDesc  = trim($__env->yieldContent('desc', __('Driveshaft components and complete driveshafts, u-joints, yokes, center support bearings, flanges and tubing. Built in the USA, shipped fast.')));
  // per-URL override from admin → SEO (English pages only: translations keep their own copy)
  $udsOwn = app()->getLocale() === 'en' && !request()->getQueryString() ? \App\Support\Seo::page(request()->getPathInfo()) : null;
  if (!empty($udsOwn['title'])) $udsTitle = e($udsOwn['title']);
  if (!empty($udsOwn['description'])) $udsDesc = e($udsOwn['description']);
  // Only the production host may be indexed. Every other environment stays out, whatever a page asks for.
  $udsProd   = app()->environment('production');
  $udsRobots = $udsProd
      ? (!empty($udsOwn['robots']) ? $udsOwn['robots'] : (View::hasSection('robots') ? trim(View::getSection('robots')) : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1'))
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
{{-- 2026 storefront shell (header, footer): scoped tokens, sits on top of the page styles above --}}
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/store/barlow-sc-700.woff2') }}" crossorigin>
<link rel="stylesheet" href="{{ asset('css/st-shell.css') }}?v={{ @filemtime(public_path('css/st-shell.css')) }}">
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

@include('partials.store.header')

<main id="main">
@yield('content')
</main>

@include('partials.store.footer')

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
<script src="{{ asset('js/store.js') }}?v={{ @filemtime(public_path('js/store.js')) }}" defer></script>
<script src="{{ asset('js/search.js') }}?v={{ @filemtime(public_path('js/search.js')) }}" defer></script>
<script src="{{ asset('js/phone.js') }}?v={{ @filemtime(public_path('js/phone.js')) }}" defer></script>
@if($udsAssistant)@include('partials.assistant')@endif
@stack('scripts')
@include('partials.ai-translation-notice')
</body>
</html>
