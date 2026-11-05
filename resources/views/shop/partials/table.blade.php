@php
  $activeParams = [];
  foreach ($schema['filters'] as $f) { if (isset($filters[$f['key']])) $activeParams[$f['param']] = $filters[$f['key']]; }
  if (isset($filters['pa_series']) && !isset($activeParams['series'])) $activeParams['series'] = $filters['pa_series'];   // series-hub deep links
  $qbase = array_filter(array_merge(['q' => $q], $activeParams), fn ($v) => $v !== '' && $v !== null);
  $svc = app(\App\Services\CatalogService::class);
  $sortLink = function ($colSort) use ($qbase, $sort) {
    $asc  = $colSort === 'price' ? 'price_asc'  : ($colSort === 'part' ? 'part_asc'  : $colSort . ':asc');
    $desc = $colSort === 'price' ? 'price_desc' : ($colSort === 'part' ? 'part_desc' : $colSort . ':desc');
    $active = in_array($sort, [$asc, $desc], true);
    $next = $sort === $asc ? $desc : $asc;
    return ['href' => '?' . http_build_query(array_merge($qbase, ['sort' => $next])), 'active' => $active, 'dir' => $sort === $desc ? 'desc' : 'asc'];
  };
  $caret = fn ($d) => '<svg class="i" viewBox="0 0 256 256" fill="currentColor" width="1em" height="1em" aria-hidden="true"><path d="'.($d==='up'
      ? 'M213.66,165.66a8,8,0,0,1-11.32,0L128,91.31,53.66,165.66a8,8,0,0,1-11.32-11.32l80-80a8,8,0,0,1,11.32,0l80,80A8,8,0,0,1,213.66,165.66Z'
      : ($d==='down'
        ? 'M213.66,101.66l-80,80a8,8,0,0,1-11.32,0l-80-80A8,8,0,0,1,53.66,90.34L128,164.69l74.34-74.35a8,8,0,0,1,11.32,11.32Z'
        : 'M181.66,170.34a8,8,0,0,1,0,11.32l-48,48a8,8,0,0,1-11.32,0l-48-48a8,8,0,0,1,11.32-11.32L128,212.69l42.34-42.35A8,8,0,0,1,181.66,170.34Zm-96-84.68L128,43.31l42.34,42.35a8,8,0,0,0,11.32-11.32l-48-48a8,8,0,0,0-11.32,0l-48,48A8,8,0,0,0,85.66,85.66Z')).'"/></svg>';
  $arrow = fn ($sl) => '<span class="th-arrow '.($sl['active']?'on':'').'">'.$caret($sl['active'] ? ($sl['dir']==='desc'?'down':'up') : 'both').'</span>';
  $ds = '<svg viewBox="0 0 64 40" width="26" height="26" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linejoin="round"><circle cx="12" cy="20" r="7"/><circle cx="52" cy="20" r="7"/><rect x="19" y="16.5" width="26" height="7" rx="2"/></svg>';
@endphp
<div class="shop-count">{{ __($data['total']==1 ? ':n part' : ':n parts', ['n' => number_format($data['total'])]) }}{{ $q ? __(' matching ":q"', ['q' => $q]) : '' }}</div>

