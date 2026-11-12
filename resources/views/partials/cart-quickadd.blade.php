{{-- quick add by part number, like a parts counter (PTI's cart): type the number, the quantity, Add item.
     Below the cart's lines (owner, 2026-11-12: the parts come first); on an empty cart it is the first thing offered. --}}
<form class="cartadd" id="cart-quickadd" autocomplete="off" novalidate>
  <label class="cartadd__lbl" for="qa-sku">{!! __('Add by part number') !!}</label>
  <input class="cartadd__sku" id="qa-sku" name="sku" type="text" placeholder="{!! __('Part number, e.g. 5-1310XU') !!}" spellcheck="false" required>
  <input class="cartadd__qty" id="qa-qty" name="qty" type="number" min="1" max="999" value="1" inputmode="numeric" aria-label="{!! __('Quantity') !!}">
  <button type="submit" class="btn btn-primary">{!! __('Add item') !!}</button>
  <a class="cartadd__more" href="/quick-order">{!! __('Add many at once') !!}</a>
  <p class="cartadd__msg" role="status" hidden></p>
</form>
