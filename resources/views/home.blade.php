@extends('layouts.store')
@section('title','Driveshaft Parts & Complete Driveshafts, Shipped Today | Universal Driveshafts')
@section('desc','U-joints, yokes, center support bearings, flanges and tubing in stock, 1310 to 1810 series. Complete driveshafts by vehicle, premade or built to your measurements. Ships same day from Florida.')
@push('preload')
<link rel="preload" as="image" type="image/avif" href="{{ asset('img/store/hero-m.avif') }}" media="(max-width: 760px)" fetchpriority="high">
<link rel="preload" as="image" type="image/avif" imagesrcset="{{ asset('img/store/hero-1280.avif') }} 1280w, {{ asset('img/store/hero-2000.avif') }} 2000w" imagesizes="100vw" media="(min-width: 761px)" fetchpriority="high">
@endpush
@section('content')
@php
  /* Home (2026 redesign). Totals come from the same caches as before: the finder (makes, sellable shafts) and the
     catalog composer ($udsCatalog: parts, categories, groups). Categories and best sellers come from config/uds.php. */
  $udsFinder = \Illuminate\Support\Facades\Cache::remember('uds_finder_totals', 3600, function () {
      try {
          return ['makes' => count(app(\App\Services\DriveshaftFinder::class)->makes()),
                  'shafts' => \App\Services\DriveshaftFinder::fitments()->distinct()->count('ds_fitments.part_number')];
      } catch (\Throwable $e) { return ['makes' => 43, 'shafts' => 4400]; }
  });
  $parts  = $udsCatalog['parts'] ?: 7500;
  $groups = collect($udsCatalog['groups'] ?? [])->keyBy('slug');
  $catPath = fn ($slug) => app(\App\Services\CatalogService::class)->categoryPath($slug);
  $groupCards = [
    ['complete-driveshafts', 'Complete driveshafts', 'By vehicle, premade or built to measure', 'complete-800'],
    ['driveshaft-components', 'Driveshaft components', 'U-joints, yokes, bearings, flanges, slips', 'components-800'],
    ['custom-cut-tubing', 'Custom cut tubing', 'Steel and aluminum, cut to your length', 'tubing-800'],
    ['small-parts', 'Small parts', 'Straps, bolts, zerks, snap rings, weights', 'smallparts-800'],
  ];
  $makes = ['ford' => 'Ford', 'chevrolet' => 'Chevrolet', 'dodge' => 'Dodge', 'jeep' => 'Jeep', 'toyota' => 'Toyota', 'gmc' => 'GMC'];
  // Cap, width and snap ring style come from the same table the part identifier and the guide use.
  $ujSpec = \App\Support\PartIdentifier::SERIES;
  $series = [];
  foreach ([
    '1310' => 'Half-ton trucks, Jeep, light duty',
    '1330' => 'Half and 3/4-ton Ford and Dodge',
    '1350' => '3/4-ton, Toyota, off-road builds',
    '1410' => 'One-ton, high-torque street',
    '1480' => 'One-ton, medium duty, drag',
    '1550' => 'Medium duty, Class 5 to 6',
    '7290' => 'Chrysler large, Ram, Dakota',
    '3R'   => 'GM Saginaw: C10, Camaro, S10',
  ] as $s => $use) {
    [$cap, $width, $ring] = $ujSpec[$s] ?? $ujSpec[$s . '/S44'];
    $series[] = [$s, number_format($cap, 3), number_format($width, 3), $use, $ring === 'inside' ? 'isr' : 'osr'];
  }
  $tiers = \App\Services\B2bAccounts::tiers();
  $pct = fn ($t) => rtrim(rtrim(number_format($tiers[$t]['pct'], 1), '0'), '.');
@endphp

