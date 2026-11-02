@extends('layouts.app')
@section('robots','noindex,follow')
@section('canonical','/')
@section('title','Driveshaft Components & Driveshafts, U-Joints, Yokes | UDS')
@section('desc','2,200+ driveshaft components in stock, u-joints, yokes, center support bearings, flanges and tubing, plus complete driveshafts built and balanced in Florida. Same-day dispatch.')

@section('content')

{{-- 1. Hero ---------------------------------------------------------------- --}}
<section class="uhero">
  <div class="uhero__media">
    <picture>
      <source media="(max-width:700px)" type="image/webp" srcset="{{ asset('img/photos/hero-overland-sm.webp') }}">
      <source media="(max-width:700px)" srcset="{{ asset('img/photos/hero-overland-sm.jpg') }}">
      <source type="image/webp" srcset="{{ asset('img/photos/hero-overland.webp') }}">
      <img src="{{ asset('img/photos/hero-overland.jpg') }}" width="2000" height="1000"
           alt="An overland truck running hard across desert terrain" fetchpriority="high">
    </picture>
  </div>
  <div class="wrap uhero__content">
    <p class="uhero__kicker">Built in Florida</p>
    <h1>Driveshaft components and complete driveshafts.</h1>
    <p class="uhero__sub">U-joints, yokes, center support bearings, flanges and tubing on the shelf, and the finished shaft when you need the whole thing. Stock parts ship the same day.</p>
    <div class="uhero__cta">
      <a class="btn btn-primary btn-lg" href="/shop/driveshaft-components">Shop components <x-icon name="arrow-right"/></a>
      <a class="btn btn-ghost btn-lg" href="/shop/complete-driveshafts">Complete driveshafts</a>
    </div>
  </div>
</section>

{{-- 2. Assurance strip ------------------------------------------------------ --}}
<section class="ustrip">
  <div class="wrap">
    <div class="ustrip__item"><x-icon name="factory"/><div>Built in the USA<span>Balanced in our Florida shop</span></div></div>
    <div class="ustrip__item"><x-icon name="truck"/><div>Free over ${{ config('uds.free_shipping_min') }}<span>Same-day dispatch on stock</span></div></div>
    <div class="ustrip__item"><x-icon name="headset"/><div>Talk to a driveline tech<span>Not a call centre</span></div></div>
    <div class="ustrip__item"><x-icon name="returns"/><div>Prepaid returns<span>Wrong part, no argument</span></div></div>
  </div>
</section>

{{-- 2b. Proof: real catalogue numbers and the series buyers scan for --------- --}}
<section class="uproof">
  <div class="wrap">
    <div class="uproof__stats">
      <div><b>{{ number_format($udsCatalog['parts']) }}</b><span>parts on the shelf</span></div>
      <div><b>{{ number_format($udsCatalog['parts'] - 95) }}</b><span>component part numbers</span></div>
      <div><b>32</b><span>u-joint series</span></div>
      <div><b>2018</b><span>building since</span></div>
    </div>
    <div class="uproof__series">
      <p>Series we stock</p>
      <ul>
        <li>1310</li><li>1330</li><li>1350</li><li>1410</li><li>1480</li>
        <li>1550</li><li>1610</li><li>1710</li><li>1810</li>
        <li class="is-more">and 23 more</li>
      </ul>
    </div>
  </div>
</section>

{{-- 3. Two paths ----------------------------------------------------------- --}}
<section class="section">
  <div class="wrap">
    <div class="s-head">
      <h2>Buy the part, or the whole assembly</h2>
      <p>Most jobs are one component: a joint, a yoke, a bearing. When the shaft itself is the problem, find it by vehicle, buy it premade, or have us build it to spec.</p>
    </div>
    <div class="upaths reveal-stagger">
      <a class="upath" href="/catalog/driveshafts">
        <span class="upath__media">
          <img src="{{ asset('img/photos/tile-complete-driveshafts.webp') }}" width="1000" height="750" loading="lazy"
               alt="A complete driveshaft assembly ready for a vehicle">
        </span>
        <span class="upath__body">
          <b>Find the driveshaft for your vehicle</b>
          <span>Pick the year, make and model. 4,600+ fitments across 47 makes, each with its exact part number and price.</span>
          <span class="upath__go">Find by vehicle <x-icon name="arrow-right"/></span>
        </span>
      </a>
      <a class="upath" href="/online-builder">
        <span class="upath__media">
          <picture>
            <source srcset="{{ asset('img/photos/panel-custom.webp') }}" type="image/webp">
            <img src="{{ asset('img/photos/panel-custom.jpg') }}" width="849" height="566" loading="lazy"
                 alt="A two-piece and a one-piece driveshaft side by side on the bench">
          </picture>
        </span>
        <span class="upath__body">
          <b>Use the online driveshaft builder</b>
          <span>Pick duty, series and yokes, enter your measurements, see the price update as you go. One-piece or two, steel or aluminium.</span>
          <span class="upath__go">Open the builder <x-icon name="arrow-right"/></span>
        </span>
      </a>
      <a class="upath" href="/premade-driveshafts">
        <span class="upath__media">
          <picture>
            <source srcset="{{ asset('img/photos/panel-premade.webp') }}" type="image/webp">
            <img src="{{ asset('img/photos/panel-premade.jpg') }}" width="1200" height="800" loading="lazy"
                 alt="A finished driveshaft on the bench, ready to ship">
          </picture>
        </span>
        <span class="upath__body">
          <b>Buy a premade driveshaft</b>
          <span>Common fitments, already assembled and balanced, packed and out the door the day you order.</span>
          <span class="upath__go">Shop premade <x-icon name="arrow-right"/></span>
        </span>
      </a>
    </div>
  </div>
