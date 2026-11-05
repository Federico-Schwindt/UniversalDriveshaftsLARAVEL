@props(['variant' => 'inline'])
@php
  // "4.7 ★ · 200+ Google reviews": figures typed in Admin → Settings → Reviews (owner keeps them current by hand).
  // A visible badge only, never aggregateRating markup: Google does not show stars a business gives itself.
  $on = (bool) \App\Support\Settings::get('reviews.badge_enabled', true);
  $rating = (float) \App\Support\Settings::get('reviews.google_rating', 4.7);
  $count = (int) \App\Support\Settings::get('reviews.google_count', 200);
  $url = trim((string) \App\Support\Settings::get('reviews.google_profile_url', \App\Support\ReviewRequests::PROFILE_URL));
@endphp
@if($on && $rating >= 1 && $rating <= 5 && $count > 0)
<a {{ $attributes->merge(['class' => 'g-rate g-rate--' . $variant]) }} href="{{ $url }}" target="_blank" rel="noopener"
   aria-label="{{ __('Rated :r out of 5 on Google from :n+ reviews (opens Google)', ['r' => number_format($rating, 1), 'n' => number_format($count)]) }}">
  <span class="g-rate__stars" style="--g-fill: {{ round($rating / 5 * 100, 1) }}%" aria-hidden="true"><span>★★★★★</span></span>
  <b class="g-rate__n">{{ number_format($rating, 1) }}</b>
  <span class="g-rate__t">{{ __(':n+ Google reviews', ['n' => number_format($count)]) }}</span>
</a>
@endif
