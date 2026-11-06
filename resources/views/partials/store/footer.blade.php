@php
  $stores = config('uds.stores');
  $phone  = config('uds.stores.0.phone', '(813) 524-6491');
  $tel    = 'tel:+1' . preg_replace('/\D/', '', $phone);
@endphp
<section class="st-promise" aria-label="{{ __('Why order from UDS') }}">
  <div class="st-wrap st-promise__in">
    <div><x-icon name="clock"/><p><b>{{ __('Ships the same day') }}</b><span>{{ __('In-stock parts ordered before 2pm ET') }}</span></p></div>
    <div><x-icon name="truck"/><p><b>{{ __('Free over $:min', ['min' => number_format(config('uds.free_shipping_min'))]) }}</b><span>{{ __('U.S. shipping on parts') }}</span></p></div>
    <div><x-icon name="headset"/><p><b>{{ __('Real driveline techs') }}</b><span>{{ __('Call, text or WhatsApp, not a call center') }}</span></p></div>
    <div><x-icon name="returns"/><p><b>{{ __('14-day returns') }}</b><span>{{ __('Our mistake? Return shipping is on us') }}</span></p></div>
  </div>
</section>

<footer class="st-foot">
  <div class="st-wrap">
    <div class="st-foot__news">
      <div>
        <p class="st-eyebrow st-eyebrow--light">{{ __('From the shop floor') }}</p>
        <h2 class="st-foot__h">{{ __('New parts, restocks and the occasional deal.') }}</h2>
        <p>{{ __('A couple of e-mails a month. Unsubscribe anytime.') }}</p>
      </div>
      <form class="st-foot__form" method="post" action="/newsletter">
        @csrf
        <label class="st-sr" for="st-news">{{ __('E-mail') }}</label>
        <input id="st-news" type="email" name="email" required placeholder="{{ __('you@company.com') }}" autocomplete="email">
        <button class="st-btn st-btn--red" type="submit">{{ __('Subscribe') }}</button>
        @if(session('newsletter_ok'))<p class="st-foot__ok" role="status">{{ session('newsletter_ok') }}</p>@endif
      </form>
    </div>

    <div class="st-foot__grid">
      <div class="st-foot__brand">
        <img src="{{ asset('img/uds-logo.webp') }}" alt="Universal Driveshafts" width="187" height="30" loading="lazy">
        <p>{{ __('Driveshaft components and complete driveshafts, built and balanced in Florida, shipped across the U.S. and abroad.') }}</p>
        <a class="st-foot__tel" href="{{ $tel }}"><x-icon name="phone"/> {{ $phone }}</a>
        <a class="st-foot__mail" href="mailto:{{ config('uds.email') }}"><x-icon name="mail"/> {{ config('uds.email') }}</a>
        <div class="st-foot__social">
          <a href="{{ config('uds.instagram') }}" target="_blank" rel="noopener" aria-label="Instagram"><x-icon name="instagram"/></a>
          <a href="{{ config('uds.facebook') }}" target="_blank" rel="noopener" aria-label="Facebook"><x-icon name="facebook"/></a>
          <a href="{{ config('uds.tiktok') }}" target="_blank" rel="noopener" aria-label="TikTok"><x-icon name="tiktok"/></a>
        </div>
      </div>
      <nav aria-label="{{ __('Shop') }}">
        <p class="st-foot__t">{{ __('Shop') }}</p>
        <a href="/shop">{{ __('All parts') }}</a>
        <a href="/shop/driveshaft-components">{{ __('Driveshaft components') }}</a>
        <a href="/shop/custom-cut-tubing">{{ __('Custom cut tubing') }}</a>
        <a href="/shop/small-parts">{{ __('Small parts') }}</a>
        <a href="/quick-order">{{ __('Quick order by part number') }}</a>
      </nav>
      <nav aria-label="{{ __('Driveshafts') }}">
        <p class="st-foot__t">{{ __('Driveshafts') }}</p>
        <a href="/catalog/driveshafts">{{ __('By vehicle') }}</a>
        <a href="/premade-driveshafts">{{ __('Premade driveshafts') }}</a>
        <a href="/online-builder">{{ __('Online builder') }}</a>
        <a href="/driveshaft-designer">{{ __('Driveshaft designer') }}</a>
        <a href="/heavy-duty-driveshafts">{{ __('Heavy duty driveshafts') }}</a>
        <a href="/custom-off-road-driveshafts">{{ __('Off-road driveshafts') }}</a>
      </nav>
      <nav aria-label="{{ __('Orders') }}">
        <p class="st-foot__t">{{ __('Orders') }}</p>
        <a href="/track-order">{{ __('Track an order') }}</a>
        <a href="/rma">{{ __('Returns and RMA') }}</a>
        <a href="/shipping-policy">{{ __('Shipping policy') }}</a>
        <a href="/return-policy">{{ __('Return policy') }}</a>
        <a href="/wholesale">{{ __('Wholesale accounts') }}</a>
      </nav>
      <div>
        <p class="st-foot__t">{{ __('Visit us') }}</p>
        @foreach ($stores as $s)
          <div class="st-foot__store">
            <b>{{ $s['name'] }} @if($s['soon'])<em>{{ __('Coming soon') }}</em>@endif</b>
            <span>{{ __($s['sub']) }}</span>
            @unless($s['soon'])<span>{{ $s['addr'] }}</span><span>{{ $s['hours'] }}</span>@endunless
          </div>
        @endforeach
      </div>
    </div>

    <div class="st-foot__legal">
      <p>{{ __('Spicer®, Dana®, Neapco® and all other manufacturer names, logos and part numbers are trademarks of their owners and are used for identification and cross-reference only.') }}</p>
      <div class="st-foot__bar">
        <span>© {{ date('Y') }} Universal Driveshafts LLC</span>
        <span class="st-foot__pay" aria-label="{{ __('Payment methods') }}"><i>VISA</i><i>Mastercard</i><i>AMEX</i><i>Discover</i><i>Apple Pay</i><i>Affirm</i></span>
        <nav aria-label="{{ __('Legal') }}"><a href="/terms">{{ __('Terms') }}</a><a href="/privacy-policy">{{ __('Privacy') }}</a><a href="/copyright">{{ __('Copyright') }}</a></nav>
        @include('partials.store.lang', ['class' => 'langsw--foot'])
      </div>
    </div>
  </div>
</footer>
