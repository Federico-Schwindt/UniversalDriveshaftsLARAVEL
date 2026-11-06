@php
  /* Storefront header: live ship-today bar, search-first header and the category bar.
     Every figure comes from the same sources as the rest of the app (settings, config, CartService, finder cache). */
  $isAdmin   = auth()->check() && auth()->user()->is_admin;
  $wishCount = count($udsWished ?? []);
  $cartCount = app(\App\Services\CartService::class)->count();
  $cats      = config('uds.categories');
  $freeMin   = number_format(config('uds.free_shipping_min'));
  $phone     = config('uds.stores.0.phone', '(813) 524-6491');
  $tel       = 'tel:+1' . preg_replace('/\D/', '', $phone);
  $cdCut  = \App\Services\CartService::cutoffToday();
  $cdShip = \App\Services\CartService::shipDate();
  $cdNext = $cdShip->isToday() ? $cdShip->copy()->addWeekday() : $cdShip;
  $cdDay  = fn ($d) => $d->isTomorrow() ? __('tomorrow') : __('on :date', ['date' => $d->copy()->locale(app()->getLocale())->translatedFormat('l, M j')]);
  $catPath = fn ($slug) => app(\App\Services\CatalogService::class)->categoryPath($slug);
  $nav = [
    ['U-joints', $catPath('u-joints')],
    ['Yokes', '/shop/driveshaft-components'],
    ['Center bearings', $catPath('center-support-bearing')],
    ['Tubing', '/shop/custom-cut-tubing'],
    ['Complete driveshafts', '/catalog/driveshafts'],
  ];
@endphp
@if($ann = setting('storefront.announcement'))<div class="st-ann">{{ $ann }}</div>@endif
@if(request()->is('cart', 'checkout') && ($cn = setting('storefront.holiday_notice')))<div class="st-ann st-ann--warn">{{ $cn }}</div>@endif
<div class="st-top" @if(!setting('promo.enabled', true)) hidden @endif>
  <div class="st-wrap st-top__in">
    <p class="st-top__ship" data-shipcd data-cut="{{ $cdCut ? $cdCut->getTimestamp() * 1000 : '' }}"
       data-today="{{ __('Order in :time and in-stock parts ship today') }}"
       data-after="{{ __('In-stock parts ordered now ship :day', ['day' => $cdDay($cdNext)]) }}">
      <span class="st-pulse" aria-hidden="true"></span>
      <span data-shipcd-t>@if($cdCut){{ __('In-stock parts ship today, order before the cutoff') }}@else{{ __('In-stock parts ordered now ship :day', ['day' => $cdDay($cdShip)]) }}@endif</span>
    </p>
    <p class="st-top__free"><x-icon name="truck"/> @if($pl = setting('promo.link'))<a href="{{ $pl }}">@endif{{ __(setting('promo.text') ?: 'Free U.S. shipping on parts over $:min', ['min' => $freeMin]) }}@if($pl)</a>@endif</p>
    <nav class="st-top__links" aria-label="{{ __('Help') }}">
      <a href="{{ $tel }}" class="st-top__tel"><x-icon name="headset"/> {{ __('Talk to a driveline tech') }} <b>{{ $phone }}</b></a>
      <a href="/track-order">{{ __('Track order') }}</a>
      <a href="/wholesale">{{ __('Wholesale') }}</a>
      @include('partials.store.lang')
    </nav>
  </div>
</div>

