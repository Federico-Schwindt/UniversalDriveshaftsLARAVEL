{{-- Slide-in cart: opens from the header cart icon on phones; every add-to-cart refreshes it and the badge --}}
<div class="ucart" id="ucart" aria-hidden="true" hidden>
  <div class="ucart__scrim" data-ucart-close></div>
  <aside class="ucart__panel" role="dialog" aria-modal="true" aria-labelledby="ucart-title">
    <header class="ucart__head">
      <div class="ucart__title">
        <span class="ucart__ic" aria-hidden="true"><x-icon name="cart"/></span>
        <h2 id="ucart-title">{{ __('Your cart') }}</h2>
        <span class="ucart__count" data-ucart-count hidden></span>
      </div>
      <button type="button" class="ucart__x" data-ucart-close aria-label="{{ __('Close cart') }}"><x-icon name="x"/></button>
    </header>
    <div class="ucart__ship" data-ucart-ship hidden>
      <p data-ucart-ship-txt></p>
      <div class="ucart__bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"><i data-ucart-bar></i></div>
    </div>
    <div class="ucart__body" data-ucart-body></div>
    <footer class="ucart__foot" data-ucart-foot hidden>
      <div class="ucart__sub"><span>{{ __('Subtotal') }}</span><b data-ucart-subtotal></b></div>
      <p class="ucart__note">{{ __('Shipping is calculated at checkout.') }}</p>
      <a href="/checkout" class="btn btn-primary ucart__go">{{ __('Checkout') }} <x-icon name="arrow-right"/></a>
      <div class="ucart__links">
        <a href="/cart">{{ __('View full cart') }}</a>
        <button type="button" data-ucart-close>{{ __('Keep shopping') }}</button>
      </div>
    </footer>
  </aside>
</div>
<svg width="0" height="0" style="position:absolute" aria-hidden="true"><symbol id="ucart-trash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M10 11v6M14 11v6M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12M9 7V4h6v3"/></symbol></svg>
<script>window.UCART_T = {!! json_encode([
  'empty' => __('Your cart is empty'), 'emptySub' => __('Parts you add show up here.'), 'browse' => __('Browse the parts catalog'),
  'items' => __(':n items'), 'item' => __('1 item'), 'remove' => __('Remove :name'), 'qty' => __('Quantity'), 'less' => __('One less'), 'more' => __('One more'),
  'each' => __(':price each'), 'shipLeft' => __('Add :amount more in parts for free US shipping'), 'shipOk' => __('Your parts ship free in the US'),
], JSON_UNESCAPED_UNICODE) !!};</script>
