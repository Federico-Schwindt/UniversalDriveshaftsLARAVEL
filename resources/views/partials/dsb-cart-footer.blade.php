{{-- quantity + add to cart (inside the build sheet) --}}
<footer class="dsb-cart-footer">
<div class="dsb-cart-status"><x-icon name="info"/> <span>{{ __('Complete the steps to enable checkout.') }}</span></div>
<div class="dsb-cart-row">
  <div class="dsb-qty"><label for="dsb-qty-sheet">{{ __('Qty') }}</label>
    <button type="button" data-qty="-" aria-label="{{ __('Decrease quantity') }}"><x-icon name="minus"/></button><input type="number" class="dsb-qty-input" id="dsb-qty-sheet" value="1" min="1" step="1"><button type="button" data-qty="+" aria-label="{{ __('Increase quantity') }}"><x-icon name="plus"/></button></div>
  <button type="button" class="dsb-btn dsb-btn--primary dsb-add-btn" data-add-btn disabled><x-icon name="cart"/> {{ __('Add to cart') }}</button>
</div>
</footer>
