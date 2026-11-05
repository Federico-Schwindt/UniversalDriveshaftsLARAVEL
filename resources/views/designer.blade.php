@extends('layouts.app')
@section('title', $pieces === 'two' ? __('Custom Two-Piece Driveshaft Online, Live Price | UDS') : __('Custom Driveshaft Online: Any Length, Live Price | UDS'))
@section('desc', $pieces === 'two' ? __('Build a two-piece driveshaft online: center support bearing, series, yokes and your measurements priced live, built and balanced in Florida.') : __('Build a custom one-piece driveshaft online. Pick duty, series and yokes, enter six measurements and see the price before you order. Built in Florida.'))
@section('content')
<link rel="stylesheet" href="/css/dsb.css?v={{ filemtime(public_path('css/dsb.css')) }}">
<link rel="stylesheet" href="/css/dsb-plan.css?v={{ filemtime(public_path('css/dsb-plan.css')) }}">

<div class="wrap">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <a href="/shop">{{ __('Resources') }}</a><x-icon name="caret-right"/>
    <span aria-current="page">{{ __('Online builder') }}</span>
  </nav>
</div>

<header class="bldhead">
  <div class="wrap">
    <h1>{{ __('Driveshaft online builder') }}</h1>
    <p>{{ __('Pick duty, series and yokes, enter your measurements, and see the price of the exact assembly before you commit.') }} {{ $pieces === 'two' ? __('Two-piece build.') : __('One-piece build.') }}</p>
    <div class="bldhead__switch" role="group" aria-label="{{ __('Driveshaft type') }}">
      <a href="/online-builder" @class(['is-on' => $pieces !== 'two'])>{{ __('One piece') }}</a>
      <a href="/online-builder/two-piece" @class(['is-on' => $pieces === 'two'])>{{ __('Two piece') }}</a>
    </div>
  </div>
</header>

<section class="section bldwrap"><div class="wrap">

