{{-- "Customers also bought": co-purchased products from this shop's orders + the WooCommerce history (CatalogService::alsoBought, cached 6 h).
     Receives $p (id, sku, name). Renders nothing with fewer than 2 results. --}}
@php
  $alsoBought = app(\App\Services\CatalogService::class)->alsoBought((int) $p['id'], 8);
  $abUser = auth()->user();
  $abDisc = ($abUser && ($abUser->wholesale_status ?? '') === 'approved' && ($abUser->wholesale_discount ?? 0) > 0) ? (float) $abUser->wholesale_discount : 0.0;
  $abPrice = fn (float $base) => round($abDisc ? $base * (1 - $abDisc / 100) : $base, 2);
@endphp
@if(count($alsoBought) >= 2)
<section class="ba-also" id="pdp-also" aria-labelledby="pdp-also-h">
  <h2 id="pdp-also-h">{{ __('Customers also bought') }}</h2>
  <p>{{ __('Parts other customers ordered together with :sku.', ['sku' => $p['sku']]) }}</p>
  <div class="pgrid">
    @foreach($alsoBought as $r)
      @php $abUrl = '/parts/' . rawurlencode($r['sku']); $abName = \Illuminate\Support\Str::limit(app(\App\Services\CatalogService::class)->cleanName($r), 70); @endphp
      <div class="pcard ba-also__card">
        <a class="pcard__media" href="{{ $abUrl }}" tabindex="-1" aria-hidden="true">@if($r['image'])<img src="{{ $r['image'] }}" alt="" loading="lazy" width="160" height="160">@else<x-icon name="package"/>@endif</a>
        <span class="pcard__body">
          <a class="pcard__sku" href="{{ $abUrl }}">{{ $r['sku'] }}</a>
          <a class="pcard__name" href="{{ $abUrl }}">{{ $abName }}</a>
          <span class="pcard__foot"><b>${{ number_format($abPrice((float) $r['price']), 2) }}</b><small class="{{ $r['stock_status'] === 'instock' ? 'pcard__ok' : '' }}">{{ $r['stock_status'] === 'instock' ? __('In stock') : ($r['stock_status'] === 'onbackorder' ? __('Backorder') : __('Call')) }}</small></span>
          <button type="button" class="p-plus ba-also__add" data-add="{{ $r['id'] }}" aria-label="Add {{ $r['sku'] }} to cart"><x-icon name="plus"/><span>{{ __('Add to cart') }}</span></button>
        </span>
      </div>
    @endforeach
  </div>
</section>
@endif