<header class="st-head" id="st-head">
  <div class="st-wrap st-head__in">
    <button class="st-burger" type="button" aria-label="{{ __('Menu') }}" aria-expanded="false" aria-controls="st-menu" data-st-burger><span></span><span></span><span></span></button>
    <a class="st-logo" href="/" aria-label="{{ __('Universal Driveshafts home') }}">
      <img src="{{ asset('img/uds-logo.webp') }}" alt="Universal Driveshafts" width="212" height="34">
    </a>

    <form class="st-search" action="/search" method="get" role="search" data-st-search autocomplete="off">
      <label class="st-sr" for="st-q">{{ __('Search parts') }}</label>
      <x-icon name="search" class="st-search__ic"/>
      <input id="st-q" name="q" type="search" role="combobox" placeholder="{{ __('Part number, Spicer or OE number, series...') }}" aria-autocomplete="list" aria-controls="st-sres" aria-expanded="false" enterkeyhint="search" spellcheck="false">
      <span class="st-search__hint" aria-hidden="true">{{ __('e.g.') }} <b>5-1310X</b></span>
      <button type="submit" class="st-search__go">{{ __('Search') }}</button>
      <div class="st-sres" id="st-sres" role="listbox" hidden data-st-sres></div>
    </form>

    <nav class="st-acts" aria-label="{{ __('Account and cart') }}">
      <a class="st-act st-act--tel" href="{{ $tel }}"><x-icon name="phone"/><span><small>{{ __('Mon to Sat') }}</small>{{ $phone }}</span></a>
      <a class="st-act" href="{{ auth()->check() ? ($isAdmin ? '/admin' : '/account') : '/login' }}" aria-label="{{ auth()->check() ? __('Account') : __('Sign in') }}"><x-icon name="user"/><span class="st-act__t">{{ auth()->check() ? __('Account') : __('Sign in') }}</span></a>
      <a class="st-act st-act--wish" href="/wishlist" aria-label="{{ __('Wishlist') }}"><x-icon name="heart"/><i class="st-badge" data-wish-count @if($wishCount == 0) hidden @endif>{{ $wishCount }}</i></a>
      <a class="st-act st-act--cart" href="/cart" data-open-cart data-st-cart aria-label="{{ __('Cart') }}"><x-icon name="cart"/><span class="st-act__t">{{ __('Cart') }}</span><i class="st-badge" data-cart-count @if($cartCount == 0) hidden @endif>{{ $cartCount }}</i></a>
    </nav>
  </div>

  <nav class="st-cats" aria-label="{{ __('Shop') }}">
    <div class="st-wrap st-cats__in">
      <div class="st-mega" data-st-mega>
        <button type="button" class="st-cats__all" aria-expanded="false" aria-controls="st-mega-p" data-st-mega-btn><x-icon name="menu"/> {{ __('All parts') }} <x-icon name="caret-down" class="st-caret"/></button>
        <div class="st-mega__p" id="st-mega-p" hidden>
          <div class="st-mega__grid">
            @foreach (array_slice($cats, 0, 24) as [$name, $slug, $count, $img])
              <a href="{{ app(\App\Services\CatalogService::class)->categoryPath($slug) }}" class="st-mega__a"><img src="{{ media_url($img, 96) }}" alt="" loading="lazy" width="40" height="40"><span><b>{{ __($name) }}</b><small>{{ __(':n parts', ['n' => number_format($count)]) }}</small></span></a>
            @endforeach
          </div>
          <div class="st-mega__side">
            <p class="st-eyebrow">{{ __('Cannot find it?') }}</p>
            <b>{{ __('Search any Spicer, Neapco or OE number. We cross-reference it to the part on our shelf.') }}</b>
            <a class="st-btn st-btn--dark st-btn--sm" href="/shop">{{ __('Browse all :n categories', ['n' => ($udsCatalog['cats'] ?? 0) ?: count($cats)]) }} <x-icon name="arrow-right"/></a>
          </div>
        </div>
      </div>
      @foreach ($nav as [$label, $href])
        <a class="st-cats__a" href="{{ $href }}">{{ __($label) }}</a>
      @endforeach
      <a class="st-cats__a" href="/premade-driveshafts">{{ __('Premade') }}</a>
      <a class="st-cats__a st-cats__a--hot" href="/online-builder"><x-icon name="sliders"/> {{ __('Build a driveshaft') }}</a>
    </div>
  </nav>
</header>

<div class="st-menu" id="st-menu" hidden data-st-menu>
  <div class="st-menu__scrim" data-st-menu-close></div>
  <aside class="st-menu__p" role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
    <div class="st-menu__top">
      <img src="{{ asset('img/uds-logo.webp') }}" alt="Universal Driveshafts" width="170" height="27">
      <button type="button" class="st-menu__x" data-st-menu-close aria-label="{{ __('Close menu') }}"><x-icon name="x"/></button>
    </div>
    <a class="st-btn st-btn--red st-btn--block" href="/catalog/driveshafts"><x-icon name="crosshair"/> {{ __('Find my driveshaft') }}</a>
    <p class="st-menu__h">{{ __('Shop parts') }}</p>
    <ul class="st-menu__list">
      @foreach (array_slice($cats, 0, 12) as [$name, $slug, $count, $img])
        <li><a href="{{ app(\App\Services\CatalogService::class)->categoryPath($slug) }}"><span>{{ __($name) }}</span><small>{{ number_format($count) }}</small></a></li>
      @endforeach
      <li><a href="/shop"><span><b>{{ __('All categories') }}</b></span><x-icon name="arrow-right"/></a></li>
    </ul>
    <p class="st-menu__h">{{ __('Complete driveshafts') }}</p>
    <ul class="st-menu__list">
      <li><a href="/catalog/driveshafts"><span>{{ __('By vehicle') }}</span><x-icon name="arrow-right"/></a></li>
      <li><a href="/premade-driveshafts"><span>{{ __('Premade, ready to ship') }}</span><x-icon name="arrow-right"/></a></li>
      <li><a href="/online-builder"><span>{{ __('Online builder') }}</span><x-icon name="arrow-right"/></a></li>
    </ul>
    <p class="st-menu__h">{{ __('Help') }}</p>
    <ul class="st-menu__list">
      <li><a href="/track-order"><span>{{ __('Track order') }}</span></a></li>
      <li><a href="/wholesale"><span>{{ __('Wholesale accounts') }}</span></a></li>
      <li><a href="{{ auth()->check() ? ($isAdmin ? '/admin' : '/account') : '/login' }}"><span>{{ auth()->check() ? __('My account') : __('Sign in') }}</span></a></li>
      <li><a href="/wishlist"><span>{{ __('Wishlist') }}</span>@if($wishCount)<small>{{ $wishCount }}</small>@endif</a></li>
      @auth<li><form method="POST" action="/logout">@csrf<button type="submit" class="st-menu__out"><span>{{ __('Sign out') }}</span></button></form></li>@endauth
    </ul>
    <a class="st-menu__tel" href="{{ $tel }}"><x-icon name="headset"/><span><small>{{ __('Talk to a driveline tech') }}</small><b>{{ $phone }}</b></span></a>
    @include('partials.store.lang', ['class' => 'langsw--drawer'])
  </aside>
</div>
