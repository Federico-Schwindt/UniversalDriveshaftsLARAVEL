@extends('layouts.app')
@section('title', ($style ? ($style === 2 ? __('Two-piece driveshaft drawing') : __('One-piece driveshaft drawing')) : __('Driveshaft designer, draw and print')) . ' | Universal Driveshafts')
@section('desc', __('Free driveshaft designer: pick one or two piece, choose the end sections, click each dimension to enter your measurements, then print or send it to the shop.'))
@if($style)
@section('canonical','/driveshaft-designer')
@section('robots','noindex,follow')
@endif
@push('head')<link rel="stylesheet" href="/css/designer.css?v={{ @filemtime(public_path('css/designer.css')) }}">@endpush
@section('content')
@php
  $slotLabels = $style ? array_merge(['Front'], $style === 2 ? ['Middle'] : [], ['Rear']) : [];
  $styleName = $style === 1 ? __('One piece') : ($style === 2 ? __('Two piece') : null);
  $legend = $style === 2
    ? [['A',__('Overall length, front end to rear end')],['B',__('Front end to center bearing (B1), center bearing to rear end (B2)')],['C',__('Front shaft, end to joint center (C1); rear shaft, joint center to end (C2)')],['W',__('Tube length, weld to weld')],['T',__('Tube outside diameter')]]
    : [['C',__('Overall length, end to end')],['W',__('Tube length, weld to weld')],['T',__('Tube outside diameter')]];
