@extends('layouts.app')
@section('title', __('Custom Driveshaft Online: Any Length, Live Price | UDS'))
@section('desc', __('Build a custom one-piece driveshaft online: tell us what it connects to, pick the series and material, enter the length and see the price. Built in Florida.'))
@section('content')
<link rel="stylesheet" href="/css/dsb.css?v={{ filemtime(public_path('css/dsb.css')) }}">
<link rel="stylesheet" href="/css/dsb-plan.css?v={{ filemtime(public_path('css/dsb-plan.css')) }}">

<div class="wrap pagehead bld-head">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <a href="/catalog/driveshafts">{{ __('Driveshafts') }}</a><x-icon name="caret-right"/>
    <span aria-current="page">{{ __('Online builder') }}</span>
  </nav>
  <div class="pagehead__row">
    <h1>{{ __('Build a custom driveshaft') }}</h1>
  </div>
  <p class="pagehead__sub">{{ __('Answer each question, enter your length and get the price. Built to order and balanced in our Florida shop.') }}</p>
</div>

<section class="section bldwrap"><div class="wrap">

<div class="ubs-dsb-wrap" id="ubs-dsb">
  <div class="dsb-layout">
  <div class="dsb-main">

  <div class="dsb-progress" aria-live="polite">
    <button type="button" class="dsb-back" data-back aria-label="{{ __('Back') }}"><x-icon name="arrow-left"/><span>{{ __('Back') }}</span></button>
    <div class="dsb-progress-text"><b data-progress-step>{{ __('Step 1') }}</b><span data-progress-name></span></div>
    <button type="button" class="dsb-restart" data-restart aria-label="{{ __('Start over') }}" hidden><x-icon name="refresh"/><span>{{ __('Start over') }}</span></button>
    <div class="dsb-bar" aria-hidden="true"><i data-progress-bar></i></div>
  </div>

  <div class="dsb-stage">

    {{-- Question tree (output type, make, model, spline count…), rendered from storage/app/builder-tree.json --}}
    <section class="dsb-step dsb-step--active" data-step="tree">
      <div class="dsb-step-head"><h2 class="dsb-step-title" data-tree-title></h2><p class="dsb-step-desc" data-tree-help hidden></p></div>
      <div class="dsb-guidebox" data-tree-guide hidden></div>
      <div class="dsb-tree" data-tree-options></div>
    </section>

    {{-- U-joint series --}}
    <section class="dsb-step" data-step="series">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('Which u-joint series?') }}</h2>
      <p class="dsb-step-desc">{{ __('Not sure? Measure a bearing cap with calipers: the cap diameter and the width across the joint tell the series apart.') }}</p></div>
      <div class="dsb-cards" id="dsb-series-cards"></div>
    </section>

    {{-- Material --}}
    <section class="dsb-step" data-step="material">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('Tube material') }}</h2>
      <p class="dsb-step-desc">{{ __('The material sets how long we can build the shaft for each tube diameter.') }}</p></div>
      <div class="dsb-cards dsb-cards--2" id="dsb-material-cards"></div>
    </section>

    {{-- Front end (heavy-duty families only: the light-duty tree already decided it) --}}
    <section class="dsb-step" data-step="front">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('Which end is at the transmission or transfer case?') }}</h2></div>
      <div class="dsb-app-cards" id="dsb-front-app-cards"></div>
    </section>

    {{-- Rear end --}}
    <section class="dsb-step" data-step="rear">
      <div class="dsb-step-head"><h2 class="dsb-step-title" id="dsb-rear-title">{{ __('What is on the differential end?') }}</h2></div>
      <div class="dsb-app-cards" id="dsb-rear-app-cards"></div>
    </section>

    {{-- Rear yoke: new / good used / reuse mine --}}
    <section class="dsb-step" data-step="rear_yoke">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('Flange yoke for this end') }}</h2>
      <p class="dsb-step-desc">{{ __('We balance the shaft with its yokes fitted, so tell us which one goes on.') }}</p></div>
      <div class="dsb-cards dsb-cards--3" data-yoke-choices="rear"></div>
    </section>

    {{-- New flange yoke: the vehicle make first, then the yokes our catalog lists for it --}}
    <section class="dsb-step" data-step="rear_brand">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('What make is the vehicle?') }}</h2>
      <p class="dsb-step-desc">{{ __('We show the flange yokes whose part data names that make. Not on the list, or not sure? See them all.') }}</p></div>
      <div class="dsb-cards dsb-cards--brands" data-brand-choices="rear"></div>
    </section>

    <section class="dsb-step" data-step="rear_pick">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('Pick the yoke') }}</h2><p class="dsb-step-desc" data-pick-desc="rear"></p></div>
      <div class="dsb-catalog" data-catalog-section="rear">
        <div class="dsb-catalog-filters" data-catalog-filters></div>
        <div class="dsb-catalog-grid" data-catalog-grid></div>
        <div class="dsb-pagination" data-catalog-pagination></div>
      </div>
    </section>

    {{-- Rear u-joint hardware --}}
    <section class="dsb-step" data-step="hardware">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('How does the rear u-joint attach to the pinion yoke?') }}</h2>
      <p class="dsb-step-desc">{{ __('Strap kits and u-bolt kits are series specific; we send the one you pick with the shaft.') }}</p></div>
      <div class="dsb-cards dsb-cards--3" data-hardware-choices></div>
      <div class="dsb-hw-list" data-hardware-list hidden></div>
    </section>

    {{-- Measurements --}}
    <section class="dsb-step" data-step="measure">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('Measurements') }}</h2>
      <p class="dsb-step-desc">{{ __('In inches, with the vehicle at ride height. A1 and C1 are both required.') }}</p></div>
      <div class="dsb-note" id="dsb-slip-note" hidden><x-icon name="info"/><span>{{ __('With a slip yoke, measure with the yoke pushed fully in (bottomed out), not in its installed position.') }}</span></div>
      <p class="dsb-guide" id="dsb-measure-help" hidden></p>
      <div class="dsb-plan" id="dsb-plan" hidden>
        <div class="dsb-plan-head"><span class="dsb-plan-code" id="dsb-plan-code"></span><span>{{ __('Tap a letter to type its value') }}</span></div>
        <div class="dsb-plan-svg" id="dsb-plan-svg"></div>
        <input class="dsb-plan-input" id="dsb-plan-input" type="text" inputmode="decimal" autocomplete="off" aria-label="{{ __('Measurement in inches') }}" hidden>
        <div class="dsb-plan-foot"><span>{{ __('Not to scale') }}</span><span>{{ __('Inches') }}</span></div>
      </div>
      <div class="dsb-measure-diagram" hidden><img id="dsb-diagram-img" src="" width="1715" height="708" loading="lazy" alt="{{ __('How to measure your driveshaft') }}"></div>
      <div class="dsb-fields">
        <div class="dsb-field" id="dsb-field-a1"><label for="dsb-inp-a1">{{ __('Length A1 (inches)') }} <span class="dsb-req">*</span></label>
          <input type="text" class="dsb-input" id="dsb-inp-a1" name="dsb_a1" placeholder="{{ __('e.g. 52.25') }}" inputmode="decimal" autocomplete="off" spellcheck="false">
          <div class="dsb-hint" data-hint="a1"></div></div>
        <div class="dsb-field" id="dsb-field-c1"><label for="dsb-inp-c1">{{ __('C1 (inches)') }} <span class="dsb-req">*</span></label>
          <input type="text" class="dsb-input" id="dsb-inp-c1" name="dsb_c1" placeholder="{{ __('e.g. 48.0') }}" inputmode="decimal" autocomplete="off" spellcheck="false">
          <div class="dsb-hint" data-hint="c1"></div></div>
      </div>
      <div class="dsb-length-check" id="dsb-length-check" hidden></div>
      <p class="dsb-guide dsb-guide--muted" id="dsb-angle-note" hidden></p>
      <div class="dsb-step-foot"><button type="button" class="dsb-btn dsb-btn--primary" data-next="measure">{{ __('Continue') }} <x-icon name="arrow-right"/></button></div>
    </section>

    {{-- Critical speed check (optional) --}}
    <section class="dsb-step" data-step="speed">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('Speed check (optional)') }}</h2>
      <p class="dsb-step-desc">{{ __('Every tube has a critical speed that depends on its diameter and length. Give us your top speed, axle ratio and tire diameter and we check the shaft RPM against it.') }}</p></div>
      <div class="dsb-fields dsb-fields--3">
        <div class="dsb-field"><label for="dsb-speed-mph">{{ __('Top speed (mph)') }}</label><input type="number" class="dsb-input" id="dsb-speed-mph" min="5" max="250" step="1" inputmode="numeric" placeholder="85"></div>
        <div class="dsb-field"><label for="dsb-speed-ratio">{{ __('Axle ratio') }}</label><input type="text" class="dsb-input" id="dsb-speed-ratio" inputmode="decimal" autocomplete="off" placeholder="3.73"></div>
        <div class="dsb-field"><label for="dsb-speed-tire">{{ __('Tire diameter (in)') }}</label><input type="text" class="dsb-input" id="dsb-speed-tire" inputmode="decimal" autocomplete="off" placeholder="31"></div>
      </div>
      <div class="dsb-speed-result" id="dsb-speed-result" hidden></div>
      <div class="dsb-step-foot"><button type="button" class="dsb-btn dsb-btn--primary" data-next="speed">{{ __('Continue') }} <x-icon name="arrow-right"/></button><button type="button" class="dsb-btn dsb-btn--ghost" data-skip="speed">{{ __('Skip') }}</button></div>
    </section>

    {{-- Build options of a table-priced product (hardware kit, build options, length answer) --}}
    <section class="dsb-step" data-step="options">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('Build options') }}</h2>
      <p class="dsb-step-desc" data-options-product></p></div>
      <div class="dsb-options" data-options></div>
    </section>

    {{-- Front yoke: new / good used / reuse mine --}}
    <section class="dsb-step" data-step="front_yoke">
      <div class="dsb-step-head"><h2 class="dsb-step-title" id="dsb-front-yoke-title">{{ __('Slip yoke for this shaft') }}</h2>
      <p class="dsb-step-desc" id="dsb-front-yoke-desc"></p></div>
      <div class="dsb-cards dsb-cards--3" data-yoke-choices="front"></div>
      <div class="dsb-field dsb-field--inline" id="dsb-front-model-wrap">
        <label for="dsb-front-model">{{ __('Transmission or transfer case model (optional)') }}</label>
        <input type="text" class="dsb-input" id="dsb-front-model" maxlength="60" placeholder="{{ __('e.g. 4L60E, NP241') }}" autocomplete="off">
      </div>
    </section>

    <section class="dsb-step" data-step="front_brand">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('What make is the vehicle?') }}</h2>
      <p class="dsb-step-desc">{{ __('We show the flange yokes whose part data names that make. Not on the list, or not sure? See them all.') }}</p></div>
      <div class="dsb-cards dsb-cards--brands" data-brand-choices="front"></div>
    </section>

    <section class="dsb-step" data-step="front_pick">
      <div class="dsb-step-head"><h2 class="dsb-step-title">{{ __('Pick the yoke') }}</h2><p class="dsb-step-desc" data-pick-desc="front"></p></div>
      <div class="dsb-catalog" data-catalog-section="front">
        <div class="dsb-catalog-filters" data-catalog-filters></div>
        <div class="dsb-catalog-grid" data-catalog-grid></div>
        <div class="dsb-pagination" data-catalog-pagination></div>
      </div>
    </section>

    {{-- Review: the configured shaft as a product --}}
    <section class="dsb-step" data-step="review">
      <article class="dsb-product">
        <div class="dsb-product-media">
          <figure class="dsb-product-main"><div class="dsb-plan-svg dsb-review-plan" id="dsb-review-plan"></div><img id="dsb-review-diagram-img" hidden alt="{{ __('Configuration diagram') }}"></figure>
        </div>
        <div class="dsb-product-info">
          <h2 class="dsb-product-title" id="dsb-review-title">{{ __('Custom driveshaft') }}</h2>
          <p class="dsb-product-sub" id="dsb-review-sub"></p>
          <div class="dsb-product-price"><span id="dsb-review-price">$0.00</span><small>{{ __('Built to your measurements, balanced before it ships') }}</small></div>
          <div class="dsb-tier" id="dsb-tier">
            <button type="button" class="dsb-tier-opt dsb-tier-opt--on" data-select-tier="certified"><b>{{ __('Certified') }}</b><span>{{ __('Certified reconditioned components') }}</span><em data-tier-price="certified">$0.00</em></button>
            <button type="button" class="dsb-tier-opt" data-select-tier="new"><b>{{ __('Brand new') }}</b><span>{{ __('All new components') }}</span><em data-tier-price="new">$0.00</em></button>
          </div>
          <dl class="dsb-breakdown" id="dsb-breakdown"></dl>
          <div class="dsb-buy">
            <div class="dsb-qty"><label for="dsb-qty-review">{{ __('Qty') }}</label><button type="button" data-qty="-" aria-label="{{ __('Decrease quantity') }}"><x-icon name="minus"/></button><input type="number" class="dsb-qty-input" id="dsb-qty-review" value="1" min="1" step="1"><button type="button" data-qty="+" aria-label="{{ __('Increase quantity') }}"><x-icon name="plus"/></button></div>
            <button type="button" class="dsb-btn dsb-btn--primary dsb-btn--lg" data-add-btn disabled><x-icon name="cart"/> {{ __('Add to cart') }}</button>
            <button type="button" class="dsb-btn dsb-btn--ghost" data-save-btn><x-icon name="heart"/> {{ __('Save design') }}</button>
          </div>
          <div class="dsb-speed-result dsb-speed-result--review" id="dsb-review-speed" hidden></div>
          <p class="dsb-guide dsb-guide--muted" id="dsb-review-angle" hidden></p>
          <p class="dsb-own-note" id="dsb-own-note" hidden></p>
          <div class="dsb-notes"><label for="dsb-notes">{{ __('Anything we should know? (optional)') }}</label>
            <textarea rows="2" id="dsb-notes" name="dsb_notes" maxlength="2000" placeholder="{{ __('Lift, angles, horsepower, a part number you already have…') }}"></textarea></div>
          <ul class="dsb-policy">
            <li>{{ __('12-month limited warranty on UDS-brand products; custom shafts are warranted built to your specifications and balanced.') }}</li>
            <li>{{ __('Custom builds are made to your measurements and cannot be returned once fabrication begins.') }}</li>
            <li>{{ __('Lead time is confirmed with you after ordering.') }}</li>
          </ul>
          <p class="dsb-call">{{ __('Questions before you order?') }} <a href="{{ \App\Http\Controllers\DsbController::PHONE_HREF }}"><x-icon name="phone"/> {{ \App\Http\Controllers\DsbController::PHONE }}</a></p>
        </div>
        <div class="dsb-product-specs">
          <h3>{{ __('Specifications') }}</h3>
          <dl class="dsb-spec-list" id="dsb-review-specs"></dl>
          <div class="dsb-share"><label for="dsb-share-url">{{ __('Share or save this build') }}</label>
            <div class="dsb-share-row"><input type="text" id="dsb-share-url" readonly data-share-url onclick="this.select()"><button type="button" class="dsb-btn dsb-btn--ghost" data-action="copy">{{ __('Copy') }}</button></div></div>
        </div>
      </article>
    </section>

    {{-- Confirmation (after add to cart) --}}
    <div class="dsb-confirm" id="dsb-confirm" hidden>
      <div class="dsb-confirm-badge"><x-icon name="check"/></div>
      <h2 class="dsb-step-title">{{ __('Added to cart') }}</h2>
      <p class="dsb-step-desc" id="dsb-confirm-summary"></p>
      <p class="dsb-step-desc">{{ __('Total:') }} <b id="dsb-confirm-price"></b></p>
      <div class="dsb-confirm-actions">
        <a href="/cart" class="dsb-btn dsb-btn--primary"><x-icon name="cart"/> {{ __('View cart and check out') }}</a>
        <a href="/online-builder" class="dsb-btn dsb-btn--ghost">{{ __('Build another') }}</a>
      </div>
    </div>

  </div>
  </div>{{-- /dsb-main --}}

  <aside class="dsb-sheet" id="dsb-sheet" aria-label="{{ __('Your build') }}" hidden>
    <div class="dsb-sheet-head"><b>{{ __('Your build') }}</b><span>{{ __('One piece') }} · {{ __('inches') }}</span></div>
    <div class="dsb-sheet-photo" id="dsb-sheet-photo" hidden></div>
    <div class="dsb-sheet-draw" id="dsb-sheet-draw"></div>
    <div class="dsb-sheet-badge" data-sheet-badge></div>
    <dl class="dsb-sheet-specs" id="dsb-sheet-specs"></dl>
    <div class="dsb-sheet-price">
      <span class="dsb-sheet-price-label">{{ __('Price') }}</span>
      <span class="dsb-sheet-price-value" data-sheet-price>{{ __('Price after measurements') }}</span>
      <small data-sheet-price-note></small>
    </div>
    @include('partials.dsb-cart-footer')
  </aside>
  </div>{{-- /dsb-layout --}}

  {{-- the build so far and its price, always in view; no drawing preview (owner, 2026-11) --}}
  <div class="dsb-mbar bld-dock" aria-live="polite">
    <span class="bld-dock__sum" data-dock-sum>{{ __('Your answers appear here') }}</span>
    <span class="bld-dock__price"><small>{{ __('Price') }}</small><b data-mbar-price>—</b></span>
  </div>
