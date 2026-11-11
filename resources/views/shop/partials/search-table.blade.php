{{-- One category's hits on the search page: same columns as that category's own table, no sorting. --}}
@php
  $ds = '<svg viewBox="0 0 64 40" width="26" height="26" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linejoin="round"><circle cx="12" cy="20" r="7"/><circle cx="52" cy="20" r="7"/><rect x="19" y="16.5" width="26" height="7" rx="2"/></svg>';
@endphp
<div class="ptbl-wrap"><table class="ptbl">
  <thead><tr>
    <th>{{ __('Add') }}</th><th>{{ __('Pic') }}</th><th>{{ __('Part #') }}</th><th>{{ __('Price') }}</th>
    @foreach($schema['columns'] as $col)
      <th @class(['l' => $col['key']==='pa_notes'])>{{ $col['key']==='pa_notes' ? __('Notes') : __($col['label']) }}</th>
    @endforeach
  </tr></thead>
  <tbody>
  @foreach($data['items'] as $p)
    <tr>
      <td data-label="{{ __('Add') }}" data-col="add"><span class="p-add">
        @if($p['price'] > 0)
        <button class="p-plus" data-add="{{ $p['id'] }}"@if(!empty($p['has_alt'])) data-has-alt @endif data-stock="{{ ($p['stock'] ?? 0) > 0 ? stock_label($p['stock']) : '' }}" data-status="{{ $p['stock_status'] }}" data-source="{{ $p['stock_source'] ?? '' }}" aria-label="{{ __('Add :sku to cart', ['sku' => $p['sku']]) }}" title="{{ __('Add to cart') }}"><x-icon name="cart"/><span class="p-plus__t">{{ __('Add') }}</span></button>
        @else
        {{-- $0.00 in the catalog = call for price: a quote request instead of a free add --}}
        <button type="button" class="p-call" data-quote="{{ $p['sku'] }}" data-pn="{{ $p['sku'] }}">{{ __('Get a quote') }}</button>
        @endif
      </span></td>
      <td data-label="{{ __('Pic') }}" data-col="pic">@if($p['image'])<img class="p-pic" width="88" height="88" src="{{ $p['image'] }}" data-zoom="{{ $p['image'] }}" alt="{{ $p['sku'] }}" loading="lazy">@else<span class="p-pic-ph">{!! $ds !!}</span>@endif</td>
      <td data-label="{{ __('Part #') }}" data-col="part"><a class="p-pn" href="/parts/{{ rawurlencode($p['sku']) }}">{{ $p['sku'] ?: '-' }}<x-icon name="arrow-up-right"/></a>@if(!empty($p['has_alt']))<button type="button" class="p-alt" data-alt-for="{{ $p['id'] }}" title="{{ __('Alternate parts available') }}" aria-label="{{ __('See alternate parts') }}"><x-icon name="warning"/><span>{{ __('Alt parts') }}</span></button>@endif</td>
      <td data-label="{{ __('Price') }}" data-col="price"><div class="p-price">
        @if($p['price'] <= 0)<span class="call">{{ __('Call for price') }}</span>@else
        @if($p['on_sale'])<span class="was">${{ number_format($p['regular'],2) }}</span>@endif
        <span class="now">${{ number_format($p['price'],2) }}</span>
        @endif
      </div></td>
      @foreach($schema['columns'] as $col)
        @if($col['key']==='pa_notes')
          <td class="l" data-label="{{ __('Notes') }}" data-col="notes">@if(!empty($p['attrs']['pa_notes']))<span class="p-notes">{{ preg_replace(['/\s+,/', '/,(?=[A-Za-z])/'], [',', ', '], $p['attrs']['pa_notes']) }}</span>@else<span class="p-muted">-</span>@endif</td>
        @else
          @php $cv = $p['attrs'][$col['key']] ?? '-'; $cvu = strtoupper(trim((string) $cv)); @endphp
          <td data-label="{{ __($col['label']) }}" data-col="{{ $col['key'] }}"@if(trim((string) $cv) === '' || $cv === '-') data-empty @endif>{{ in_array($cvu, ['Y', 'YES'], true) ? __('Yes') : (in_array($cvu, ['N', 'NO'], true) ? __('No') : $cv) }}</td>
        @endif
      @endforeach
    </tr>
  @endforeach
  </tbody>
</table></div>
@pushOnce('scripts')
<script src="/js/alerts.js?v={{ @filemtime(public_path('js/alerts.js')) }}" defer></script>
@endPushOnce