{{-- ============ 1 · HERO: the photo, the promise and the three ways in ============ --}}
<section class="h-hero">
  <picture class="h-hero__bg">
    <source media="(max-width: 760px)" type="image/avif" srcset="{{ asset('img/store/hero-m.avif') }}">
    <source media="(max-width: 760px)" type="image/webp" srcset="{{ asset('img/store/hero-m.webp') }}">
    <source type="image/avif" srcset="{{ asset('img/store/hero-1280.avif') }} 1280w, {{ asset('img/store/hero-2000.avif') }} 2000w" sizes="100vw">
    <img src="{{ asset('img/store/hero-1280.webp') }}" srcset="{{ asset('img/store/hero-1280.webp') }} 1280w, {{ asset('img/store/hero-2000.webp') }} 2000w" sizes="100vw" width="2000" height="1000" alt="{{ __('A Ford Super Duty overland truck kicking up dust on a desert trail') }}" fetchpriority="high" decoding="async">
  </picture>
  <div class="st-wrap h-hero__in">
    <div class="h-hero__copy">
      <p class="h-chip"><span class="st-pulse" aria-hidden="true"></span>{{ __(':n parts on the shelf in Odessa, FL', ['n' => number_format($parts)]) }}</p>
      <h1 class="h-hero__t">{!! __('Driveshaft parts and complete driveshafts,') !!} <em>{!! __('shipped today.') !!}</em></h1>
      <p class="h-hero__lead">{!! __('U-joints, yokes, center bearings, flanges and tubing from 1310 to 1810 series, cross-referenced to the Spicer and OE numbers you already run.') !!} <span class="h-hero__more">{!! __('Or the complete shaft for your truck, ready to bolt in.') !!}</span></p>
    </div>

    <div class="h-con" data-console>
      <div class="h-con__tabs" role="tablist" aria-label="{{ __('How do you want to shop?') }}">
        <button role="tab" type="button" id="hc-t1" aria-controls="hc-p1" aria-selected="true" data-con-tab><x-icon name="crosshair"/> <span>{{ __('By vehicle') }}</span></button>
        <button role="tab" type="button" id="hc-t2" aria-controls="hc-p2" aria-selected="false" tabindex="-1" data-con-tab><x-icon name="search"/> <span>{{ __('By part number') }}</span></button>
        <button role="tab" type="button" id="hc-t3" aria-controls="hc-p3" aria-selected="false" tabindex="-1" data-con-tab><x-icon name="ruler"/> <span>{{ __('Build to measure') }}</span></button>
      </div>

      <div class="h-con__p" id="hc-p1" role="tabpanel" aria-labelledby="hc-t1"><form action="/catalog/driveshafts" method="get" data-finder autocomplete="off">
        <p class="h-con__t">{{ __('Find the complete driveshaft for your vehicle') }}</p>
        <div class="h-con__row">
          <label class="h-sel"><span>{{ __('Make') }}</span><select name="make" data-f="make" required><option value="">{{ __('Choose make') }}</option></select></label>
          <label class="h-sel"><span>{{ __('Year') }}</span><select name="year" data-f="year" disabled><option value="">{{ __('Year') }}</option></select></label>
          <label class="h-sel"><span>{{ __('Model') }}</span><select name="model" data-f="model" disabled><option value="">{{ __('Model') }}</option></select></label>
        </div>
        <button class="st-btn st-btn--red st-btn--lg st-btn--block" type="submit">{{ __('Find my driveshaft') }} <x-icon name="arrow-right"/></button>
        <div class="h-con__makes" aria-label="{{ __('Popular makes') }}">
          @foreach ($makes as $slug => $label)
            <button type="button" data-make="{{ $label }}" title="{{ $label }}">@if(file_exists(public_path("img/makes/$slug.png")))<img src="{{ media_url("/img/makes/$slug.png", 120) }}" alt="{{ $label }}" width="48" height="32" loading="lazy">@else{{ $label }}@endif</button>
          @endforeach
        </div>
        <p class="h-con__note"><x-icon name="check"/> {{ __(':n driveshafts across :m makes, each with its exact part number and price.', ['n' => number_format($udsFinder['shafts']), 'm' => $udsFinder['makes']]) }}</p>
      </form></div>

      <div class="h-con__p" id="hc-p2" role="tabpanel" aria-labelledby="hc-t2" hidden><form action="/search" method="get">
        <p class="h-con__t">{{ __('Search any part number. We cross-reference it.') }}</p>
        <label class="st-sr" for="hc-q">{{ __('Part number') }}</label>
        <div class="h-con__q">
          <input id="hc-q" name="q" type="search" placeholder="{{ __('Spicer, Neapco, OE or UDS number') }}" spellcheck="false" enterkeyhint="search">
          <button class="st-btn st-btn--red st-btn--lg" type="submit">{{ __('Search') }}</button>
        </div>
        <p class="h-con__chips"><span>{{ __('Try') }}</span>
          @foreach (['5-1310X', '5-1350X', '210088-1X', '2-28-447', '1350'] as $q)<a href="/search?q={{ urlencode($q) }}">{{ $q }}</a>@endforeach
        </p>
        <p class="h-con__note"><x-icon name="check"/> {{ __('Not sure of the number? Text a photo of the part to :phone and a tech will match it.', ['phone' => config('uds.stores.0.phone', '(813) 524-6491')]) }}</p>
      </form></div>

      <div class="h-con__p" id="hc-p3" role="tabpanel" aria-labelledby="hc-t3" hidden>
        <p class="h-con__t">{{ __('Your measurements, priced live, built in our Florida shop') }}</p>
        <ol class="h-con__steps">
          <li><b>1</b><span>{{ __('Duty and series') }}</span></li>
          <li><b>2</b><span>{{ __('Yokes at each end') }}</span></li>
          <li><b>3</b><span>{{ __('Six measurements') }}</span></li>
        </ol>
        <a class="st-btn st-btn--red st-btn--lg st-btn--block" href="/online-builder">{{ __('Start the online builder') }} <x-icon name="arrow-right"/></a>
        <p class="h-con__note"><x-icon name="check"/> {{ __('One piece or two, steel or aluminum. Balanced and checked before it ships.') }}</p>
      </div>
    </div>
  </div>

  <div class="h-proof">
    <div class="st-wrap h-proof__in">
      @php
        $gOn = (bool) \App\Support\Settings::get('reviews.badge_enabled', true);
        $gRate = (float) \App\Support\Settings::get('reviews.google_rating', 4.7);
        $gCount = (int) \App\Support\Settings::get('reviews.google_count', 200);
        $gUrl = trim((string) \App\Support\Settings::get('reviews.google_profile_url', \App\Support\ReviewRequests::PROFILE_URL));
      @endphp
      @if($gOn && $gRate >= 1 && $gCount > 0)
        <a class="g-rate h-proof__rate" href="{{ $gUrl }}" target="_blank" rel="noopener"><span class="g-rate__stars" style="--g-fill: {{ round($gRate / 5 * 100, 1) }}%" aria-hidden="true"><span>★★★★★</span></span><b class="g-rate__n">{{ number_format($gRate, 1) }}</b><span class="g-rate__t">{{ __(':n+ Google reviews', ['n' => number_format($gCount)]) }}</span></a>
      @endif
      <div><b>{{ number_format($parts) }}</b><span>{{ __('parts in stock') }}</span></div>
      <div><b>{{ number_format($udsFinder['shafts']) }}</b><span>{{ __('complete shafts by vehicle') }}</span></div>
      <div><b>{{ __('2pm ET') }}</b><span>{{ __('same-day cutoff') }}</span></div>
      <div><b>{{ __('Since 2018') }}</b><span>{{ __('building in Florida') }}</span></div>
    </div>
  </div>
