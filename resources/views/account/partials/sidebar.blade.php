@php
  $nav = [
    ['dashboard', '/account', 'Overview', 'gauge'],
    ['orders', '/account/orders', 'Orders', 'package'],
    ['buy-again', '/account/buy-again', 'Buy again', 'cart'],
    ['addresses', '/account/addresses', 'Addresses', 'pin'],
    ['business', '/account/business', 'Business account', 'certificate'],
    ['designs', '/account/designs', 'Driveshaft designs', 'sliders'],
    ['requests', '/account/requests', 'Custom requests', 'mail'],
    ['wishlist', '/wishlist', 'Wishlist', 'heart'],
    ['settings', '/account/settings', 'Profile & security', 'user'],
  ];
@endphp
<nav class="acct-nav" aria-label="{{ __('My Account') }}">
  @foreach($nav as [$key, $href, $label, $ico])
    <a href="{{ $href }}" class="{{ $active === $key ? 'on' : '' }}" @if($active === $key) aria-current="page" @endif><x-icon :name="$ico"/>{{ __($label) }}</a>
  @endforeach
  <hr>
  <form method="POST" action="/logout">@csrf<button type="submit"><x-icon name="arrow-right"/>{{ __('Sign out') }}</button></form>
  <div class="acct-help">{{ __('Need help?') }}<br><a href="tel:+18135246491">(813) 524-6491</a></div>
</nav>
<script>
(function () {
  // phone tab strip: bring the current tab into view, and fade the right edge while more tabs are hidden there
  var n = document.querySelector('.acct-nav'); if (!n) return;
  function edge() { n.classList.toggle('has-more', n.scrollWidth - n.clientWidth - n.scrollLeft > 4); }
  var on = n.querySelector('a.on');
  if (on && n.scrollWidth > n.clientWidth) { var a = on.getBoundingClientRect(), b = n.getBoundingClientRect(); n.scrollLeft += a.left - b.left - (b.width - a.width) / 2; }
  edge(); n.addEventListener('scroll', edge, { passive: true }); window.addEventListener('resize', edge);
})();
</script>