</section>

{{-- 4. Catalog: photographic group tiles, then the categories people search --}}
<section class="section section--tint">
  <div class="wrap">
    <div class="s-head s-head--between">
      <div>
        <h2>Shop by component</h2>
        <p>{{ number_format($udsCatalog['parts']) }} parts across {{ $udsCatalog['cats'] }} categories, cross-referenced to the series you already run.</p>
      </div>
      <a class="s-link" href="/shop">All categories <x-icon name="arrow-right"/></a>
    </div>

    @php $tilePhoto = ['complete-driveshafts'=>'tile-complete-driveshafts','driveshaft-components'=>'tile-driveshaft-components','custom-cut-tubing'=>'tile-steel-tube','small-parts'=>'small-parts']; @endphp
    <div class="ugroups reveal-stagger">
      @foreach ($udsCatalog['groups'] as $g)
        @php $ph = $tilePhoto[$g['slug']] ?? 'tile-steel-tube'; @endphp
        <a class="ugroup" href="/shop/{{ $g['slug'] }}">
          <picture>
            <source srcset="{{ asset('img/photos/'.$ph.'.webp') }}" type="image/webp">
            <img src="{{ asset('img/photos/'.$ph.'.jpg') }}" alt="" loading="lazy" width="1000" height="750">
          </picture>
          <span class="ugroup__n">{{ number_format($g['count']) }} parts</span>
          <b>{{ $g['label'] }}</b>
          <span class="ugroup__go">Shop group <x-icon name="arrow-right"/></span>
        </a>
      @endforeach
    </div>

    <div class="upop">
      @foreach (array_slice($categories, 0, 10) as [$name,$slug,$count,$img])
        <a href="/product-category/{{ $slug }}">{{ $name }} <span>{{ $count }}</span></a>
      @endforeach
      <a href="/shop">All {{ $udsCatalog['cats'] }} categories <x-icon name="arrow-right"/></a>
    </div>
  </div>
</section>

{{-- 5. Best sellers rail ---------------------------------------------------- --}}
<section class="section">
  <div class="wrap">
    <div class="s-head s-head--between">
      <div><h2>Moving fastest this month</h2></div>
      <a class="s-link" href="/shop">Full parts catalog <x-icon name="arrow-right"/></a>
    </div>
  </div>
  <div class="urail-wrap">
    <div class="wrap">
      <ul class="urail">
        @foreach ($bestsellers as [$name,$slug,$price,$img])
          <li class="uprod">
            <a class="uprod__media" href="/product/{{ $slug }}" tabindex="-1" aria-hidden="true">
              <img src="{{ $img }}" alt="" loading="lazy">
            </a>
            <div class="uprod__body">
              <span class="uprod__sku">{{ strtok($name,' ') }}</span>
              <a class="uprod__name" href="/product/{{ $slug }}">{{ trim(substr($name, strpos($name,' ') ?: 0)) }}</a>
              <div class="uprod__foot">
                <span class="uprod__price u-mono u-nums">${{ number_format($price,2) }}</span>
                <a class="uprod__add" href="/product/{{ $slug }}" aria-label="View {{ $name }}"><x-icon name="arrow-right"/></a>
              </div>
            </div>
          </li>
        @endforeach
      </ul>
    </div>
  </div>
</section>

{{-- 6. Builder band: full-bleed, photo backed, one white CTA ---------------- --}}
<section class="ubuilder">
  <div class="ubuilder__media">
    <picture>
      <source srcset="{{ asset('img/photos/band-fabrication.webp') }}" type="image/webp">
      <img src="{{ asset('img/photos/band-fabrication.jpg') }}" width="1800" height="1000" loading="lazy"
           alt="A welder joining steel driveshaft tube in the shop">
    </picture>
  </div>
  <div class="wrap ubuilder__content">
    <span class="ubuilder__tag"><x-icon name="ruler"/> Live pricing as you spec it</span>
    <h2>Cannot find your shaft? Give us the numbers.</h2>
    <p>Six measurements and your yoke style is all it takes. The builder prices the exact assembly before you commit.</p>
    <a class="btn btn-lg ubuilder__cta" href="/online-builder">Start the online builder <x-icon name="arrow-right"/></a>
    <ul class="ubuilder__specs">
      <li><x-icon name="gauge"/> One piece and two piece</li>
      <li><x-icon name="factory"/> Steel and aluminium tube</li>
      <li><x-icon name="certificate"/> Balanced and certified</li>
    </ul>
  </div>
</section>

@endsection