</section>

{{-- ============ 2 · SHOP BY COMPONENT ============ --}}
<section class="h-sec">
  <div class="st-wrap">
    <div class="h-head">
      <div>
        <p class="st-eyebrow">{{ __('Parts catalog') }}</p>
        <h2 class="h-h2">{{ __('Shop by component') }}</h2>
      </div>
      <a class="st-link" href="/shop">{{ __('All :n categories', ['n' => $udsCatalog['cats'] ?: count($categories)]) }} <x-icon name="arrow-right"/></a>
    </div>
    <div class="h-groups">
      @foreach ($groupCards as [$slug, $label, $sub, $img])
        <a class="h-group" href="/shop/{{ $slug }}" data-rv>
          <picture><source type="image/avif" srcset="{{ asset("img/store/$img.avif") }}"><img src="{{ asset("img/store/$img.webp") }}" alt="" loading="lazy" decoding="async" width="800" height="600"></picture>
          <span class="h-group__b">
            <small>{{ __(':n parts', ['n' => number_format($groups[$slug]['count'] ?? 0)]) }}</small>
            <b>{{ __($label) }}</b>
            <span>{{ __($sub) }}</span>
          </span>
          <span class="h-group__go" aria-hidden="true"><x-icon name="arrow-right"/></span>
        </a>
      @endforeach
    </div>
    <div class="h-cats">
      @foreach (array_slice($categories, 0, 15) as [$name, $slug, $count, $img])
        <a class="h-cat" href="{{ $catPath($slug) }}"><span class="h-cat__img"><img src="{{ media_url($img, 160) }}" alt="" loading="lazy" decoding="async" width="56" height="56"></span><span><b>{{ __($name) }}</b><small>{{ __(':n parts', ['n' => number_format($count)]) }}</small></span></a>
      @endforeach
      <a class="h-cat h-cat--all" href="/shop"><span>{{ __('See every category') }}</span><x-icon name="arrow-right"/></a>
    </div>
  </div>
