{{-- quantity + add to cart (inside the build sheet) --}}
<footer class="dsb-cart-footer">
<div class="dsb-cart-status"><x-icon name="info"/> <span>{{ __('Complete the steps above to enable checkout.') }}</span></div>
<div class="dsb-cart-row">
  <div class="dsb-qty"><label style="font-size:.85rem;font-weight:600">{{ __('Quantity') }}</label>
    <button type="button" data-qty="-">−</button><input type="number" class="dsb-qty-input" value="1" min="1" step="1"><button type="button" data-qty="+"><x-icon name="plus"/></button></div>
  <button type="button" class="dsb-btn dsb-btn--primary dsb-add-btn" data-add-btn disabled><x-icon name="cart"/> {{ __('Add to cart') }} <span class="dsb-add-price" data-cart-price>$0.00</span></button>
</div>
  </footer>
