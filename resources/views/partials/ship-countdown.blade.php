{{-- Shipping-time note for stock parts (cart, checkout, product page). Owner, 2026-11-14: no "order within 2 h and it
     ships today" countdown and no flat same-day promise; the boss's wording instead: most stock parts ship the same day
     when ordered before the cutoff (Settings → Shipping), processing times may vary. Custom builds ship when finished. --}}
<p class="shipcd {{ $class ?? '' }}">
  <x-icon name="clock"/>
  <span>{{ __('Most stock parts ship the same day when ordered before :time EST; processing times may vary.', ['time' => \App\Services\CartService::cutoffLabel()]) }}</span>
</p>