</section>

{{-- ============ 3 · BEST SELLERS ============ --}}
<section class="h-sec h-sec--tint">
  <div class="st-wrap">
    <div class="h-head">
      <div>
        <p class="st-eyebrow">{{ __('Moving fastest this month') }}</p>
        <h2 class="h-h2">{{ __('Best sellers, in stock now') }}</h2>
      </div>
      <div class="h-head__r">
        <div class="h-arrows"><button type="button" data-rail-prev aria-label="{{ __('Previous products') }}"><x-icon name="arrow-left"/></button><button type="button" data-rail-next aria-label="{{ __('More products') }}"><x-icon name="arrow-right"/></button></div>
        <a class="st-link" href="/shop/driveshaft-components">{{ __('Shop components') }} <x-icon name="arrow-right"/></a>
      </div>
    </div>
    <div class="h-rail" data-rail>
      @foreach ($bestsellers as [$name, $slug, $price, $img])
        @php $sku = strtok($name, ' '); $title = trim(substr($name, strpos($name, ' ') ?: 0)); @endphp
        <article class="h-prod">
          <a class="h-prod__media" href="/parts/{{ rawurlencode($sku) }}" tabindex="-1" aria-hidden="true"><img src="{{ media_url($img, 320) }}" alt="" loading="lazy" decoding="async" width="320" height="320"></a>
          <div class="h-prod__b">
            <span class="h-prod__sku">{{ $sku }}</span>
            <h3 class="h-prod__name"><a href="/parts/{{ rawurlencode($sku) }}">{{ $title }}</a></h3>
            <p class="h-prod__stock"><span class="st-dot" aria-hidden="true"></span>{{ __('In stock') }} <span>{{ __('· same day before 2pm ET') }}</span></p>
            <div class="h-prod__foot">
              <span class="h-prod__price">${{ number_format($price, 2) }}</span>
              <button type="button" class="h-prod__add" data-add-sku="{{ $sku }}" aria-label="{{ __('Add :sku to cart', ['sku' => $sku]) }}"><x-icon name="cart"/><span>{{ __('Add') }}</span></button>
            </div>
          </div>
        </article>
      @endforeach
    </div>
  </div>
</section>

