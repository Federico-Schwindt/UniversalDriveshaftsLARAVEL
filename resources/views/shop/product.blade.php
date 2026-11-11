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
@unless($callPrice)
<meta property="product:price:amount" content="{{ number_format($p['price'], 2, '.', '') }}">
<meta property="product:price:currency" content="USD">
@endunless
<meta property="product:availability" content="{{ $mode === 'in' ? 'in stock' : ($mode === 'bo' ? 'backorder' : 'out of stock') }}">
<meta property="product:retailer_item_id" content="{{ $p['sku'] }}">
<meta property="product:condition" content="new">
<style>
.pdp__cart .pdp__ftnote,.pdp__cart .pdp__msg{order:3;flex-basis:100%}
@media (max-width:560px){.pdp__cart{flex-wrap:wrap}.pdp__cart .btn{min-width:0;flex:1 1 0}.pdp__cart .btn-line{flex-basis:100%}}
.pdp__sku{font-family:var(--font-m);font-weight:600;font-size:.95rem;letter-spacing:.02em;color:var(--blue);margin:0}
.pdp h1.pdp__name{font-family:var(--font-h);font-weight:600;font-size:clamp(1.3rem,1.1rem + .8vw,1.7rem);line-height:1.25;letter-spacing:-.01em;color:var(--ink);margin:.3rem 0 0;max-width:none}
/* specs read like a data sheet: label left, value right, one hairline per row */
.pdp .uspecs{display:grid;grid-template-columns:1fr;gap:0;margin:0;border-top:1px solid var(--line)}
.uspecs > div{display:grid;grid-template-columns:minmax(0,42%) minmax(0,1fr);gap:1rem;align-items:baseline;padding:.5rem .1rem;border-bottom:1px solid var(--line);background:none;border-radius:0}
.uspecs dt{font-size:.84rem;color:var(--ink-3)}
.uspecs dd{margin:0;font-size:.9rem;color:var(--ink)}
.pdp__side{display:grid;gap:1.5rem;align-content:start}
.pdp__xref{display:grid;gap:.6rem}
.pdp__xref h2{font-size:1.05rem;margin:0}
.pdp__xref p{margin:0;color:var(--ink-3);font-size:.84rem;line-height:1.55}
.pdp__xref .k{font-family:var(--font-m);font-size:.72rem;letter-spacing:.05em;text-transform:uppercase;color:var(--ink-faint);display:block;margin-bottom:.3rem}
.xrefs{display:flex;flex-wrap:wrap;gap:.45rem;list-style:none;padding:0;margin:0}
.xrefs li{font-family:var(--font-m);font-size:.84rem;font-weight:500;padding:.35rem .65rem;border-radius:var(--r-xs);background:var(--paper-2);color:var(--ink)}
.xrefs li a{color:var(--blue)}
.pdp-fits > summary small{font:500 .78rem var(--font-m);color:var(--ink-3);margin-left:auto;padding-right:.5rem}
.pdp-fits__list{display:grid;gap:.55rem;padding:0 0 1rem;font-size:.88rem;line-height:1.5}
.pdp-fits__list p{margin:0}
.pdp-fits__list b{margin-right:.35rem}
.pdp-fits__list span{white-space:nowrap}
.pdp-fits__list span + span::before{content:" · ";color:var(--ink-faint)}
.pdp-fits__note{color:var(--ink-3);font-size:.8rem}
/* folded sections (description, shipping estimate): a heading row that opens; the text stays on the page */
.pdp-fold{border-top:1px solid var(--line)}
.pdp-fold > summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 0;cursor:pointer;list-style:none;font-weight:600}
.pdp-fold > summary::-webkit-details-marker{display:none}
.pdp-fold > summary::after{content:"+";font:400 1.4rem/1 var(--font-b);color:var(--blue)}
.pdp-fold[open] > summary::after{content:"\2212"}
.pdp-fold > summary h2{font-size:1.15rem;margin:0}
.pdp-fold > .prose{padding-bottom:1.5rem}
.pdp-fold--est{border:1px solid var(--line);border-radius:var(--r);padding:0 1rem}
.pdp-fold--est > summary{font-size:.92rem;padding:.75rem 0}
.pdp-fold--est .est{margin:0 0 1rem;padding:0;border:0;background:none}
.pdp-fold--est .est__head h2{display:none}
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
.pdp__rel{border-top:1px solid var(--line);padding-block:clamp(1.5rem,1rem + 1.5vw,2.25rem)}
.pdp__rel h2{font-size:1.15rem;margin-bottom:.4rem}
.pdp__rel-all{display:inline-flex;margin-bottom:1rem;font-size:.9rem}
.pdp__rel .pcard__name{font-size:.84rem;min-height:0}
.pgrid .pcard{min-width:0}.pcard__media{min-height:0}
.pcard__media:not(:has(img)){background:var(--paper-2)}.pcard__media > .i{font-size:2.4rem;opacity:.35}
.pdp__faq{border-top:1px solid var(--line);padding-block:clamp(1.5rem,1rem + 1.5vw,2.25rem)}
.pdp__faq h2{font-size:1.15rem;margin-bottom:.25rem}
.pdp__faq .faq{margin-top:1rem;max-width:78ch}
.pdp__detail h2 small{display:block;font-family:var(--font-b);font-weight:400;font-size:.85rem;color:var(--ink-faint);margin-top:.2rem}
</style>
@endpush