<div class="ubs-dsb-wrap" id="ubs-dsb">

  <div class="dsb-layout">
  <div class="dsb-main">
  <nav class="dsb-crumbs" aria-label="{{ __('Builder steps') }}">
    <button type="button" class="dsb-crumb" data-goto="16" data-field="build_mode"><span class="dsb-crumb-num">1</span><span><span class="dsb-crumb-label">{{ __('Build type') }}</span><span class="dsb-crumb-value">{{ __('Not selected') }}</span></span></button>
    <button type="button" class="dsb-crumb" data-goto="1" data-field="duty"><span class="dsb-crumb-num">2</span><span><span class="dsb-crumb-label">{{ __('Duty rating') }}</span><span class="dsb-crumb-value">{{ __('Not selected') }}</span></span></button>
    <button type="button" class="dsb-crumb" data-goto="2" data-field="series"><span class="dsb-crumb-num">3</span><span><span class="dsb-crumb-label">{{ __('Series') }}</span><span class="dsb-crumb-value">{{ __('Not selected') }}</span></span></button>
    <button type="button" class="dsb-crumb" data-goto="4" data-field="measure"><span class="dsb-crumb-num">4</span><span><span class="dsb-crumb-label">{{ __('Measurements') }}</span><span class="dsb-crumb-value">{{ __('Not selected') }}</span></span></button>
    <button type="button" class="dsb-crumb" data-goto="6" data-field="review"><span class="dsb-crumb-num"><x-icon name="check"/></span><span><span class="dsb-crumb-label">{{ __('Review') }}</span><span class="dsb-crumb-value">{{ __('Pending') }}</span></span></button>
  </nav>

  <div class="dsb-stage">

    {{-- STEP 16 ,  Build Mode --}}
    <section class="dsb-step dsb-step--active" data-step="16">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('How would you like to proceed?') }}</h4>
      <p class="dsb-step-desc">{{ __('Open the online builder to your exact specifications, or browse our ready-to-ship stock.') }}</p></div></div>
      <div class="dsb-mode-cards">
        <button type="button" class="dsb-mode-card" data-mode="custom"><span class="dsb-mode-icon"><x-icon name="ruler"/></span><span class="dsb-mode-title">{{ __('Build a custom length driveshaft') }}</span><span class="dsb-mode-desc">{{ __('Specify your exact measurements and yoke style. We build it to your exact specs. Ideal when the vehicle is modified or you need a non-standard length.') }}</span><span class="dsb-mode-cta">{{ __('Start building') }} <x-icon name="arrow-right"/></span></button>
        <button type="button" class="dsb-mode-card" data-mode="premade"><span class="dsb-mode-icon"><x-icon name="package"/></span><span class="dsb-mode-title">{{ __('Browse premade driveshafts') }}</span><span class="dsb-mode-desc">{{ __('Shop our driveshafts filtered for your selected series. Made for factory-length replacements, 1 to 2 business days to ship.') }}</span><span class="dsb-mode-cta">{{ __('Browse catalog') }} <x-icon name="arrow-right"/></span></button>
      </div>
    </section>

    {{-- STEP 1 ,  Duty --}}
    <section class="dsb-step" data-step="1">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Select Duty rating') }}</h4>
      <p class="dsb-step-desc">{{ __('Choose the duty class that matches your vehicle or application.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back="16"><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-cards" id="dsb-duty-cards"></div>
    </section>

    {{-- STEP 13 ,  HD Subcategory --}}
    <section class="dsb-step" data-step="13">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Select Subcategory') }}</h4>
      <p class="dsb-step-desc">{{ __('Choose the product family for your heavy-duty application.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back="1"><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-cards" id="dsb-subcat-cards"></div>
    </section>

    {{-- STEP 2 ,  Series --}}
    <section class="dsb-step" data-step="2">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Select Series') }}</h4>
      <p class="dsb-step-desc">{{ __('Pick the series that matches your driveshaft.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back="1"><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-search"><input type="search" id="dsb-series-search" placeholder="{{ __('Search series (e.g. 1310, SPL, GM)') }}"><button type="button" class="dsb-search-clear" hidden><x-icon name="x"/></button></div>
      <p class="dsb-count" data-count></p>
      <div class="dsb-cards" id="dsb-series-cards"></div>
    </section>

    {{-- STEP 19 ,  Material --}}
    <section class="dsb-step" data-step="19">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Select Driveshaft Material') }}</h4>
      <p class="dsb-step-desc">{{ __('The material affects the maximum build length available for each tube OD. Aluminum supports longer builds; Steel is heavier-duty for standard applications.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back-auto><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-mode-cards">
        <button type="button" class="dsb-mode-card" data-material="steel"><span class="dsb-mode-icon"><x-icon name="sliders"/>️</span><span class="dsb-mode-title">{{ __('Steel') }}</span><span class="dsb-mode-desc">{{ __('Standard steel construction ,  heavier, strong, and cost-effective. Tube OD availability limits max build length depending on series.') }}</span><span class="dsb-mode-cta">{{ __('Select Steel') }} <x-icon name="arrow-right"/></span></button>
        <button type="button" class="dsb-mode-card" data-material="aluminum"><span class="dsb-mode-icon"><x-icon name="gauge"/></span><span class="dsb-mode-title">{{ __('Aluminum') }}</span><span class="dsb-mode-desc">{{ __('Lightweight aluminum construction ,  allows longer builds on certain series. Ideal for high-speed and performance applications.') }}</span><span class="dsb-mode-cta">{{ __('Select Aluminum') }} <x-icon name="arrow-right"/></span></button>
      </div>
    </section>

    {{-- STEP 14 ,  Front App --}}
    <section class="dsb-step" data-step="14">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Select Your Front Application') }}</h4>
      <p class="dsb-step-desc">{{ __('Choose the front yoke style for your driveshaft.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back-auto><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-app-cards" id="dsb-front-app-cards"></div>
    </section>

    {{-- STEP 15 ,  Rear App --}}
    <section class="dsb-step" data-step="15">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Choose Your Rear End Application') }}</h4>
      <p class="dsb-step-desc">{{ __('Select the rear yoke style based on your front selection.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back="14"><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-app-cards" id="dsb-rear-app-cards"></div>
    </section>

    {{-- STEP 4 ,  Measurements --}}
    <section class="dsb-step" data-step="4">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Measurements') }}</h4>
      <p class="dsb-step-desc">{{ __('Provide the dimensions so we can confirm the right driveshaft.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back-auto><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-material-warning" id="dsb-material-warning" hidden><x-icon name="warning"/> {{ __('It is not safe to build a driveshaft with that length on this material due to the availability of tube OD. Please try with Aluminum.') }}</div>
      <div class="dsb-info-banner" id="dsb-slip-note" hidden><span><x-icon name="info"/></span><span>{{ __('Measure the driveshaft length with the slip yoke fully collapsed (bottomed out), not in its installed or working position. This ensures the maximum compressed length is captured accurately. Do not measure with the slip yoke extended, as this can result in an incorrect overall length and improper fitment once installed.') }}</span></div>
      <div class="dsb-plan" id="dsb-plan" hidden>
        <div class="dsb-plan-head"><span><x-icon name="ruler"/> {{ __('Click a dimension on the drawing and type it in inches. Enter moves to the next one.') }}</span><span class="dsb-plan-code" id="dsb-plan-code"></span></div>
        <div class="dsb-plan-svg" id="dsb-plan-svg"></div>
        <input class="dsb-plan-input" id="dsb-plan-input" type="text" inputmode="decimal" autocomplete="off" aria-label="{{ __('Measurement in inches') }}" hidden>
        <div class="dsb-plan-foot"><span>{{ __('Not to scale') }}</span><span>{{ __('Inches') }}</span></div>
      </div>
      <div class="dsb-measure-diagram"><img id="dsb-diagram-img" src="/img/builder/diagram-measure-takeoff-drivesahfts.webp" width="1380" height="752" loading="lazy" alt="{{ __('How to measure your driveshaft') }}" crossorigin="anonymous"><canvas class="dsb-diagram-canvas" id="dsb-diagram-canvas"></canvas></div>
      <div class="dsb-fields">
        <div class="dsb-field" id="dsb-field-a1"><label>{{ __('Measurement A1 (inches)') }} <span class="dsb-req">*</span></label>
          <input type="number" step="0.125" min="1" class="dsb-input" id="dsb-inp-a1" name="dsb_a1" placeholder="{{ __('e.g. 24.5') }}">
          <div class="dsb-hint">{{ __('See diagram above for A1 measurement point.') }}</div></div>
        <div class="dsb-field" id="dsb-field-c1"><label>{{ __('Measurement C1 (inches)') }} <span class="dsb-opt">{{ __('(optional)') }}</span></label>
          <input type="number" step="0.125" min="1" class="dsb-input" id="dsb-inp-c1" name="dsb_c1" placeholder="{{ __('e.g. 12.0') }}">
          <div class="dsb-hint">{{ __('See diagram above for C1 measurement point.') }}</div></div>
      </div>
      <div class="dsb-step-foot"><button type="button" class="dsb-btn dsb-btn--primary" data-next="measure">{{ __('Continue') }} <x-icon name="arrow-right"/></button></div>
    </section>

    {{-- STEP 7 ,  Front yoke ask --}}
    <section class="dsb-step" data-step="7">
      <div class="dsb-yoke-ask">
        <span class="dsb-ask-icon"><x-icon name="info"/></span>
        <h4 class="dsb-step-title" id="dsb-yoke7-title">{{ __('Do you need a Flange Yoke?') }}</h4>
        <p class="dsb-step-desc" id="dsb-yoke7-desc">{{ __('A flange yoke connects the driveshaft to the differential. Choose to add one from our shop or catalog, or skip this step.') }}</p>
        <div class="dsb-yoke-choice">
          <button type="button" class="dsb-btn dsb-btn--primary dsb-btn--lg" data-yoke-answer="flange:yes" id="dsb-yoke7-add"><x-icon name="plus"/> {{ __('Add a Flange Yoke to the Order') }}</button>
          <button type="button" class="dsb-btn dsb-btn--secondary dsb-btn--lg" data-yoke-answer="flange:no">{{ __('No, use one from the shop') }}</button>
        </div>
      </div>
    </section>

    {{-- STEP 9 ,  Front yoke catalog --}}
    <section class="dsb-step" data-step="9">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Select a Yoke') }}</h4>
      <p class="dsb-step-desc">{{ __('Browse and filter our catalog. Click a product to add it.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back="4"><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-catalog" data-catalog-section="flange">
        <div class="dsb-catalog-filters" data-catalog-filters></div>
        <div class="dsb-catalog-grid" data-catalog-grid></div>
        <div class="dsb-pagination" data-catalog-pagination></div>
      </div>
    </section>

    {{-- STEP 10 ,  Rear yoke ask --}}
    <section class="dsb-step" data-step="10">
      <div class="dsb-yoke-ask">
        <span class="dsb-ask-icon"><x-icon name="info"/></span>
        <h4 class="dsb-step-title" id="dsb-yoke10-title">{{ __('Do you need a Transmission Yoke?') }}</h4>
        <p class="dsb-step-desc" id="dsb-yoke10-desc">{{ __('A transmission yoke connects the driveshaft to the transfer case or transmission. Choose to add one or skip.') }}</p>
        <div class="dsb-yoke-choice">
          <button type="button" class="dsb-btn dsb-btn--primary dsb-btn--lg" data-yoke-answer="trans:yes" id="dsb-yoke10-add"><x-icon name="plus"/> {{ __('Add a Transmission Yoke to the Order') }}</button>
          <button type="button" class="dsb-btn dsb-btn--secondary dsb-btn--lg" data-yoke-answer="trans:no">{{ __('No, use one from the shop') }}</button>
        </div>
      </div>
    </section>

    {{-- STEP 12 ,  Rear yoke catalog --}}
    <section class="dsb-step" data-step="12">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Select a Yoke') }}</h4>
      <p class="dsb-step-desc">{{ __('Browse and filter our catalog. Click a product to add it.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back="4"><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-catalog" data-catalog-section="trans">
        <div class="dsb-catalog-filters" data-catalog-filters></div>
        <div class="dsb-catalog-grid" data-catalog-grid></div>
        <div class="dsb-pagination" data-catalog-pagination></div>
      </div>
    </section>

    {{-- STEP 17 ,  Premade catalog --}}
    <section class="dsb-step" data-step="17">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Select a Pre-Made Driveshaft') }}</h4>
      <p class="dsb-step-desc" id="dsb-premade-desc">{{ __('Driveshafts matching your series selection. Select one to add to your order.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back="2"><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div class="dsb-premade-toolbar"><label>{{ __('Filter by Overall Length:') }}</label>
        <select id="dsb-premade-length-filter" class="dsb-input dsb-catalog-select"><option value="">{{ __('All Lengths') }}</option></select></div>
      <div class="dsb-premade-grid" id="dsb-premade-grid"><div class="dsb-loading">{{ __('Loading…') }}</div></div>
      <div class="dsb-pagination" id="dsb-premade-pagination"></div>
    </section>

    {{-- STEP 18 ,  Premade measurements --}}
    <section class="dsb-step" data-step="18">
      <div class="dsb-step-head"><div><h4 class="dsb-step-title">{{ __('Measurements') }}</h4>
      <p class="dsb-step-desc">{{ __('Please provide your measurements for this driveshaft.') }}</p></div>
      <button type="button" class="dsb-btn dsb-btn--secondary" data-back="17"><x-icon name="arrow-left"/> {{ __('Back') }}</button></div>
      <div id="dsb-epo-fields-container"></div>
      <div class="dsb-step-foot"><button type="button" class="dsb-btn dsb-btn--primary" data-next="epo">{{ __('Continue to Review') }} <x-icon name="arrow-right"/></button></div>
    </section>

    {{-- STEP 6 ,  Review --}}
    <section class="dsb-step" data-step="6">
      <div class="dsb-review">
        <div class="dsb-review-badge"><x-icon name="check"/></div>
        <h4 class="dsb-step-title">{{ __('Configuration Ready') }}</h4>
        <p class="dsb-step-desc">{{ __('Your selection is attached to this item and will be saved with the order and confirmation email.') }}</p>
        <dl class="dsb-review-list">
          <div class="dsb-review-row"><dt>{{ __('Duty Type') }}</dt><dd data-field="duty-full">, </dd></div>
          <div class="dsb-review-row"><dt>{{ __('Series') }}</dt><dd data-field="series-full">, </dd></div>
          <div class="dsb-review-row"><dt>{{ __('Material') }}</dt><dd data-field="material-full">, </dd></div>
          <div class="dsb-review-row"><dt>{{ __('Product') }}</dt><dd data-field="premade-name">, </dd></div>
          <div class="dsb-review-row"><dt>{{ __('Measurements') }}</dt><dd data-field="premade-measure">, </dd></div>
          <div class="dsb-review-row"><dt>{{ __('Front Application') }}</dt><dd data-field="front-app-full">, </dd></div>
          <div class="dsb-review-row"><dt>{{ __('Rear Application') }}</dt><dd data-field="rear-app-full">, </dd></div>
          <div class="dsb-review-row"><dt>{{ __('Specs') }}</dt><dd data-field="extras-full">, </dd></div>
          <div class="dsb-review-row"><dt>{{ __('Front Yoke Add-on') }}</dt><dd data-field="flange-full">, </dd></div>
          <div class="dsb-review-row"><dt>{{ __('Rear Yoke Add-on') }}</dt><dd data-field="trans-full">, </dd></div>
        </dl>
        <div class="dsb-review-diagram"><div class="dsb-plan-svg dsb-review-plan" id="dsb-review-plan" hidden></div><img id="dsb-review-diagram-img" hidden alt="{{ __('Configuration diagram') }}"><div class="dsb-diagram-badges" id="dsb-review-diagram-badges"></div></div>
        <div class="dsb-tier-picker">
          <p class="dsb-tier-title">{{ __('Choose Your Driveshaft') }}</p>
          <div class="dsb-tier-cards">
            <button type="button" class="dsb-tier-card dsb-tier-card--selected" data-select-tier="certified"><span class="dsb-tier-badge">{{ __('Certified') }}</span><span class="dsb-tier-label">{{ __('Certified Driveshaft') }}</span><span class="dsb-tier-desc">{{ __('Professionally inspected & refurbished') }}</span><span class="dsb-tier-price" data-tier-price="certified">$0.00</span></button>
            <button type="button" class="dsb-tier-card" data-select-tier="new"><span class="dsb-tier-badge dsb-tier-badge--new">{{ __('Brand New') }}</span><span class="dsb-tier-label">{{ __('Brand New Driveshaft') }}</span><span class="dsb-tier-desc">{{ __('New components') }}</span><span class="dsb-tier-price" data-tier-price="new">$0.00</span></button>
          </div>
        </div>
        <div class="dsb-notes"><label>{{ __('Additional Notes (optional)') }}</label>
          <textarea rows="3" id="dsb-notes" name="dsb_notes" placeholder="{{ __('Any additional details about your application...') }}"></textarea></div>
        <div class="dsb-share"><label>{{ __('Share link ,  contains all your measurements & selections') }}</label>
          <div class="dsb-share-row"><input type="text" readonly data-share-url onclick="this.select()"><button type="button" class="dsb-btn dsb-btn--secondary" data-action="copy">{{ __('Copy') }}</button></div></div>
        <div class="dsb-review-actions">
          <button type="button" class="dsb-btn dsb-btn--secondary" data-back="1"><x-icon name="returns"/> {{ __('Start Over') }}</button>
          <button type="button" class="dsb-btn dsb-btn--secondary" data-action="print">{{ __('Print') }}</button>
        </div>
      </div>
    </section>

    {{-- Confirmation (after add to cart) --}}
    <div class="dsb-confirm" id="dsb-confirm" hidden>
      <div class="dsb-review-badge"><x-icon name="check"/></div>
      <h4 class="dsb-step-title">{{ __('Added to cart!') }}</h4>
      <p class="dsb-step-desc" id="dsb-confirm-summary"></p>
      <p class="dsb-step-desc">{{ __('Total:') }} <b id="dsb-confirm-price" style="color:var(--dsb-primary)"></b></p>
      <div class="dsb-review-actions" style="margin-top:14px">
        <a href="/cart" class="dsb-btn dsb-btn--primary"><x-icon name="cart"/> {{ __('View Cart & Checkout') }}</a>
        <a href="/online-builder" class="dsb-btn dsb-btn--secondary">{{ __('Build another') }}</a>
      </div>
    </div>

  </div>
  </div>{{-- /dsb-main --}}

  <aside class="dsb-sheet" id="dsb-sheet" aria-label="{{ __('Your build') }}">
    <div class="dsb-sheet-head">
      <span class="dsb-sheet-kicker">{{ __('Build sheet') }}</span>
      <span class="dsb-sheet-type">{{ $pieces === 'two' ? __('Two piece') : __('One piece') }} · {{ __('inches') }}</span>
    </div>
    <div class="dsb-sheet-draw" id="dsb-sheet-draw"></div>
    <dl class="dsb-sheet-specs" id="dsb-sheet-specs"></dl>
    <div class="dsb-sheet-price">
      <span class="dsb-hero-price-label">{{ __('Current price') }}</span>
      <span class="dsb-hero-price-value">$0.00</span>
      <small>{{ __('Built, straightened and balanced in our shop') }}</small>
    </div>
    @include('partials.dsb-cart-footer')
  </aside>
  </div>{{-- /dsb-layout --}}

  <div class="dsb-mbar">
    <span><small>{{ __('Current price') }}</small><b data-mbar-price>$0.00</b></span>
    <button type="button" class="dsb-btn dsb-btn--primary" data-mbar-go>{{ __('Your build') }} <x-icon name="caret-down"/></button>
  </div>

  {{-- hidden fields (same names as the original plugin) --}}
  <input type="hidden" name="dsb_duty_key"><input type="hidden" name="dsb_duty_label">
  <input type="hidden" name="dsb_series_key"><input type="hidden" name="dsb_series_label">
  <input type="hidden" name="dsb_material"><input type="hidden" name="dsb_front_app"><input type="hidden" name="dsb_rear_app">
  <input type="hidden" name="dsb_final_price"><input type="hidden" name="dsb_price_tier" value="certified">
  <input type="hidden" name="dsb_flange_addon_label"><input type="hidden" name="dsb_flange_addon_price" value="0">
  <input type="hidden" name="dsb_trans_addon_label"><input type="hidden" name="dsb_trans_addon_price" value="0">
  <input type="hidden" id="dsb-configured" name="dsb_configured" value="0">
  <input type="hidden" name="dsb_premade_extras">


</div>

  <aside class="bldhelp">
    <div>
      <b>{{ __('Not sure what to measure?') }}</b>
      <p>{{ __('Take the numbers off the shaft you already have. The guides walk each measurement with diagrams.') }}</p>
      <a class="textlink" href="/driveshaft-designer">{{ __('Open the driveshaft designer') }} <x-icon name="arrow-right"/></a>
    </div>
    <div>
      <b>{{ __('Rather talk it through?') }}</b>
      <p>{{ __('Call the shop and a driveline tech will spec it with you. No call centre.') }}</p>
      <a class="textlink" href="tel:18135246491"><x-icon name="phone"/> (813) 524-6491</a>
    </div>
    <div>
      <b>{{ __('Want us to measure it?') }}</b>
      <p>{{ __('Bring the shaft to our shop. We measure, build and balance it in house.') }}</p>
      <a class="textlink" href="/contact">{{ __('Contact the shop') }} <x-icon name="arrow-right"/></a>
    </div>
  </aside>
</div>
</div></section>
<script>window.dsbConfig = @json($config);
window.UDS_T = Object.assign(window.UDS_T || {}, @json(app()->getLocale() === 'en' ? (object) [] : \App\Http\Controllers\DsbController::jsStrings($config)));</script>
<script src="/js/dsb.js?v={{ filemtime(public_path('js/dsb.js')) }}"></script>
@endsection