</div>

  <p class="bld-help">{{ __('Not sure about a measurement or a part?') }} <a href="{{ \App\Http\Controllers\DsbController::PHONE_HREF }}"><x-icon name="phone"/> {{ \App\Http\Controllers\DsbController::PHONE }}</a> · <a href="https://wa.me/18136445707" target="_blank" rel="noopener">WhatsApp</a> · {{ __('or bring the shaft to the shop and we measure it.') }}</p>
</div></section>
<script>window.dsbConfig = @json($config);
window.UDS_T = Object.assign(window.UDS_T || {}, @json(app()->getLocale() === 'en' ? (object) [] : \App\Http\Controllers\DsbController::jsStrings($config)));</script>
<script src="/js/dsb.js?v={{ filemtime(public_path('js/dsb.js')) }}"></script>
<script>
(function(){
  var specs=document.getElementById('dsb-sheet-specs'), sum=document.querySelector('[data-dock-sum]'); if(!specs||!sum) return;
  var idle=sum.textContent;
  function sync(){ var v=[].map.call(specs.querySelectorAll('dd'),function(d){return d.textContent.replace(/\s*Edit\s*$/,'').trim();}).filter(Boolean); sum.textContent=v.length?v.join('  ·  '):idle; }
  new MutationObserver(sync).observe(specs,{childList:true,subtree:true,characterData:true}); sync();
})();
</script>
@endsection
