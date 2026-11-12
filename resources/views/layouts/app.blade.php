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
<meta name="theme-color" content="#101c2e">
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
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/ibmplexsans-var.woff2') }}" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="{{ asset('fonts/ibmplexmono-500.woff2') }}" crossorigin>
<link rel="stylesheet" href="{{ asset('css/uds.css') }}?v={{ @filemtime(public_path('css/uds.css')) }}">
<link rel="stylesheet" href="{{ asset('css/uds-2026.css') }}?v={{ @filemtime(public_path('css/uds-2026.css')) }}">
<link rel="stylesheet" href="{{ asset('css/pages-2026.css') }}?v={{ @filemtime(public_path('css/pages-2026.css')) }}">
<script>document.documentElement.classList.add('js');try{if(localStorage.getItem('uds.rail')==='1')document.documentElement.classList.add('sb-rail')}catch(e){}</script>
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
  $isAdmin = auth()->check() && auth()->user()->is_admin;
  $cartCount = app(\App\Services\CartService::class)->count();
@endphp

@include('partials.sidebar')

<div class="page">
@if($ann = setting('storefront.announcement'))<div class="uannounce">{{ $ann }}</div>@endif
@if(request()->is('cart','checkout') && ($cn = setting('storefront.holiday_notice')))<div class="uannounce uannounce--warn">{{ $cn }}</div>@endif
<header class="tb" id="uhead">
  <button type="button" class="tb__burger" id="uburger" aria-label="{!! __('Open menu') !!}" aria-expanded="false" aria-controls="sb"><x-icon name="menu"/></button>
  <a class="tb__logo" href="/" aria-label="{!! __('Universal Driveshafts home') !!}"><img src="{{ asset('img/uds-logo.webp') }}" alt="{!! __('Universal Driveshafts') !!}" width="187" height="30"></a>
  <form class="search tb__search" action="/search" method="get" role="search">
    <x-icon name="search"/>
    <input type="search" name="q" placeholder="{!! __('Part number, OEM number or vehicle') !!}" aria-label="{!! __('Search parts') !!}" autocomplete="off">
    <button type="submit">{!! __('Search') !!}</button>
  </form>
  <div class="tb__acts">
    @if(setting('promo.enabled', true))<a class="tb__promo" href="{{ setting('promo.link') ?: '/shipping-policy' }}"><x-icon name="truck"/>{{ setting('promo.text') ? __(setting('promo.text')) : __('Free U.S. shipping on parts over $:min', ['min' => number_format(config('uds.free_shipping_min'))]) }}</a>@endif
    <button type="button" class="tb__icon tb__find" data-open-search aria-label="{!! __('Search parts') !!}"><x-icon name="search"/></button>
    <a class="tb__tel" href="tel:18135246491"><x-icon name="phone"/><span>(813) 524-6491</span></a>
    <a class="tb__icon tb__acct" href="{{ auth()->check() ? ($isAdmin ? '/admin' : '/account') : '/login' }}" aria-label="{{ auth()->check() ? __('My account') : __('Sign in') }}" title="{{ auth()->check() ? __('My account') : __('Sign in') }}"><x-icon name="user"/></a>
    <a class="tb__cart" href="/cart" data-open-cart aria-label="{!! __('Cart') !!}"><x-icon name="cart"/><span class="tb__cart-t">{!! __('Cart') !!}</span><b data-cart-count @if($cartCount==0) hidden @endif>{{ $cartCount }}</b></a>
  </div>
</header>

<main id="main">
@yield('content')
</main>