{{-- ============ 4 · THREE WAYS TO THE RIGHT SHAFT ============ --}}
<section class="h-sec">
  <div class="st-wrap">
    <div class="h-head">
      <div>
        <p class="st-eyebrow">{{ __('Complete driveshafts') }}</p>
        <h2 class="h-h2">{{ __('Three ways to the right driveshaft') }}</h2>
      </div>
    </div>
    <div class="h-ways">
      <a class="h-way h-way--big" href="/catalog/driveshafts" data-rv>
        <picture><source type="image/avif" srcset="{{ asset('img/store/vehicle-1200.avif') }}"><img src="{{ asset('img/store/vehicle-1200.webp') }}" alt="{{ __('A lifted Jeep Gladiator on a forest road') }}" loading="lazy" decoding="async" width="1200" height="632"></picture>
        <span class="h-way__b">
          <small>{{ __('By vehicle') }}</small>
          <b>{{ __('Bolt-in shafts for :n makes', ['n' => $udsFinder['makes']]) }}</b>
          <span>{{ __('Pick make, year and model. You get the exact part number, price and stock, no guessing.') }}</span>
          <span class="st-btn st-btn--light">{{ __('Find by vehicle') }} <x-icon name="arrow-right"/></span>
        </span>
      </a>
      <a class="h-way" href="/premade-driveshafts" data-rv>
        <picture><source type="image/avif" srcset="{{ asset('img/store/stockroom-850.avif') }}"><img src="{{ asset('img/store/stockroom-850.webp') }}" alt="{{ __('Premade driveshafts ready to ship in the UDS stockroom') }}" loading="lazy" decoding="async" width="850" height="567"></picture>
        <span class="h-way__b">
          <small>{{ __('Ready to ship') }}</small>
          <b>{{ __('Premade driveshafts') }}</b>
          <span>{{ __('Common fitments, assembled and balanced, out the door the day you order.') }}</span>
          <span class="st-link st-link--light">{{ __('Shop premade') }} <x-icon name="arrow-right"/></span>
        </span>
      </a>
      <a class="h-way" href="/online-builder" data-rv>
        <picture><source type="image/avif" srcset="{{ asset('img/store/aluminum-1000.avif') }}"><img src="{{ asset('img/store/aluminum-1000.webp') }}" alt="{{ __('A custom aluminum driveshaft with a CV joint on the UDS bench') }}" loading="lazy" decoding="async" width="1000" height="667"></picture>
        <span class="h-way__b">
          <small>{{ __('To your measurements') }}</small>
          <b>{{ __('Build it online') }}</b>
          <span>{{ __('Duty, series, yokes and six measurements. The price updates as you go.') }}</span>
          <span class="st-link st-link--light">{{ __('Open the builder') }} <x-icon name="arrow-right"/></span>
        </span>
      </a>
    </div>
  </div>
</section>

{{-- ============ 5 · SHOP BY U-JOINT SERIES ============ --}}
<section class="h-sec h-sec--steel">
  <div class="st-wrap">
    <div class="h-head h-head--light">
      <div>
        <p class="st-eyebrow st-eyebrow--light">{{ __('Universal joints') }}</p>
        <h2 class="h-h2">{{ __('Know your series? Shop it.') }}</h2>
        <p class="h-sub">{{ __('Measure the cap and the width across the caps (in inches), match the drawing, and every yoke, joint and flange for that series is on one page.') }}</p>
      </div>
      <a class="st-link st-link--light" href="/series">{{ __('All series') }} <x-icon name="arrow-right"/></a>
    </div>
    @php
      $uidSeries = collect($ujSpec)->map(fn ($v, $k) => ['s' => explode('/', $k)[0], 'cap' => $v[0], 'w' => $v[1], 'ring' => $v[2]])->values();
    @endphp
    <form class="h-uid" data-uid data-series='@json($uidSeries)' data-wa="https://wa.me/18136445707" action="/series" onsubmit="return false">
      <p class="h-uid__t"><x-icon name="ruler"/> <span><b>{{ __('Not sure?') }}</b> {{ __('Measure it.') }}</span></p>
      <label class="h-uid__f"><span>{{ __('Cap diameter') }}</span><input type="text" inputmode="decimal" autocomplete="off" placeholder="1.062" data-uid-cap></label>
      <span class="h-uid__x" aria-hidden="true">×</span>
      <label class="h-uid__f"><span>{{ __('Width across caps') }}</span><input type="text" inputmode="decimal" autocomplete="off" placeholder="3.219" data-uid-w></label>
      <p class="h-uid__out" data-uid-out aria-live="polite">{{ __('Caliper on one cap, then across the caps. Inches or millimeters.') }}</p>
    </form>
    <div class="h-series" data-uid-grid>
      @foreach ($series as [$s, $cap, $width, $use, $ring])
        <a class="h-ser" href="/series/{{ $s }}" data-rv>
          <span class="h-ser__top"><span class="h-ser__n">{{ $s }}</span><span class="h-ser__ring">{{ $ring === 'isr' ? __('Inside snap ring') : __('Outside snap ring') }}</span></span>
          @include('partials.store.ujoint-drawing', ['series' => $s, 'class' => 'h-ser__dwg'])
          <span class="h-ser__use">{{ __($use) }}</span>
          <span class="h-ser__go">{{ __('Shop :s', ['s' => $s]) }} <x-icon name="arrow-right"/></span>
        </a>
      @endforeach
    </div>
  </div>