@endphp
<div class="wrap no-print">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><a href="/online-builder">{{ __('Driveshafts') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Driveshaft designer') }}</span></nav>
</div>
<header class="bldhead no-print"><div class="wrap">
  <h1>{{ __('Driveshaft designer') }}</h1>
  <p>{{ __('Draw the shaft you need, dimension it and print the sheet, or send it to our shop with your measurements attached. Free to use, no account needed.') }}</p>
</div></header>

<section class="section dsg" id="dsg" data-style="{{ $style }}" data-sections="{{ $sections }}" data-complete="{{ $complete ? 1 : 0 }}"><div class="wrap">

  {{-- step rail: one strip for the whole flow, the chosen sections ride along in it --}}
  <div class="dsg__bar no-print">
    <ol class="dsg__rail" aria-label="{{ __('Progress') }}">
      <li class="dsg__slot {{ $style ? 'is-done' : 'is-on' }}">
        @if($style)<a class="dsg__slot-a" href="/driveshaft-designer" aria-label="{{ __('Change style') }}">@else<span class="dsg__slot-a">@endif
          <span class="dsg__slot-k"><i>1</i>{{ __('Style') }}</span>
          <span class="dsg__slot-v">@if($style)<b>{{ $styleName }}</b>@else<em>{{ __('Choose below') }}</em>@endif</span>
        @if($style)</a>@else</span>@endif
      </li>
      @if(!$style)
        <li class="dsg__slot"><span class="dsg__slot-a"><span class="dsg__slot-k"><i>2</i>{{ __('Sections') }}</span><span class="dsg__slot-v"><em>{{ __('Next') }}</em></span></span></li>
        <li class="dsg__slot"><span class="dsg__slot-a"><span class="dsg__slot-k"><i>3</i>{{ __('Measurements') }}</span><span class="dsg__slot-v"><em>{{ __('Then print') }}</em></span></span></li>
      @else
        @foreach($slotLabels as $i => $lab)
          @php $done = isset($codes[$i]); $on = !$complete && $locationLabel === $lab; $back = '/driveshaft-designer/'.$style.($i ? '/'.implode('+', array_slice($codes, 0, $i)) : ''); @endphp
          <li class="dsg__slot {{ $done ? 'is-done' : ($on ? 'is-on' : '') }}">
            @if($done)<a class="dsg__slot-a" href="{{ $back }}" aria-label="{{ __('Change '.strtolower($lab).' section :code', ['code' => $codes[$i]]) }}">@else<span class="dsg__slot-a">@endif
              <span class="dsg__slot-k"><i>{{ $i + 2 }}</i>{{ __($lab.' section') }}</span>
              <span class="dsg__slot-v">
                @if($done)<img src="{{ asset('img/designer/sections-uds/'.$codes[$i].'.svg') }}" alt="{{ __($lab.' section') }} {{ $codes[$i] }}"><code>{{ $codes[$i] }}</code>
                @elseif($on)<em>{{ __('Choose below') }}</em>
                @else<em>{{ __('Next') }}</em>@endif
              </span>
            @if($done)</a>@else</span>@endif
          </li>
        @endforeach
        <li class="dsg__slot {{ $complete ? 'is-on' : '' }}"><span class="dsg__slot-a"><span class="dsg__slot-k"><i>{{ $style + 3 }}</i>{{ __('Measurements') }}</span><span class="dsg__slot-v"><em>{{ $complete ? __('Enter and print') : __('Then print') }}</em></span></span></li>
      @endif
    </ol>
    <div class="dsg__btns">
      @if($style)<a class="btn btn-ghost btn-sm" href="{{ count($codes) ? '/driveshaft-designer/'.$style.(count($codes) > 1 ? '/'.implode('+', array_slice($codes, 0, -1)) : '') : '/driveshaft-designer' }}"><x-icon name="arrow-left"/> {{ __('Back') }}</a>@endif
      @if($style)<a class="btn btn-ghost btn-sm" href="/driveshaft-designer" data-confirm="{{ __('Start a new drawing? The current one will be cleared.') }}"><x-icon name="plus"/> {{ __('New') }}</a>@endif
      @if($complete)<button type="button" class="btn btn-ghost btn-sm" id="dsg-save" data-style="{{ $style }}" data-sections="{{ $sections }}" title="{{ auth()->check() ? __('Save this drawing to your account') : __('Sign in to save the drawing to your account') }}"><x-icon name="heart"/> {{ __('Save') }}</button>
      <button type="button" class="btn btn-primary btn-sm" id="dsg-print"><x-icon name="package"/> {{ __('Print sheet') }}</button>@endif
    </div>
  </div>

  @if(!$style)
  {{-- 1. style --}}
  <div class="dsg__head">
    <h2 class="dsg__h">{{ __('Choose a style') }}</h2>
    <p>{{ __('Both styles are drawn from the same section library. You pick the ends, we draw the shaft.') }}</p>
  </div>
  <div class="dsg__styles">
    <a class="dsg__style" href="/driveshaft-designer/1">
      <span class="dsg__style-k">{{ __('Style :n', ['n' => 1]) }}</span>
      <span class="dsg__style-n">{{ __('One piece') }}</span>
      <span class="dsg__style-img"><img src="/driveshaft-designer/svg/01+01R?style=uds" alt="{{ __('One piece driveshaft drawing with three dimensions') }}" loading="lazy"></span>
      <span class="dsg__style-d">{{ __('A single tube with an end at each side. Yokes, flanges, slips or companion ends, in any combination.') }}</span>
      <span class="dsg__style-f"><span><b class="n-mono">3</b> {{ __('measurements') }}</span><span><b class="n-mono">2</b> {{ __('sections to choose') }}</span><span class="dsg__style-go">{{ __('Start') }} <x-icon name="arrow-right"/></span></span>
    </a>
    <a class="dsg__style" href="/driveshaft-designer/2">
      <span class="dsg__style-k">{{ __('Style :n', ['n' => 2]) }}</span>
      <span class="dsg__style-n">{{ __('Two piece') }}</span>
      <span class="dsg__style-img"><img src="/driveshaft-designer/svg/01+50+01R?style=uds" alt="{{ __('Two piece driveshaft drawing with nine dimensions') }}" loading="lazy"></span>
      <span class="dsg__style-d">{{ __('Two tubes joined through a center support bearing. Common on long wheelbase trucks, vans and 4x4s.') }}</span>
      <span class="dsg__style-f"><span><b class="n-mono">9</b> {{ __('measurements') }}</span><span><b class="n-mono">3</b> {{ __('sections to choose') }}</span><span class="dsg__style-go">{{ __('Start') }} <x-icon name="arrow-right"/></span></span>
    </a>
  </div>

  @elseif(!$complete)
  {{-- 2. sections --}}
  <div class="dsg__head dsg__head--between">
    <div>
      <h2 class="dsg__h">{{ __('Choose the '.strtolower($locationLabel).' section') }}</h2>
      <p>{{ $locationLabel === 'Front' ? __('The end that faces the transmission or transfer case.') : ($locationLabel === 'Middle' ? __('The center support bearing and the coupling joint between the two tubes.') : __('The end that faces the differential or axle.')) }} {{ __('Not sure which one you have? Pick the closest match and note the difference when you send it in.') }}</p>
    </div>
    <span class="dsg__count"><b class="n-mono">{{ count($options) }}</b> {{ __('options') }}</span>
  </div>
  <div class="dsg__sections">
    @foreach($options as $code)
      <a class="dsg__section" href="/driveshaft-designer/{{ $style }}/{{ $sections ? $sections.'+'.$code : $code }}" aria-label="{{ __($locationLabel.' section') }} {{ $code }}">
        <img src="{{ asset('img/designer/sections-uds/'.$code.'.svg') }}" alt="" loading="lazy">
        <code>{{ $code }}</code>
      </a>
    @endforeach
  </div>

  @else
  {{-- 3. result: the drawing sheet --}}
  <p class="dsg__hint no-print"><x-icon name="info"/><span>{!! __('Click any <b>{dimension}</b> on the drawing or a row in the table, type the measurement in inches and press Enter or Tab to move to the next one. Your values are saved in this page\'s link, so you can print, share or send it to the shop.') !!}</span></p>
  <article class="dsg__sheet" id="dsg-result">
    <header class="dsg__sheet-top">
      <span class="dsg__sheet-kicker">{{ __('Driveshaft drawing') }}</span>
      <span class="dsg__sheet-no n-mono">{{ $sections }}</span>
      <span class="dsg__sheet-meta n-mono">{{ $styleName }} · NTS · {{ __('inches') }}</span>
    </header>
    <div class="dsg__svg" id="dsg-svg" data-url="/driveshaft-designer/svg/{{ $sections }}">
      <input type="text" class="dsg__dim-input" id="dsg-dim-input" hidden autocomplete="off" inputmode="decimal" aria-label="{{ __('Measurement') }}">
    </div>
    <div class="dsg__body">
      <section class="dsg__meas">
        <h2 class="dsg__sub">{{ __('Measurements') }}</h2>
        <table class="dsg__table" id="dsg-table">
          <thead><tr><th scope="col">{{ __('Ref') }}</th><th scope="col">{{ __('Description') }}</th><th scope="col" class="dsg__th-val">{{ __('Inches') }}</th></tr></thead>
          <tbody></tbody>
        </table>
      </section>
      <section class="dsg__legend">
        <h2 class="dsg__sub">{{ __('Legend') }}</h2>
        <dl>
          @foreach($legend as [$k, $d])<div><dt class="n-mono">{{ $k }}</dt><dd>{{ $d }}</dd></div>@endforeach
        </dl>
        <p class="dsg__note">{{ __('Measure with the shaft installed and the vehicle at ride height, or compressed to the running length. Joint centers are the cross centerlines; flange ends are measured at the flange face.') }}</p>
      </section>
      <section class="dsg__job">
        <h2 class="dsg__sub">{{ __('Job') }}</h2>
        <div class="dsg__line"><span>{{ __('Customer') }}</span></div>
        <div class="dsg__line"><span>{{ __('Vehicle or application') }}</span></div>
        <div class="dsg__line"><span>{{ __('Phone') }}</span></div>
        <div class="dsg__line dsg__line--tall"><span>{{ __('Notes') }}</span></div>
      </section>
    </div>
    <footer class="dsg__title">
      <div class="dsg__tb dsg__tb--brand">
        <img src="/img/uds-logo.webp" alt="Universal Driveshafts" width="150" height="40">
        <small><strong>Universal Driveshafts LLC</strong> · Florida, USA · {{ $store['phone'] }} · inquiry@universaldriveshafts.com</small>
      </div>
      <div class="dsg__tb"><small>{{ __('Drawing no.') }}</small><b class="n-mono">{{ $sections }}</b></div>
      <div class="dsg__tb"><small>{{ __('Style') }}</small><b>{{ $styleName }}</b></div>
      <div class="dsg__tb"><small>{{ __('Date') }}</small><b class="n-mono">{{ now()->format('Y-m-d') }}</b></div>
      <div class="dsg__tb"><small>{{ __('Scale') }}</small><b class="n-mono">NTS</b></div>
      <div class="dsg__tb"><small>{{ __('Units') }}</small><b>{{ __('Inches') }}</b></div>
      <div class="dsg__tb"><small>{{ __('Sheet') }}</small><b class="n-mono">{{ __(':a of :b', ['a' => 1, 'b' => 1]) }}</b></div>
    </footer>
  </article>
  <div class="dsg__after no-print">
    <a class="btn btn-primary" href="/online-builder">{{ __('Price this build in the online builder') }} <x-icon name="arrow-right"/></a>
    <a class="btn btn-ghost" id="dsg-send" href="/contact?designer={{ $sections }}" data-sections="{{ $sections }}"><x-icon name="mail"/> {{ __('Send it to the shop') }}</a>
    <button type="button" class="btn btn-ghost" data-dsg-save><x-icon name="heart"/> {{ __('Save to my account') }}</button>
    <p class="dsg__after-p">{{ __('The drawing and your measurements travel with the message. Sections and dimensions are drawn not to scale, from the supplier\'s library, with permission.') }}</p>
  </div>
  @endif

</div></section>
@endsection
@push('scripts')<script>window.UDS_T = Object.assign(window.UDS_T || {}, @json(app()->getLocale() === 'en' ? (object) [] : collect(\App\Http\Controllers\DesignerController::JS_STRINGS)->mapWithKeys(fn ($k) => [$k => __($k)])->filter(fn ($v, $k) => $v !== $k)));</script><script src="/js/designer.js?v={{ @filemtime(public_path('js/designer.js')) }}"></script>@endpush
