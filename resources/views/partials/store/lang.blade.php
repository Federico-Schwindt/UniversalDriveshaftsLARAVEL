{{-- Storefront language menu: the same links and cookie as partials/lang-switch, with an accessible name that
     contains the visible code ("Language: EN"). A <details>, so it works without JS. --}}
@php
  $cur = app()->getLocale();
  $q = request()->except('lang');
  $href = fn ($l) => url()->current() . '?' . http_build_query($q + ['lang' => $l]);
  $names = \App\Http\Middleware\SetLocale::NAMES;
@endphp
<details class="langsw {{ $class ?? '' }}">
  <summary><span class="st-sr">{{ __('Language') }}:</span><span class="langsw__code">{{ strtoupper($cur) }}</span><svg class="langsw__chev" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 4.5 3 3 3-3"/></svg></summary>
  <ul class="langsw__menu">
    @foreach($names as $code => $name)
      <li><a href="{{ $href($code) }}" hreflang="{{ \App\Http\Middleware\SetLocale::tag($code) }}" lang="{{ \App\Http\Middleware\SetLocale::tag($code) }}" @class(['is-on' => $cur === $code]) @if($cur === $code) aria-current="true" @endif><span class="langsw__name">{{ $name }}</span><span class="langsw__code">{{ strtoupper($code) }}</span></a></li>
    @endforeach
  </ul>
</details>