</section>

{{-- ============ 6 · BUILDER BAND ============ --}}
<section class="h-band">
  <picture class="h-band__bg"><source type="image/avif" srcset="{{ asset('img/store/weld-1600.avif') }}"><img src="{{ asset('img/store/weld-1600.webp') }}" alt="" loading="lazy" decoding="async" width="1600" height="900"></picture>
  <div class="st-wrap h-band__in">
    <div class="h-band__c" data-rv>
      <p class="st-eyebrow st-eyebrow--light">{{ __('Custom driveshafts') }}</p>
      <h2 class="h-h2">{{ __('No match for your truck? We build it.') }}</h2>
      <p>{{ __('Give the builder your series, yoke style and six measurements. It prices the exact assembly before you commit, and our shop builds, balances and ships it.') }}</p>
      <ul class="h-band__list">
        <li><x-icon name="check"/> {{ __('One piece or two piece, steel or aluminum tube') }}</li>
        <li><x-icon name="check"/> {{ __('Balanced and run-out checked before shipping') }}</li>
        <li><x-icon name="check"/> {{ __('Live price, no quote request, no waiting') }}</li>
      </ul>
      <div class="h-band__cta">
        <a class="st-btn st-btn--red st-btn--lg" href="/online-builder">{{ __('Start the online builder') }} <x-icon name="arrow-right"/></a>
        <a class="st-btn st-btn--ghost st-btn--lg" href="tel:+1{{ preg_replace('/\D/', '', config('uds.stores.0.phone', '8135246491')) }}"><x-icon name="phone"/> {{ __('Talk it through') }}</a>
      </div>
    </div>
  </div>
</section>

{{-- ============ 7 · WHOLESALE ============ --}}
<section class="h-sec">
  <div class="st-wrap h-b2b">
    <div class="h-b2b__media" data-rv>
      <picture><source type="image/avif" srcset="{{ asset('img/store/stockroom-850.avif') }}"><img src="{{ asset('img/store/stockroom-850.webp') }}" alt="{{ __('Driveshafts and parts on the shelves of the UDS stockroom') }}" loading="lazy" decoding="async" width="850" height="567"></picture>
      <p class="h-b2b__badge"><b>{{ number_format($parts) }}</b> {{ __('parts ready to ship to your bay') }}</p>
    </div>
    <div class="h-b2b__c">
      <p class="st-eyebrow">{{ __('For shops and fleets') }}</p>
      <h2 class="h-h2">{{ __('Wholesale pricing that grows with your volume') }}</h2>
      <p class="h-sub">{{ __('Approved repair shops, fleets and resellers get a trade discount on every order, applied automatically at checkout. Order by part number in seconds.') }}</p>
      <div class="h-tiers">
        <div class="h-tier"><span>{{ $tiers['bronze']['name'] }}</span><b>{{ $pct('bronze') }}%</b><small>{{ __('On approval') }}</small></div>
        <div class="h-tier"><span>{{ $tiers['silver']['name'] }}</span><b>{{ $pct('silver') }}%</b><small>{{ __('From $:n a month', ['n' => number_format($tiers['silver']['min'])]) }}</small></div>
        <div class="h-tier h-tier--top"><span>{{ $tiers['gold']['name'] }}</span><b>{{ $pct('gold') }}%</b><small>{{ __('From $:n a month', ['n' => number_format($tiers['gold']['min'])]) }}</small></div>
      </div>
      <div class="h-b2b__cta">
        <a class="st-btn st-btn--dark st-btn--lg" href="/wholesale">{{ __('Open a wholesale account') }} <x-icon name="arrow-right"/></a>
        <a class="st-link" href="/quick-order">{{ __('Quick order by part number') }} <x-icon name="arrow-right"/></a>
      </div>
    </div>
  </div>
</section>
@endsection
