{{-- Back-in-stock capture on the product page. Receives $p (id, sku, name, stock_status, stock). --}}
@php $rcvOut = isset($p) && ((($p['stock_status'] ?? 'instock') !== 'instock') || (($p['stock'] ?? null) === 0)); @endphp
@if($rcvOut)
<form class="rcv-notify" data-notify action="/alerts/subscribe" method="post" novalidate>
  @csrf
  <input type="hidden" name="kind" value="product">
  <input type="hidden" name="ref" value="{{ $p['id'] }}">
  <input type="text" name="website" class="rcv-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
  <div class="rcv-notify__head">
    <span class="rcv-notify__ic" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg></span>
    <div><b>{{ __('Notify me when it\'s back') }}</b><span>{{ __('One e-mail the moment :part is in stock again. No newsletter.', ['part' => $p['sku'] ?: __('this part')]) }}</span></div>
  </div>
  <div class="rcv-notify__row">
    <label class="rcv-sr" for="rcv-email">{{ __('E-mail address') }}</label>
    <input type="email" id="rcv-email" name="email" required placeholder="you@email.com" autocomplete="email" inputmode="email" value="{{ auth()->user()->email ?? '' }}">
    <button type="submit" class="btn btn-primary btn-sm">{{ __('Notify me') }}</button>
  </div>
  <p class="rcv-notify__msg" role="status" aria-live="polite" hidden></p>
</form>
@pushOnce('scripts')
<script src="/js/alerts.js?v={{ @filemtime(public_path('js/alerts.js')) }}" defer></script>
@endPushOnce
@endif