<footer class="ufoot">
  <div class="wrap">
    <div class="ufoot__grid">
      <nav aria-label="{!! __('Shop') !!}">
        <h2 class="ufoot__h">{!! __('Shop') !!}</h2>
        <a href="/shop">{!! __('All parts') !!}</a>
        <a href="/shop/driveshaft-components/u-joints">{!! __('Universal joints') !!}</a>
        <a href="/shop/driveshaft-components/flange-yokes">{!! __('Flange yokes') !!}</a>
        <a href="/shop/driveshaft-components/center-support-bearing">{!! __('Center support bearings') !!}</a>
        <a href="/shop/driveshaft-tubing/tubing">{!! __('Driveshaft tubing') !!}</a>
        <a href="/quick-order">{!! __('Quick order') !!}</a>
      </nav>
      <nav aria-label="{!! __('Driveshafts') !!}">
        <h2 class="ufoot__h">{!! __('Driveshafts') !!}</h2>
        <a href="/online-builder">{!! __('Online driveshaft builder') !!}</a>
        <a href="/catalog/driveshafts">{!! __('Driveshafts by vehicle') !!}</a>
        <a href="/driveshafts">{!! __('Driveshafts by make &amp; model') !!}</a>
        <a href="/premade-driveshafts">{!! __('Premade driveshafts') !!}</a>
        <a href="/heavy-duty-driveshafts">{!! __('Heavy duty driveshafts') !!}</a>
        <a href="/custom-off-road-driveshafts">{!! __('Off-road driveshafts') !!}</a>
      </nav>
      <nav aria-label="{!! __('Help') !!}">
        <h2 class="ufoot__h">{!! __('Help') !!}</h2>
        <a href="/track-order">{!! __('Track an order') !!}</a>
        <a href="/rma">{!! __('Returns &amp; RMA') !!}</a>
        <a href="/shipping-policy">{!! __('Shipping') !!}</a>
        <a href="/contact">{!! __('Contact') !!}</a>
        <a href="/wholesale">{!! __('Wholesale accounts') !!}</a>
        <a href="/about-us">{!! __('About us') !!}</a>
      </nav>
      <div class="ufoot__contact">
        <h2 class="ufoot__h">{!! __('Universal Driveshafts') !!}</h2>
        {{-- the label is a name, the same in every language (owner, 2026-11-11) --}}
        <a href="tel:18135246491"><x-icon name="phone"/> (813) 524-6491 &nbsp;Driveline Tech</a>
        <a href="tel:18136445707"><x-icon name="phone"/> {!! __('(813) 644-5707 &nbsp;OEM &amp; fleet') !!}</a>
        <a href="mailto:{{ config('uds.email') }}"><x-icon name="mail"/> {{ config('uds.email') }}</a>
        <form class="unews__form" method="POST" action="/newsletter" data-newsletter>@csrf<input type="text" name="website" tabindex="-1" autocomplete="off" class="unews__hp" aria-hidden="true"><label class="sr-only" for="unews-email">{!! __('E-mail') !!}</label><input id="unews-email" class="unews__in" type="email" name="email" placeholder="{!! __('E-mail for new parts and deals') !!}" required><button class="btn btn-primary btn-sm" type="submit">{!! __('Subscribe') !!}</button></form>
        @if(session('newsletter_ok'))<p class="unews__ok">{{ session('newsletter_ok') }}</p>@endif
      </div>
    </div>
    <p class="ufoot__legal">{!! __('Spicer') !!}<sup>{!! __('&reg;') !!}</sup>{!! __(', Dana') !!}<sup>{!! __('&reg;') !!}</sup>{!! __(', Neapco') !!}<sup>{!! __('&reg;') !!}</sup> {!! __('and all other manufacturer names, logos, series designations and part numbers shown on this site are trademarks of their respective owners and are used for identification and cross-reference purposes only. Universal Driveshafts LLC is an independent company and is not affiliated with, sponsored by, endorsed by or licensed by any of them. Parts sold under a UDS part number are UDS-brand replacement parts. Interchange data and images are for reference; verify fitment before ordering.') !!} <a href="/copyright">{!! __('Copyright &amp; trademark notice') !!}</a></p>
    <div class="ufoot__bar">
      <span>&copy; {{ date('Y') }} Universal Driveshafts LLC. {{ __('Built in the USA.') }}</span>
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
</div>

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
{{-- Instant pages: a link is fetched while the pointer rests on it (or as the finger or button goes down), so most clicks
     open a page that is already here (Chrome and Edge; other browsers ignore it). Never fetched early: pages with side
     effects or per-visit content (cart, checkout, account, admin, sign-in) and the language links, which set a cookie.
     Laravel keeps these requests out of the session's "previous URL"; uds.js drops them when the cart changes. --}}
<script type="speculationrules" data-uds-spec>{"prefetch":[{"source":"document","where":{"and":[{"href_matches":"/*"},{"not":{"href_matches":["/cart","/cart/*","/checkout","/checkout/*","/pay/*","/account","/account/*","/admin","/admin/*","/api/*","/login","/register","/register/*","/logout","/password/*","/email/*","/newsletter/*","/reviews/*","/feeds/*","/media/*","/assistant/*"]}},{"not":{"selector_matches":"[hreflang], [download], [target=_blank], [rel~=nofollow], [data-no-prefetch]"}}]},"eagerness":"moderate"}]}</script>
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
