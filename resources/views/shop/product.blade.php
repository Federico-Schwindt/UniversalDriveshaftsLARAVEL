@extends('layouts.app')

@php
  $byFootName = app(\App\Services\ProductService::class)->soldByFoot((int) $p['id']);
  $displayName = ($cat['slug'] ?? '') === 'tubing' && $seo['type'] && $byFootName ? \App\Support\PartGlossary::t($seo['type']) . __(', sold by the foot') : app(\App\Services\CatalogService::class)->cleanName($p);
  $st   = $p['stock_status'];
  $mode = $st === 'outofstock' ? 'out' : ($st === 'onbackorder' ? 'bo' : 'in');
  $gallery = array_values(array_filter($p['gallery'] ?: [$p['image']]));
  $base = rtrim(config('app.url'), '/');
  $imgAlt = $p['sku'] . ' ' . ($seo['short'] ?: $displayName);
  $catPath = ($group && $cat) ? '/shop/' . $group['slug'] . '/' . $cat['slug'] : null;
  $callPrice = $p['price'] <= 0;   // a $0.00 catalog price is a data gap: "call for price", no add button, no $0 Offer
@endphp

@section('ogtype','product')
@section('title', $seo['title'])
@section('desc', $seo['desc'])
@section('canonical', '/parts/' . rawurlencode($p['sku']))
@if(!empty($p['image']))@section('ogimage', str_starts_with($p['image'], 'http') ? $p['image'] : $base . $p['image'])@endif

@push('head')
<link rel="stylesheet" href="{{ asset('css/pdp.css') }}?v={{ @filemtime(public_path('css/pdp.css')) }}">
@unless($callPrice)
<meta property="product:price:amount" content="{{ number_format($p['price'], 2, '.', '') }}">
<meta property="product:price:currency" content="USD">
@endunless
<meta property="product:availability" content="{{ $mode === 'in' ? 'in stock' : ($mode === 'bo' ? 'backorder' : 'out of stock') }}">
<meta property="product:retailer_item_id" content="{{ $p['sku'] }}">
<meta property="product:condition" content="new">
<style>
/* buy row: qty + Add + wishlist stay on one line (the heart used to wrap onto its own 52x25 row on phones) */
.pdp__cart .pdp__wish{order:2;width:48px;min-height:48px;align-self:stretch}
.pdp__cart .pdp__ftnote,.pdp__cart .pdp__msg{order:3;flex-basis:100%}
@media (max-width:560px){.pdp__cart{flex-wrap:wrap}.pdp__cart .btn{min-width:0;flex:1 1 0}.pdp__cart .btn-line{flex-basis:100%}}
.pdp__secondary a,.pdp__secondary button{min-height:40px}
.pdp__sku{font-family:var(--font-m);font-weight:600;font-size:.95rem;letter-spacing:.02em;color:var(--blue);margin:0}
.pdp h1.pdp__name{font-family:var(--font-h);font-weight:700;font-size:clamp(1.35rem,1.05rem + 1.2vw,1.85rem);line-height:1.25;letter-spacing:-.015em;color:var(--ink);margin:.3rem 0 0;max-width:none}
.uspecs > div{background:var(--paper-2);border-radius:var(--r-sm);padding:.85rem 1rem;display:flex;flex-direction:column;gap:.2rem}
.pdp__xref{border-top:1px solid var(--line);padding-block:clamp(1.75rem,1rem + 2vw,2.75rem);display:grid;gap:1.25rem}
.pdp__xref h2{font-size:1.28rem;margin:0}
.pdp__xref p{margin:0;color:var(--ink-2);line-height:1.65}
.pdp__xref .k{font-family:var(--font-m);font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-faint);display:block;margin-bottom:.3rem}
.xrefs{display:flex;flex-wrap:wrap;gap:.45rem;list-style:none;padding:0;margin:0}
.xrefs li{font-family:var(--font-m);font-size:.84rem;font-weight:500;padding:.35rem .65rem;border-radius:var(--r-xs);background:var(--paper-2);color:var(--ink)}
.xrefs li a{color:var(--blue)}
.pdp__desc{border-top:1px solid var(--line);padding-block:clamp(1.75rem,1rem + 2vw,2.75rem)}
.pdp__desc > h2{font-size:1.28rem;margin-bottom:1rem}
.prose{max-width:78ch;color:var(--ink-2);line-height:1.7;font-size:.97rem}
.prose h3{font-size:1.08rem;margin:1.5rem 0 .5rem;color:var(--ink)}
.prose h4{font-size:.98rem;margin:1.2rem 0 .4rem;color:var(--ink)}
.prose p{margin:0 0 .9rem}
.prose ul,.prose ol{margin:0 0 1rem 1.2rem;padding:0}
.prose li{margin:.25rem 0}
.prose table{border-collapse:collapse;width:100%;max-width:560px;margin:.5rem 0 1rem;font-size:.9rem}
.prose th,.prose td{border:1px solid var(--line);padding:.45rem .7rem;text-align:left;vertical-align:top}
.prose th{background:var(--paper-2);font-family:var(--font-m);font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-faint)}
.prose strong{color:var(--ink)}
.pdp__rel{border-top:1px solid var(--line);padding-block:clamp(1.75rem,1rem + 2vw,2.75rem)}
.pdp__rel h2{font-size:1.28rem;margin-bottom:.4rem}
.pdp__rel > p{color:var(--ink-2);margin-bottom:1.25rem;max-width:64ch}
.pdp__rel .pcard__name{font-size:.84rem;min-height:0}
.pgrid{grid-template-columns:repeat(4,minmax(0,1fr))}.pgrid .pcard{min-width:0}.pcard__media{overflow:hidden;min-height:0}.pcard__media img{width:auto;height:auto;max-width:100%;max-height:100%}
.pcard__media:not(:has(img)){background:var(--paper-2)}.pcard__media > .i{font-size:2.4rem;opacity:.35}
@media (max-width:640px){.pdp__rel .pgrid{grid-template-columns:repeat(2,minmax(0,1fr))}}
.pdp__faq{border-top:1px solid var(--line);padding-block:clamp(1.75rem,1rem + 2vw,2.75rem)}
.pdp__faq h2{font-size:1.28rem;margin-bottom:.25rem}
.pdp__faq .faq{margin-top:1rem;max-width:78ch}
.pdp__detail h2 small{display:block;font-family:var(--font-b);font-weight:400;font-size:.85rem;color:var(--ink-faint);margin-top:.2rem}
</style>
@endpush