@if(count($data['items']))
<div class="ptbl-wrap"><table class="ptbl">
  <thead><tr>
    <th>{{ __('Add') }}</th><th>{{ __('Pic') }}</th>
    @php $slp=$sortLink('part'); @endphp
    <th><a class="th-sort" data-sort rel="nofollow" href="{{ $slp['href'] }}">{{ __('Part #') }}{!! $arrow($slp) !!}</a></th>
    @php $slpr=$sortLink('price'); @endphp
    <th><a class="th-sort" data-sort rel="nofollow" href="{{ $slpr['href'] }}">{{ __('Price') }}{!! $arrow($slpr) !!}</a></th>
    @foreach($schema['columns'] as $col)
      @if($col['key']==='pa_notes')
        <th class="l">{{ __('Notes') }}</th>
      @else
        @php $sl=$sortLink('attr:'.$col['key']); @endphp
        <th><a class="th-sort" data-sort rel="nofollow" href="{{ $sl['href'] }}">{{ __($col['label']) }}{!! $arrow($sl) !!}</a></th>
      @endif
    @endforeach
  </tr></thead>
  <tbody>
  @foreach($data['items'] as $p)
    <tr>
      @php $rowWished = in_array($p['id'], $udsWished ?? []); @endphp
      <td data-label="{{ __('Add') }}" data-col="add"><span class="p-add">
        <button class="p-wish{{ $rowWished ? ' is-on' : '' }}" data-wish="{{ $p['id'] }}" aria-pressed="{{ $rowWished ? 'true' : 'false' }}" aria-label="{{ $rowWished ? __('Remove from wishlist') : __('Add to wishlist') }}" title="{{ $rowWished ? __('Remove from wishlist') : __('Add to wishlist') }}"><x-icon name="heart"/></button>
        @if($p['price'] > 0)
        <button class="p-plus" data-add="{{ $p['id'] }}"@if(!empty($p['has_alt'])) data-has-alt @endif data-stock="{{ ($p['stock'] ?? 0) > 0 ? $p['stock'] : '' }}" data-status="{{ $p['stock_status'] }}" data-source="{{ $p['stock_source'] ?? '' }}" aria-label="{{ __('Add :sku to cart', ['sku' => $p['sku']]) }}" title="{{ __('Add to cart') }}"><x-icon name="cart"/><span class="p-plus__t">{{ __('Add') }}</span></button>
        @else
        {{-- $0.00 in the catalog = call for price: a quote request instead of a free add --}}
        <button type="button" class="p-call" data-quote="{{ $p['sku'] }}" data-pn="{{ $p['sku'] }}">{{ __('Get a quote') }}</button>
        @endif
      </span></td>
      <td data-label="{{ __('Pic') }}" data-col="pic">@if($p['image'])<img class="p-pic" width="88" height="88" src="{{ $p['image'] }}" data-zoom="{{ $p['image'] }}" alt="{{ $p['sku'] }} {{ \Illuminate\Support\Str::limit($svc->cleanName($p), 60, '') }}" loading="lazy">@else<span class="p-pic-ph">{!! $ds !!}</span>@endif</td>
      <td data-label="{{ __('Part #') }}" data-col="part"><a class="p-pn" href="/parts/{{ rawurlencode($p['sku']) }}">{{ $p['sku'] ?: '-' }}<x-icon name="arrow-up-right"/></a>@if(!empty($p['has_alt']))<button type="button" class="p-alt" data-alt-for="{{ $p['id'] }}" title="{{ __('Alternate parts available') }}" aria-label="{{ __('See alternate parts') }}"><x-icon name="warning"/><span>{{ __('Alt parts') }}</span></button>@endif</td>
      <td data-label="{{ __('Price') }}" data-col="price"><div class="p-price">
        @if($p['price'] <= 0)<span class="call">{{ __('Call for price') }}</span>@else
        @if($p['on_sale'])<span class="was">${{ number_format($p['regular'],2) }}</span>@endif
        <span class="now">${{ number_format($p['price'],2) }}</span>
        @if($p['on_sale'])<span class="save">{{ __('Save $') }}{{ number_format($p['regular']-$p['price'],2) }}</span>@endif
        @endif
      </div></td>
      @foreach($schema['columns'] as $col)
        @if($col['key']==='pa_notes')
          <td class="l" data-label="{{ __('Notes') }}" data-col="notes">@if(!empty($p['attrs']['pa_notes']))<span class="p-notes">{{ $p['attrs']['pa_notes'] }}</span>@else<span class="p-muted">-</span>@endif</td>
        @else
          @php $cv = $p['attrs'][$col['key']] ?? '-'; $cvu = strtoupper(trim((string) $cv)); @endphp
          <td data-label="{{ __($col['label']) }}" data-col="{{ $col['key'] }}">{{ in_array($cvu, ['Y', 'YES'], true) ? __('Yes') : (in_array($cvu, ['N', 'NO'], true) ? __('No') : $cv) }}</td>
        @endif
      @endforeach
    </tr>
  @endforeach
  </tbody>
</table></div>

@if($data['pages'] > 1)
  @php $qs = function ($pg) use ($qbase, $sort) { $params = array_merge($qbase, $sort !== 'price_asc' ? ['sort' => $sort] : [], $pg > 1 ? ['page' => $pg] : []); return '?' . http_build_query($params ?: ['page' => 1]); }; @endphp
  <div class="shop-pg">
    @if($data['page']>1)<a data-page href="{{ $qs($data['page']-1) }}" aria-label="{{ __('Previous page') }}"><x-icon name="arrow-left"/></a>@else<span class="dis" aria-hidden="true"><x-icon name="arrow-left"/></span>@endif
    @php $start=max(1,$data['page']-2); $end=min($data['pages'],$start+4); $start=max(1,$end-4); @endphp
    @if($start>1)<a data-page href="{{ $qs(1) }}">1</a>@if($start>2)<span class="dis">{{ __('…') }}</span>@endif @endif
    @for($i=$start;$i<=$end;$i++)@if($i==$data['page'])<span class="cur">{{ $i }}</span>@else<a data-page href="{{ $qs($i) }}">{{ $i }}</a>@endif @endfor
    @if($end<$data['pages'])@if($end<$data['pages']-1)<span class="dis">{{ __('…') }}</span>@endif<a data-page href="{{ $qs($data['pages']) }}">{{ $data['pages'] }}</a>@endif
    @if($data['page']<$data['pages'])<a data-page href="{{ $qs($data['page']+1) }}" aria-label="{{ __('Next page') }}"><x-icon name="arrow-right"/></a>@else<span class="dis" aria-hidden="true"><x-icon name="arrow-right"/></span>@endif
  </div>
@endif
@else
  <div class="aempty"><b>{{ __('No parts match those filters') }}</b>{{ __('Try a wider search, or clear the filters and start again.') }} <a class="textlink" href="/shop/{{ $group['slug'] }}/{{ $cat['slug'] }}">{{ __('Clear filters') }}</a></div>
@endif
@pushOnce('scripts')
<script src="/js/alerts.js?v={{ @filemtime(public_path('js/alerts.js')) }}" defer></script>
@endPushOnce
