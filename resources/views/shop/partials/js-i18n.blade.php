{{-- Strings catalog.js shows, in the visitor's language (catalog.js falls back to the English key). --}}
@if(app()->getLocale() !== 'en')
@php($jsT = collect(['Added', 'Add to cart', '+ Add to Cart', 'Add to wishlist', 'Availability', 'available', 'Backorder', 'Current', 'Details', 'In Stock', 'Loading…', 'Notes', 'Notes:', 'Not found.', 'Out of Stock', 'Part Number', 'Price', 'Remove from wishlist', 'SAVE ', 'Series ', 'Style', 'Type', 'UDS — our brand', 'Weight',
  // add-to-cart feedback, quick view, stock popover (product.js / catalog.js / shop-filters.js)
  'Adding…', 'Added ✓', 'Call for price', 'Get a quote', 'Quantity', 'Ships from our supplier', 'Session expired — reload the page and try again.',
  'Too many tries — wait a minute and try again.', 'Could not add to cart. Please try again.', 'Network error. Please try again.',
  'Could not load the parts. Please try again.'])->mapWithKeys(fn ($k) => [$k => __($k)]))
<script>window.UDS_T=Object.assign(window.UDS_T||{},{!! json_encode($jsT, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) !!});</script>
@endif