@push('scripts')
@include('shop.partials.js-i18n')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
<script src="{{ asset('js/product.js') }}?v={{ @filemtime(public_path('js/product.js')) }}" defer></script>
<script src="{{ asset('js/arrival.js') }}?v={{ @filemtime(public_path('js/arrival.js')) }}" defer></script>
@if($measure)<script src="{{ asset('js/measure.js') }}?v={{ @filemtime(public_path('js/measure.js')) }}" defer></script>@endif
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
      'inventoryLevel'  => $mode === 'in' && ($p['stock'] ?? 0) > 0 && ($p['stock_source'] ?? 'ours') !== 'supplier' ? (((int) $p['stock']) > 50 ? ['@type' => 'QuantitativeValue', 'minValue' => 50] : ['@type' => 'QuantitativeValue', 'value' => (int) $p['stock']]) : null,
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
<script type="application/ld+json">{!! json_encode(['@@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
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
      <h1 class="pdp__name">{{ $displayName ?: $p['sku'] }}</h1>

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
          <p class="pdp__stock pdp__stock--in"><x-icon name="check"/><b>{{ __('In stock') }}</b><span>{{ ($p['stock'] ?? 0) > 0 ? __(':n ready to ship', ['n' => stock_label($p['stock'])]) : '' }}</span></p>
          @include('partials.ship-countdown', ['class' => 'shipcd--pdp'])
          {{-- delivery date to the visitor's ZIP (js/arrival.js) --}}
          <p class="arrive" data-arrive data-id="{{ $p['id'] }}" data-zip="{{ \App\Support\Arrival::savedZip(auth()->user()) }}" aria-live="polite"></p>
        @endif
      @elseif($mode === 'bo')
        <p class="pdp__stock pdp__stock--bo"><x-icon name="clock"/><b>{{ __('On backorder') }}</b></p>
        <p class="pdp__stocknote">{{ __('We can build this or ship part of the order now. Call to confirm a date.') }}</p>
      @else
        <p class="pdp__stock pdp__stock--out"><x-icon name="warning"/><b>{{ __('Call for availability') }}</b></p>
        <p class="pdp__stocknote">{{ __('Stock varies on this part. Call') }} <a href="tel:18135246491">(813) 524-6491</a> {{ __('and we will check.') }}</p>
      @endif

      @unless($callPrice)@include('partials.affirm-msg', ['amount' => $p['price'], 'class' => 'affirm-msg--pdp'])@endunless
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
      </div>
      @endif

      <ul class="pdp__assure">
        <li><x-icon name="truck"/><span>{{ __('Free shipping on parts orders over $') }}{{ number_format(config('uds.free_shipping_min')) }}</span></li>
        <li><x-icon name="returns"/><span>{{ __('14-day returns') }}</span></li>
        <li><x-icon name="headset"/><span>{{ __('Fitment help:') }} <a href="tel:18135246491">(813) 524-6491</a></span></li>
      </ul>
      <x-google-rating variant="compact" class="pdp__rating"/>
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
        <span>{{ \Illuminate\Support\Str::limit($displayName, 46) }}</span>
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
    <section aria-labelledby="pdp-specs-h" @if($measure) data-md-scope @endif>
      <h2 id="pdp-specs-h">{{ __('Specifications') }} <small>{{ $p['sku'] }}{{ $seo['short'] ? ' · ' . $seo['short'] : '' }}</small></h2>
      @if($measure)
        <figure class="pdp-md"><figcaption><span>{{ __('How to measure') }}</span><span class="pdp-md__read" aria-live="polite">{{ __('Point at a letter to read its measurement') }}</span></figcaption>{!! \App\Support\Measure\Sketch::uniq($measure['svg']) !!}</figure>
      @endif
      @php $mdRow = function ($key) use ($measure) { $l = $measure['shown'][$key] ?? null; return $l ? [' data-md="' . e($key) . '"', '<span class="md-chip" aria-hidden="true">' . e($l) . '</span>'] : ['', '']; }; @endphp
      <dl class="uspecs">
        <div><dt class="k">{{ __('Part number') }}</dt><dd class="v">{{ $p['sku'] }}</dd></div>
        @if($seo['type'])<div><dt class="k">{{ __('Part type') }}</dt><dd class="v">{{ \App\Support\PartGlossary::t($seo['type']) }}</dd></div>@endif
        <div><dt class="k">{{ __('Condition') }}</dt><dd class="v">{{ $p['brand']==='OEM SPICER' ? __('New, OEM') : __('New') }}</dd></div>
        @if($p['brand'])@php [$mda, $mdc] = $mdRow('pa_brand'); @endphp<div{!! $mda !!}><dt class="k">{!! $mdc !!}{{ __('Brand') }}</dt><dd class="v">{{ $p['brand'] }}</dd></div>@endif
        @if($p['series'])@php [$mda, $mdc] = $mdRow('pa_series'); @endphp<div{!! $mda !!}><dt class="k">{!! $mdc !!}{{ __('Series') }}</dt><dd class="v">@if($seriesHub)<a href="/series/{{ $seriesHub['slug'] }}">{{ $p['series'] }}</a>@else{{ $p['series'] }}@endif</dd></div>@endif
        @if($p['style'])@php [$mda, $mdc] = $mdRow('pa_style'); @endphp<div{!! $mda !!}><dt class="k">{!! $mdc !!}{{ __('Style') }}</dt><dd class="v">{{ $p['style'] }}</dd></div>@endif
        @foreach(($p['specs'] ?? []) as $label => $val)
          @continue(in_array($label, ['Brand', 'Series', 'Style'], true) || $val === '')
          @php [$mda, $mdc] = $mdRow('pa_' . str_replace(' ', '-', strtolower($label))); @endphp
          <div{!! $mda !!}><dt class="k">{!! $mdc !!}{{ \App\Support\PartGlossary::specLabel($label, $p['specs']) }}</dt><dd class="v">{{ \App\Support\PartGlossary::specValue($label, (string) $val) }}</dd></div>
        @endforeach
        @if($p['weight'] !== '')<div><dt class="k">{{ __('Weight') }}</dt><dd class="v">{{ $p['weight'] }} lb</dd></div>@endif
      </dl>
    </section>

    <div class="pdp__side">
      <section aria-labelledby="pdp-fit-h">
        <h2 id="pdp-fit-h">{{ __('Fitment') }}</h2>
        <div class="pdp__notes">
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
            <p>{{ __('Send us the number off the old part and we will confirm the fit before you order.') }}</p>
          @endif
        </div>
        <div class="pdp__help">
          @if($seriesHub)<a class="textlink" href="/series/{{ $seriesHub['slug'] }}">{{ __('All :series series parts', ['series' => $seriesHub['name']]) }} <x-icon name="arrow-right"/></a>@endif
          @if($catPath)<a class="textlink" href="{{ $catPath }}">{{ __('All :type', ['type' => \App\Support\PartGlossary::t($cat['name'])]) }} <x-icon name="arrow-right"/></a>@endif
          <a class="textlink" href="/contact">{{ __('Ask a driveline tech about fitment') }} <x-icon name="arrow-right"/></a>
        </div>
      </section>
      @if(!empty($xref['xref']))
      <section class="pdp__xref" aria-labelledby="pdp-xref-h">
        <h2 id="pdp-xref-h">{{ __('Interchange / cross-reference') }}</h2>
        <ul class="xrefs">
          @foreach($xref['xref'] as $x)
            <li>@if($x['sku'])<a href="/parts/{{ rawurlencode($x['sku']) }}">{{ $x['num'] }}</a>@else{{ $x['num'] }}@endif</li>
          @endforeach
        </ul>
        <p>{{ __(':sku replaces or interchanges with :nums', ['sku' => $p['sku'], 'nums' => implode(', ', array_column($xref['xref'], 'num'))]) }}{{ __('. Cross-reference numbers are supplied for identification; compare the series, spline and bolt-pattern dimensions above before substituting.') }}</p>
      </section>
      @endif

      @if(!empty($bearingFits))
      {{-- the vehicles whose complete driveshafts use this bearing (PTI application data, App\Support\BearingFits) --}}
      <details class="pdp-fold pdp-fits" @if(count($bearingFits) <= 2) open @endif>
        <summary>{{ __('Vehicles this bearing is listed for') }} <small>{{ trans_choice(':n model|:n models', $n = array_sum(array_map(fn ($m) => count($m['models']), $bearingFits)), ['n' => $n]) }}</small></summary>
        <div class="pdp-fits__list">
          @foreach($bearingFits as $mk)
            <p><b>@if($mk['url'])<a href="{{ $mk['url'] }}">{{ $mk['make'] }}</a>@else{{ $mk['make'] }}@endif</b>
              @foreach($mk['models'] as $md)<span>@if($md['url'])<a href="{{ $md['url'] }}">{{ $md['model'] }}</a>@else{{ $md['model'] }}@endif {{ $md['years'] }}</span> @endforeach</p>
          @endforeach
          <p class="pdp-fits__note">{{ __('From the complete driveshafts listed for these vehicles. Confirm the bearing ID and the mount dimensions above before you order.') }}</p>
        </div>
      </details>
      @endif

      <details class="pdp-fold pdp-fold--est"><summary>{{ __('Estimate shipping to your ZIP') }}</summary>@includeIf('partials.pdp-estimator')</details>
    </div>
  </div>

  {{-- alternative parts: exact-fitment matches + same number under another brand, in the category table format --}}
  @if(!empty($sameFit))
  <section class="ualts" id="pdp-alts" aria-labelledby="pdp-alts-h">
    <h2 id="pdp-alts-h">{{ __('Alternative parts for :sku', ['sku' => $p['sku']]) }}</h2>
    <p>{{ __('Same fitment, another number or brand. Check the notes before you substitute.') }}</p>
    <div class="shop-bleed"><div id="shop-table" class="ptfit">@include('shop.partials.search-table', ['data' => ['items' => $sameFit], 'schema' => $altSchema])</div>@include('shop.partials.table-fit')</div>
  </section>
  @endif

  @includeIf('partials.pdp-also-bought')   {{-- customers also bought (commerce agent) --}}

  {{-- long description (WooCommerce content, sanitized) --}}
  @if($descriptionHtml)
  <details class="pdp-fold pdp__desc">
    <summary><h2 id="pdp-desc-h">{{ __('About :sku', ['sku' => $p['sku']]) }}</h2></summary>
    <div class="prose">{!! $descriptionHtml !!}</div>
  </details>
  @endif

  {{-- related parts: same series + category --}}
  @if($related)
  <section class="pdp__rel" aria-labelledby="pdp-rel-h">
    <h2 id="pdp-rel-h">{{ $p['series'] ? __('Related :type in the :series series', ['type' => (!empty($cat['name']) ? \App\Support\PartGlossary::t($cat['name']) : __('parts')), 'series' => $p['series']]) : __('Related :type', ['type' => (!empty($cat['name']) ? \App\Support\PartGlossary::t($cat['name']) : __('parts'))]) }}</h2>
    @if($catPath)<a class="textlink pdp__rel-all" href="{{ $catPath }}{{ $p['series'] && $seriesHub ? '?series=' . $seriesHub['slug'] : '' }}">{{ __('See the full table') }} <x-icon name="arrow-right"/></a>@endif
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
        <details><summary>{{ $f[0] }}</summary><p>{{ $f[1] }}</p></details>
      @endforeach
    </div>
  </section>
  @endif

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