@push('scripts')
@include('shop.partials.js-i18n')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
<script src="{{ asset('js/product.js') }}?v={{ @filemtime(public_path('js/product.js')) }}" defer></script>
@endpush

@push('schema')
@php
  $avail = $mode === 'out' ? 'OutOfStock' : ($mode === 'bo' ? 'BackOrder' : 'InStock');
  $crumbs = [[__('Home'), $base . '/'], [__('Catalog'), $base . '/shop']];
  if ($group) { $crumbs[] = [__($group['label']), $base . '/shop/' . $group['slug']]; }
  if ($catPath) { $crumbs[] = [\App\Support\PartGlossary::t($cat['name']), $base . $catPath]; }
  $crumbs[] = [$p['sku'], $base . '/parts/' . rawurlencode($p['sku'])];
  $abs = fn ($u) => str_starts_with($u, 'http') ? $u : $base . $u;

  $props = [];
  foreach (array_merge($p['series'] ? ['Series' => $p['series']] : [], $p['style'] ? ['Style' => $p['style']] : [], $p['specs'] ?? []) as $k => $v) {
    if ($v === '' || $v === null || in_array($k, ['Brand'], true) || isset($props[$k])) continue;
    $props[$k] = ['@type' => 'PropertyValue', 'name' => $k, 'value' => html_entity_decode((string) $v)];
  }
  $product = array_filter([
    '@type'       => 'Product',
    '@id'         => $base . '/parts/' . rawurlencode($p['sku']) . '#product',
    'name'        => $p['sku'] . ' ' . ($seo['short'] ?: $displayName),
    'alternateName' => $displayName !== ($seo['short'] ?: $displayName) ? $displayName : null,
    'sku'         => $p['sku'],
    'mpn'         => $p['sku'],
    'productID'   => 'sku:' . $p['sku'],
    'url'         => $base . '/parts/' . rawurlencode($p['sku']),
    'description' => $seo['desc'],
    'image'       => array_values(array_map($abs, $gallery)) ?: null,
    'brand'       => ['@type' => 'Brand', 'name' => $p['brand'] ?: 'UDS'],
    'category'    => 'Vehicles & Parts > Vehicle Parts & Accessories > Motor Vehicle Parts > Motor Vehicle Power & Drive Train Parts',
    'weight'      => $p['weight'] !== '' ? ['@type' => 'QuantitativeValue', 'value' => (float) $p['weight'], 'unitCode' => 'LBR', 'unitText' => 'lb'] : null,
    'additionalProperty' => array_values($props) ?: null,
    'isAccessoryOrSparePartFor' => !empty($xref['application']) ? array_map(fn ($a) => ['@type' => 'Vehicle', 'name' => $a], $xref['application']) : null,
    'isSimilarTo' => $alts ? array_map(fn ($a) => ['@type' => 'Product', 'name' => $a['sku'], 'sku' => $a['sku'], 'url' => $base . '/parts/' . rawurlencode($a['sku'])], $alts) : null,
    'offers'      => [
      '@type'           => 'Offer',
      'url'             => $base . '/parts/' . rawurlencode($p['sku']),
      'price'           => number_format($p['price'], 2, '.', ''),
      'priceCurrency'   => 'USD',
      'availability'    => 'https://schema.org/' . $avail,
      'inventoryLevel'  => $mode === 'in' && ($p['stock'] ?? 0) > 0 && ($p['stock_source'] ?? 'ours') !== 'supplier' ? ['@type' => 'QuantitativeValue', 'value' => (int) $p['stock']] : null,
      'itemCondition'   => 'https://schema.org/NewCondition',
      'priceValidUntil' => now()->addYear()->toDateString(),
      'seller'          => ['@id' => $base . '/#organization'],
      'shippingDetails' => [
        '@type' => 'OfferShippingDetails',
        'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'US'],
      ],
      'hasMerchantReturnPolicy' => [
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => 'US',
        'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
        'merchantReturnDays' => 14,                       // /return-policy: 14 days with an RMA
        'returnMethod' => 'https://schema.org/ReturnByMail',
        'returnFees' => 'https://schema.org/RestockingFees',
        'restockingFee' => 20,                            // percent, as on /return-policy
      ],
    ],
  ], fn ($v) => $v !== null);
  $product['offers'] = array_filter($product['offers'], fn ($v) => $v !== null);
  if ($p['price'] <= 0) unset($product['offers']);   // "call for price": no $0 Offer in the rich result

  $breadcrumb = [
    '@type' => 'BreadcrumbList',
    'itemListElement' => collect($crumbs)->values()->map(fn ($c, $i) => [
      '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1],
    ])->all(),
  ];
  $graph = [$product, $breadcrumb];
  if (count($faq) >= 2) {
    $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn ($f) => [
      '@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ], $faq)];
  }
@endphp
<script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
@include('shop.partials.style')


<div class="wrap" data-pdp>

  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <a href="/shop">{{ __('Catalog') }}</a>
    @if($group)<x-icon name="caret-right"/><a href="/shop/{{ $group['slug'] }}">{{ __($group['label']) }}</a>@endif
    @if($catPath)<x-icon name="caret-right"/><a href="{{ $catPath }}">{{ \App\Support\PartGlossary::t($cat['name']) }}</a>@endif
    <x-icon name="caret-right"/><span aria-current="page">{{ $p['sku'] }}</span>
  </nav>

  <div class="pdp">
    {{-- gallery --}}
    <div>
      @if(count($gallery))
        <figure class="pdp__stage">
          <img id="pdp-img" src="{{ $gallery[0] }}" alt="{{ $imgAlt }}" width="800" height="600">
        </figure>
        @if(count($gallery) > 1)
          <ul class="pdp__thumbs">
            @foreach($gallery as $i => $img)
              <li><button type="button" data-full="{{ $img }}" aria-current="{{ $i === 0 ? 'true' : 'false' }}" aria-label="View image {{ $i+1 }}">
                <img src="{{ $img }}" alt="{{ $imgAlt }} – view {{ $i+1 }}" loading="lazy">
              </button></li>
            @endforeach
          </ul>
        @endif
      @else
        <figure class="pdp__stage pdp__stage--empty"><x-icon name="package"/></figure>
      @endif
    </div>

    {{-- buy box --}}
    <div>
      @if($p['brand'] || $p['series'] || $p['style'])
        <div class="pdp__brand">
          @if($p['brand'])<span class="pdp__chip pdp__chip--oem">{{ $p['brand'] }}</span>@endif
          @if($p['series'])@if($seriesHub)<a class="pdp__chip" href="/series/{{ $seriesHub['slug'] }}">{{ __('Series') }} {{ $p['series'] }}</a>@else<span class="pdp__chip">{{ __('Series') }} {{ $p['series'] }}</span>@endif @endif
          @if($p['style'])<span class="pdp__chip">{{ $p['style'] }}</span>@endif
        </div>
      @endif

      {{-- "1680-60" is searched as "1680/60" too (Search Console, 2026-11-04): say both, so the page matches either --}}
      @php $skuAlias = preg_match('/^(\d{3,5})-(\d{1,4}[A-Z]?)$/', (string) $p['sku'], $sa) ? $sa[1] . '/' . $sa[2] : null; @endphp
      <p class="pdp__sku">{{ __('Part No.') }} {{ $p['sku'] }}@if($skuAlias) <span class="muted">({{ __('also written') }} {{ $skuAlias }})</span>@endif</p>
      @php
        // a short, scannable title ("1350 Series Weld Yoke"); the full catalog name, with its dimensions, stays right under it
        $pdpTitle = ($byFootName || !$seo['short']) ? ($displayName ?: $p['sku']) : $seo['short'];
        $pdpKeySpecs = collect($p['specs'] ?? [])->except(['Brand', 'Series', 'Style', 'Material', 'Notes'])->filter(fn ($v) => $v !== '')->take(3);
        $pdpSeries = $p['series'] ? trim(explode(',', (string) $p['series'])[0]) : '';
      @endphp
      <h1 class="pdp__name">{{ $pdpTitle }}</h1>
      @if($displayName && $pdpTitle !== $displayName)<p class="pdp__fullname">{{ $displayName }}</p>@endif
      @if($pdpKeySpecs->isNotEmpty())
        <ul class="pdp__keyspecs" aria-label="{{ __('Key dimensions') }}">@foreach($pdpKeySpecs as $k => $v)<li><span>{{ __($k) }}</span> <b>{{ html_entity_decode($v) }}</b></li>@endforeach</ul>
      @endif

      @php
        // sold by the foot = tubing listed with a 1" length (the price is per foot); fixed-length tubes are not
        preg_match('/Tube Length\s*([\d.]+)/i', $p['name'], $ml);
        $tubeLen = (string) ($p['attrs']['pa_tube-length'] ?? ($ml[1] ?? ''));
        $byFoot = $byFootName;   // one definition, shared with the shipping weight (ProductService::soldByFoot)
        preg_match('/Tube Dia\s*([\d.]+)/i', $p['name'], $md); preg_match('/Tube Wall\s*([\d.]+)/i', $p['name'], $mw);
        $tubeSpec = trim(($md[1] ?? '') ? $md[1] . '" OD' : '') . (($mw[1] ?? '') ? ' × ' . $mw[1] . '" ' . __('wall') : '');
      @endphp
      <div class="pdp__pricerow">
        <span class="pdp__price">@if($callPrice){{ __('Call for price') }}@else${{ number_format($p['price'],2) }}@endif @if($byFoot && !$callPrice)<small class="pdp__perft">/ ft</small>@endif</span>
        @if($byFoot)<button type="button" class="pdp__ftbadge" data-ft-open><x-icon name="ruler"/> {{ __('Sold by the foot') }}</button>@endif
        @if($p['on_sale'])
          <span class="pdp__was">${{ number_format($p['regular'],2) }}</span>
          <span class="pdp__save">{{ __('Save $') }}{{ number_format($p['regular']-$p['price'],2) }}</span>
        @endif
      </div>

      @if($mode === 'in')
        @if(($p['stock_source'] ?? null) === 'supplier')
          {{-- in stock at our supplier (PTI), not on our Florida shelf: no same-day countdown, no count --}}
          <p class="pdp__stock pdp__stock--in"><x-icon name="truck"/><b>{{ __('Ships from our supplier') }}</b></p>
          <p class="pdp__stocknote">{{ __('Not on our Florida shelf right now; our supplier has it. Call (813) 524-6491 for a ship date.') }}</p>
        @else
          <p class="pdp__stock pdp__stock--in"><x-icon name="check"/><b>{{ __('In stock') }}</b><span>{{ ($p['stock'] ?? 0) > 0 ? __(':n ready to ship', ['n' => $p['stock']]) : '' }}</span></p>
          @include('partials.ship-countdown', ['class' => 'shipcd--pdp'])
        @endif
      @elseif($mode === 'bo')
        <p class="pdp__stock pdp__stock--bo"><x-icon name="clock"/><b>{{ __('On backorder') }}</b></p>
        <p class="pdp__stocknote">{{ __('We can build this or ship part of the order now. Call to confirm a date.') }}</p>
      @else
        <p class="pdp__stock pdp__stock--out"><x-icon name="warning"/><b>{{ __('Call for availability') }}</b></p>
        <p class="pdp__stocknote">{{ __('Stock varies on this part. Call') }} <a href="tel:18135246491">(813) 524-6491</a> {{ __('and we will check.') }}</p>
      @endif

      @unless($callPrice)@include('partials.affirm-msg', ['amount' => $p['price'], 'class' => 'affirm-msg--pdp'])@endunless
      @php $pdpWished = in_array($p['id'], $udsWished ?? []); @endphp
      @includeIf('partials.pdp-notify')       {{-- back-in-stock / quote capture (recovery agent) --}}
      @if($callPrice)
      <div class="pdp__cart">
        <button type="button" class="btn btn-primary btn-lg" data-quote="{{ $p['sku'] }}" data-pn="{{ $p['sku'] }}"><x-icon name="headset"/><span>{{ __('Get a quote') }}</span></button>
        <a class="btn btn-line btn-lg" href="tel:18135246491">{{ __('Call (813) 524-6491') }}</a>
      </div>
      @pushOnce('scripts')
      <script src="/js/alerts.js?v={{ @filemtime(public_path('js/alerts.js')) }}" defer></script>
      @endPushOnce
      @else
      <div class="pdp__cart">
        <div class="uqty">
          <button type="button" data-qty="down" aria-label="{{ __('Decrease quantity') }}"><x-icon name="minus"/></button>
          <input type="number" id="pdp-qty" value="1" min="1" aria-label="{{ __('Quantity') }}">
          <button type="button" data-qty="up" aria-label="{{ __('Increase quantity') }}"><x-icon name="plus"/></button>
        </div>
        <button type="button" class="btn btn-primary btn-lg" id="pdp-add" data-add="{{ $p['id'] }}">
          <x-icon name="cart"/><span>{{ __('Add to cart') }}</span>
        </button>
        @if($byFoot)<p class="pdp__ftnote"><x-icon name="ruler"/> {{ __('Quantity is in') }} <b>{{ __('feet') }}</b>: <span data-ft-live>1 ft = 12" · ${{ number_format($p['price'],2) }}</span></p>@endif
        <button type="button" class="pdp__wish{{ $pdpWished ? ' is-on' : '' }}" id="pdp-wish"
                data-wish="{{ $p['id'] }}" aria-pressed="{{ $pdpWished ? 'true' : 'false' }}"
                aria-label="{{ $pdpWished ? __('Remove from wishlist') : __('Add to wishlist') }}"
                title="{{ $pdpWished ? __('Remove from wishlist') : __('Add to wishlist') }}">
          <x-icon name="heart"/>
        </button>
      </div>
      @endif

      <div class="pdp__secondary">
        <a href="/cart"><x-icon name="cart"/> {{ __('View cart') }}</a>
        <a href="https://wa.me/18136445707?text={{ rawurlencode(__('Hi, a question about part :sku: ', ['sku' => $p['sku']])) }}" target="_blank" rel="noopener"><x-icon name="whatsapp"/> {{ __('Ask a tech about this part') }}</a>
      </div>

      {{-- a yoke or flange needs its u-joint: the matching joints, one tap to add --}}
      @if(!empty($joints))
        <div class="pdp__joint">
          <p class="pdp__joint-h"><x-icon name="plus"/> {{ __('Add the matching :s u-joint', ['s' => $pdpSeries]) }}</p>
          <ul>
            @foreach($joints as $j)
              <li>
                <a class="pdp__joint-it" href="/parts/{{ rawurlencode($j['sku']) }}">@if($j['image'])<img src="{{ $j['image'] }}" alt="" width="48" height="48" loading="lazy">@endif<span><b>{{ $j['sku'] }}</b><small>{{ trim(($j['brand'] ? $j['brand'] . ' · ' : '') . ($j['style'] ?: __('Universal joint'))) }}</small></span></a>
                <span class="pdp__joint-p">${{ number_format($j['price'], 2) }}</span>
                <button type="button" class="pdp__joint-add" data-add-sku="{{ $j['sku'] }}" aria-label="{{ __('Add :sku to cart', ['sku' => $j['sku']]) }}"><x-icon name="plus"/> {{ __('Add') }}</button>
              </li>
            @endforeach
          </ul>
        </div>
      @endif

      <x-google-rating variant="card"/>
      <ul class="pdp__assure">
        <li><x-icon name="truck"/><span><b>{{ __('Free shipping on parts orders over $') }}{{ number_format(config('uds.free_shipping_min')) }}.</b> {{ __('Tubing and complete driveshafts always ship at live UPS rates, calculated at checkout.') }}</span></li>
        <li><x-icon name="returns"/><span><b>{{ __('14-day returns.') }}</b> {{ __('Wrong or damaged part? We send a prepaid label.') }}</span></li>
        <li><x-icon name="headset"/><span><b>{{ __('Fitment help before you buy.') }}</b> {{ __('Call (813) 524-6491 and talk to a driveline tech.') }}</span></li>
      </ul>
    </div>
  </div>

  {{-- sticky buy bar: appears once the main buy box scrolls out of view --}}
  <div class="pdp__sticky" id="pdp-sticky" aria-hidden="true">
    <div class="pdp__sticky-in">
      <span class="pdp__sticky-media">
        @if(count($gallery))<img src="{{ $gallery[0] }}" alt="" loading="lazy">@else<x-icon name="package"/>@endif
      </span>
      <span class="pdp__sticky-id">
        <b>{{ $p['sku'] }}</b>
        <span>{{ \Illuminate\Support\Str::limit($pdpTitle, 46) }}</span>
      </span>
      <span class="pdp__sticky-price">{{ $callPrice ? __('Call for price') : '$' . number_format($p['price'],2) }}</span>
      @if($callPrice)
      <button type="button" class="btn btn-primary" data-quote="{{ $p['sku'] }}" data-pn="{{ $p['sku'] }}"><x-icon name="headset"/><span>{{ __('Get a quote') }}</span></button>
      @else
      <button type="button" class="btn btn-primary" id="pdp-sticky-add" data-add="{{ $p['id'] }}">
        <x-icon name="cart"/><span>{{ __('Add to cart') }}</span>
      </button>
      @endif
    </div>
  </div>

  {{-- specifications + notes --}}
  <div class="pdp__detail">
    <section aria-labelledby="pdp-specs-h">
      <h2 id="pdp-specs-h">{{ __('Specifications') }} <small>{{ $p['sku'] }}{{ $seo['short'] ? ' · ' . $seo['short'] : '' }}</small></h2>
      <dl class="uspecs">
        <div><dt class="k">{{ __('Part number') }}</dt><dd class="v">{{ $p['sku'] }}</dd></div>
        @if($seo['type'])<div><dt class="k">{{ __('Part type') }}</dt><dd class="v">{{ \App\Support\PartGlossary::t($seo['type']) }}</dd></div>@endif
        <div><dt class="k">{{ __('Condition') }}</dt><dd class="v">{{ $p['brand']==='OEM SPICER' ? __('New, OEM') : __('New') }}</dd></div>
        @if($p['brand'])<div><dt class="k">{{ __('Brand') }}</dt><dd class="v">{{ $p['brand'] }}</dd></div>@endif
        @if($p['series'])<div><dt class="k">{{ __('Series') }}</dt><dd class="v">@if($seriesHub)<a href="/series/{{ $seriesHub['slug'] }}">{{ $p['series'] }}</a>@else{{ $p['series'] }}@endif</dd></div>@endif
        @if($p['style'])<div><dt class="k">{{ __('Style') }}</dt><dd class="v">{{ $p['style'] }}</dd></div>@endif
        @foreach(($p['specs'] ?? []) as $label => $val)
          @continue(in_array($label, ['Brand', 'Series', 'Style'], true) || $val === '')
          <div><dt class="k">{{ __($label) }}</dt><dd class="v">{{ html_entity_decode($val) }}</dd></div>
        @endforeach
        @if($p['weight'] !== '')<div><dt class="k">{{ __('Weight') }}</dt><dd class="v">{{ $p['weight'] }} lb</dd></div>@endif
      </dl>
    </section>

    <section aria-labelledby="pdp-fit-h">
      <h2 id="pdp-fit-h">{{ __('Fitment') }}</h2>
      <div class="pdp__notes">
        @if($p['series'])@include('partials.store.ujoint-drawing', ['series' => $p['series'], 'class' => 'pdp__ujdwg'])@endif
        @if($p['notes'])
          <p>{{ $p['notes'] }}</p>
        @endif
        @if(!empty($xref['application']))
          <p><b>{{ __('Fits / application:') }}</b> {{ implode('; ', $xref['application']) }}</p>
        @endif
        @if($p['series'] && $seriesHub && ($seriesHub['cap'] ?? null))
          <p>{{ __(':series series parts use :cap u-joint bearing caps', ['series' => $p['series'], 'cap' => $seriesHub['cap']]) }}{{ $seriesHub['width'] ? __(' on a :w snap-ring-to-snap-ring width', ['w' => $seriesHub['width']]) : '' }} {{ __('(nominal). Confirm the cap diameter on the mating yoke or joint before ordering.') }}</p>
        @endif
        @if(!$p['notes'] && empty($xref['application']) && !($seriesHub['cap'] ?? null))
          <p>{{ __('No fitment notes recorded for this part. Measure the shaft or send us the number off the old component and we will confirm it before you order.') }}</p>
        @endif
      </div>
      <div class="pdp__help">
        @if($seriesHub)<a class="textlink" href="/series/{{ $seriesHub['slug'] }}">{{ __('All :series series parts', ['series' => $seriesHub['name']]) }} <x-icon name="arrow-right"/></a>@endif
        @if($catPath)<a class="textlink" href="{{ $catPath }}">{{ __('All :type', ['type' => \App\Support\PartGlossary::t($cat['name'])]) }} <x-icon name="arrow-right"/></a>@endif
        <a class="textlink" href="/contact">{{ __('Ask a driveline tech about fitment') }} <x-icon name="arrow-right"/></a>
      </div>
    </section>
  </div>

  {{-- cross-reference (OEM / competitor numbers from pa_partn-number) --}}
  @if(!empty($xref['xref']) || !empty($xref['application']))
  <section class="pdp__xref" aria-labelledby="pdp-xref-h">
    <h2 id="pdp-xref-h">{{ __('Interchange / cross-reference') }}</h2>
    @if(!empty($xref['xref']))
      <div>
        <span class="k">{{ __('Replaces / interchanges with') }}</span>
        <ul class="xrefs">
          @foreach($xref['xref'] as $x)
            <li>@if($x['sku'])<a href="/parts/{{ rawurlencode($x['sku']) }}">{{ $x['num'] }}</a>@else{{ $x['num'] }}@endif</li>
          @endforeach
        </ul>
      </div>
      <p>{{ __(':sku replaces or interchanges with :nums', ['sku' => $p['sku'], 'nums' => implode(', ', array_column($xref['xref'], 'num'))]) }}{{ __('. Cross-reference numbers are supplied for identification; compare the series, spline and bolt-pattern dimensions above before substituting.') }}</p>
    @endif
    @if(!empty($xref['application']))
      <p><span class="k">{{ __('Fits / application') }}</span>{{ implode('; ', $xref['application']) }}</p>
    @endif
  </section>
  @endif

  {{-- alternative parts: exact-fitment matches + same number under another brand, in the category table format --}}
  @includeIf('partials.pdp-estimator')     {{-- shipping estimator (commerce agent) --}}
  @includeIf('partials.pdp-also-bought')   {{-- customers also bought (commerce agent) --}}
  @if(!empty($sameFit))
  <section class="ualts" id="pdp-alts" aria-labelledby="pdp-alts-h">
    <h2 id="pdp-alts-h">{{ __('Alternative parts for :sku', ['sku' => $p['sku']]) }}</h2>
    <p>{{ $p['series'] ? __('Same measurements in the :series series', ['series' => $p['series']]) : __('Same measurements') }} {{ __('— a different number, brand or lube spec, but the same fitment. Check the notes before you substitute.') }}</p>
    <div id="shop-table">@include('shop.partials.search-table', ['data' => ['items' => $sameFit], 'schema' => $altSchema])</div>
  </section>
  @endif

  {{-- long description (WooCommerce content, sanitized) --}}
  @if($descriptionHtml)
  <section class="pdp__desc" aria-labelledby="pdp-desc-h">
    <h2 id="pdp-desc-h">{{ __('About :sku', ['sku' => $p['sku']]) }}</h2>
    <div class="prose">{!! $descriptionHtml !!}</div>
  </section>
  @endif

  {{-- related parts: same series + category --}}
  @if($related)
  <section class="pdp__rel" aria-labelledby="pdp-rel-h">
    <h2 id="pdp-rel-h">{{ $p['series'] ? __('Related :type in the :series series', ['type' => (!empty($cat['name']) ? \App\Support\PartGlossary::t($cat['name']) : __('parts')), 'series' => $p['series']]) : __('Related :type', ['type' => (!empty($cat['name']) ? \App\Support\PartGlossary::t($cat['name']) : __('parts'))]) }}</h2>
    <p>{{ $p['series'] ? __('Other :type that share the :series series.', ['type' => mb_strtolower($cat ? \App\Support\PartGlossary::t($cat['name']) : __('parts')), 'series' => $p['series']]) : __('Other :type that share the same category.', ['type' => mb_strtolower($cat ? \App\Support\PartGlossary::t($cat['name']) : __('parts'))]) }} @if($catPath)<a href="{{ $catPath }}{{ $p['series'] && $seriesHub ? '?series=' . $seriesHub['slug'] : '' }}">{{ __('See the full table') }}</a>.@endif</p>
    <div class="pgrid">
      @foreach($related as $r)
        <a class="pcard" href="/parts/{{ rawurlencode($r['sku']) }}">
          <span class="pcard__media">@if($r['image'])<img src="{{ $r['image'] }}" alt="{{ $r['sku'] }} {{ \Illuminate\Support\Str::limit(app(\App\Services\CatalogService::class)->cleanName($r), 60, '') }}" loading="lazy" width="160" height="160">@else<x-icon name="package"/>@endif</span>
          <span class="pcard__body">
            <span class="pcard__sku">{{ $r['sku'] }}</span>
            <span class="pcard__name">{{ \Illuminate\Support\Str::limit(app(\App\Services\CatalogService::class)->cleanName($r), 70) }}</span>
            <span class="pcard__foot"><b>{{ $r['price'] > 0 ? '$' . number_format($r['price'], 2) : __('Call for price') }}</b><small class="{{ $r['stock_status'] === 'instock' ? 'pcard__ok' : '' }}">{{ $r['stock_status'] === 'instock' ? __('In stock') : ($r['stock_status'] === 'onbackorder' ? __('Backorder') : __('Call')) }}</small></span>
          </span>
        </a>
      @endforeach
    </div>
  </section>
  @endif

  {{-- FAQ (data-driven; FAQPage JSON-LD above only when 2+ answers) --}}
  @if($faq)
  <section class="pdp__faq" aria-labelledby="pdp-faq-h">
    <h2 id="pdp-faq-h">{{ $p['sku'] }} {{ __('questions') }}</h2>
    <div class="faq">
      @foreach($faq as $i => $f)
        <details @if($i === 0) open @endif><summary>{{ $f[0] }}</summary><p>{{ $f[1] }}</p></details>
      @endforeach
    </div>
  </section>
  @endif

  <p class="xref-note">{{ __('Manufacturer names, series designations and part numbers shown in this catalog (including Spicer') }}<sup>{{ __('®') }}</sup>{{ __(', Dana') }}<sup>{{ __('®') }}</sup>{{ __(', Neapco') }}<sup>{{ __('®') }}</sup> {{ __('and vehicle makes) are trademarks of their respective owners and are used for identification and cross-reference only. They do not indicate affiliation, endorsement, or that the part was made by that manufacturer unless the brand is expressly stated. Parts under a UDS part number are UDS-brand replacements. Interchange data, stock and images are provided for reference and may contain errors, so verify fitment before ordering.') }} <a href="/copyright">{{ __('Copyright & trademark notice') }}</a> {{ __('·') }} <a href="/terms">{{ __('Terms of use') }}</a></p>
</div>
@if($byFoot)
{{-- sold-by-the-foot heads-up: shown once per session per tube, re-opened from the badge --}}
<div class="ftpop" id="ftpop" hidden role="dialog" aria-modal="true" aria-labelledby="ftpop-h">
  <div class="ftpop__scrim" data-ft-close></div>
  <div class="ftpop__card">
    <button type="button" class="ftpop__x" data-ft-close aria-label="{{ __('Close') }}"><x-icon name="x"/></button>
    <div class="ftpop__art" aria-hidden="true">
      <svg viewBox="0 0 240 72" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <rect x="14" y="18" width="212" height="30" rx="4" fill="rgba(18,70,171,.08)"/>
        <ellipse cx="14" cy="33" rx="6" ry="15" fill="#fff"/><ellipse cx="226" cy="33" rx="6" ry="15" fill="#fff"/>
        <path d="M14 18h212M14 48h212" opacity=".35"/>
        <path d="M30 60v8M60 60v8M90 60v8M120 60v8M150 60v8M180 60v8M210 60v8" opacity=".6"/>
        <path d="M24 64h192" opacity=".6"/><path d="M28 64l-6-3v6zM212 64l6-3v6z" fill="currentColor" stroke="none"/>
        <text x="120" y="12" text-anchor="middle" font-family="IBM Plex Mono,Menlo,monospace" font-size="11" font-weight="600" fill="currentColor" stroke="none">12" = 1 ft</text>
      </svg>
    </div>
    <p class="ftpop__kicker">{{ __('Heads up before you add it') }}</p>
    <h2 id="ftpop-h">{{ __('This tube is sold') }} <em>{{ __('by the foot') }}</em></h2>
    <p class="ftpop__lead">{{ __('The price shown is') }} <b>{{ __('per foot') }}</b>{{ $tubeSpec ? __(' of :spec DOM tubing', ['spec' => $tubeSpec]) : '' }}{{ __('. The quantity you enter is the number of feet you get — so') }} <b>{{ __('quantity 4 = 4 ft (48")') }}</b>{{ __('. Need a specific cut? Tell us the exact length in the order notes and we cut it before it ships.') }}</p>
    <div class="ftpop__calc">
      <label for="ftpop-ft">{{ __('Feet needed') }}</label>
      <div class="uqty">
        <button type="button" data-ft="down" aria-label="{{ __('Fewer feet') }}"><x-icon name="minus"/></button>
        <input type="number" id="ftpop-ft" value="1" min="1" max="40" inputmode="numeric" aria-label="{{ __('Feet') }}">
        <button type="button" data-ft="up" aria-label="{{ __('More feet') }}"><x-icon name="plus"/></button>
      </div>
      <div class="ftpop__total"><span data-ft-inches>12"</span><b data-ft-total>${{ number_format($p['price'],2) }}</b></div>
    </div>
    <ul class="ftpop__facts">
      <li><x-icon name="package"/> {{ __('Each tube ships in its own box at live UPS rates') }}</li>
      <li><x-icon name="truck"/> {{ __('Tubing does not count toward the $') }}{{ number_format(config('uds.free_shipping_min')) }} {{ __('free-shipping total') }}</li>
    </ul>
    <div class="ftpop__acts">
      <button type="button" class="btn btn-primary" data-ft-apply>{{ __('Got it — set') }} <span data-ft-btn>1 ft</span></button>
      <a class="btn btn-ghost" href="/online-builder">{{ __('Need a finished driveshaft?') }}</a>
    </div>
  </div>
</div>
<script>
(function(){var pop=document.getElementById('ftpop');if(!pop)return;var price={{ (float) $p['price'] }},key='uds.ft.'+@json($p['sku']);
var qty=document.getElementById('pdp-qty'),ft=document.getElementById('ftpop-ft'),live=document.querySelector('[data-ft-live]');
function money(n){return '$'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
function paint(){var n=Math.max(1,parseInt(ft.value,10)||1);ft.value=n;pop.querySelector('[data-ft-inches]').textContent=n+' ft = '+(n*12)+'"';pop.querySelector('[data-ft-total]').textContent=money(n*price);pop.querySelector('[data-ft-btn]').textContent=n+' ft';}
function liveNote(){if(!live||!qty)return;var n=Math.max(1,parseInt(qty.value,10)||1);live.textContent=n+' ft = '+(n*12)+'" · '+money(n*price);}
function open(){ft.value=qty?qty.value:1;paint();pop.hidden=false;document.documentElement.style.overflow='hidden';setTimeout(function(){ft.focus();ft.select();},60);}
function close(){pop.hidden=true;document.documentElement.style.overflow='';try{sessionStorage.setItem(key,'1');}catch(e){}}
pop.addEventListener('click',function(e){if(e.target.closest('[data-ft-close]'))close();var b=e.target.closest('[data-ft]');if(b){ft.value=Math.max(1,(parseInt(ft.value,10)||1)+(b.dataset.ft==='up'?1:-1));paint();}
  if(e.target.closest('[data-ft-apply]')){if(qty){qty.value=ft.value;qty.dispatchEvent(new Event('input',{bubbles:true}));qty.dispatchEvent(new Event('change',{bubbles:true}));}liveNote();close();}});
ft.addEventListener('input',paint);document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!pop.hidden)close();});
document.querySelectorAll('[data-ft-open]').forEach(function(b){b.addEventListener('click',open);});
if(qty){qty.addEventListener('input',liveNote);qty.addEventListener('change',liveNote);document.querySelectorAll('[data-qty]').forEach(function(b){b.addEventListener('click',function(){setTimeout(liveNote,0);});});}
var seen=false;try{seen=!!sessionStorage.getItem(key);}catch(e){}
if(!seen)setTimeout(open,350);
})();
</script>
@endif
</div>
<div class="stock-pop" id="stock-pop"></div>
<div class="img-pop" id="img-pop" aria-hidden="true"><img alt=""></div>
@endsection
