{{-- Language menu: the current language as a button, every language in a dropdown (top bar, footer) or an accordion
     row (mobile menu). A <details>, so it opens without JS (uds.js adds outside click, Escape and arrow keys); each
     option is a plain link and the middleware stores the choice in a cookie. --}}
@php
  $cur = app()->getLocale();
  $q = request()->except('lang');
  $href = fn ($l) => url()->current() . '?' . http_build_query($q + ['lang' => $l]);
  $names = \App\Http\Middleware\SetLocale::NAMES;
  $flags = [
    'en' => '<rect width="19" height="13" fill="#b22234"/><path d="M0 1.5h19M0 3.5h19M0 5.5h19M0 7.5h19M0 9.5h19M0 11.5h19" stroke="#fff" stroke-width="1"/><rect width="7.6" height="7" fill="#3c3b6e"/><g fill="#fff"><circle cx="1.3" cy="1.2" r=".45"/><circle cx="3.8" cy="1.2" r=".45"/><circle cx="6.3" cy="1.2" r=".45"/><circle cx="2.55" cy="2.6" r=".45"/><circle cx="5.05" cy="2.6" r=".45"/><circle cx="1.3" cy="4" r=".45"/><circle cx="3.8" cy="4" r=".45"/><circle cx="6.3" cy="4" r=".45"/><circle cx="2.55" cy="5.4" r=".45"/><circle cx="5.05" cy="5.4" r=".45"/></g>',
    'es' => '<rect width="6.34" height="13" fill="#006847"/><rect x="6.33" width="6.34" height="13" fill="#fff"/><rect x="12.66" width="6.34" height="13" fill="#ce1126"/><circle cx="9.5" cy="6.5" r="1.9" fill="none" stroke="#8c5a2b" stroke-width=".7"/><circle cx="9.5" cy="6.2" r=".8" fill="#6b4423"/><path d="M8 7.9q1.5 1 3 0" fill="none" stroke="#1f7a3a" stroke-width=".5"/>',
    'fr' => '<rect width="6.34" height="13" fill="#002654"/><rect x="6.33" width="6.34" height="13" fill="#fff"/><rect x="12.66" width="6.34" height="13" fill="#ce1126"/>',
    'zh' => '<rect width="19" height="13" fill="#de2910"/><g fill="#ffde00"><polygon points="3.17,1.30 3.61,2.65 5.02,2.65 3.88,3.48 4.32,4.83 3.17,3.99 2.02,4.83 2.46,3.48 1.32,2.65 2.73,2.65"/><polygon points="5.78,1.64 6.08,1.28 5.83,0.88 6.27,1.06 6.58,0.70 6.54,1.17 6.98,1.35 6.52,1.46 6.48,1.93 6.24,1.53"/><polygon points="6.96,2.69 7.38,2.48 7.31,2.02 7.64,2.36 8.06,2.15 7.85,2.56 8.18,2.90 7.71,2.82 7.49,3.24 7.42,2.77"/><polygon points="6.98,4.37 7.45,4.35 7.58,3.90 7.74,4.35 8.21,4.33 7.84,4.62 8.00,5.06 7.61,4.80 7.23,5.09 7.37,4.63"/><polygon points="5.83,5.44 6.27,5.61 6.57,5.25 6.54,5.72 6.98,5.89 6.52,6.01 6.49,6.48 6.24,6.08 5.78,6.20 6.08,5.84"/></g>',
    'ja' => '<rect width="19" height="13" fill="#fff"/><circle cx="9.5" cy="6.5" r="3.9" fill="#bc002d"/>',
  ];
  $flag = fn ($l) => '<svg class="langsw__flag" viewBox="0 0 19 13" aria-hidden="true">' . $flags[$l] . '</svg>';
@endphp
<details class="langsw {{ $class ?? '' }}" data-langsw>
  <summary aria-label="{{ __('Language') }}: {{ $names[$cur] }}">
    <span class="langsw__label"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6"/></svg>{{ __('Language') }}</span>
    <span class="langsw__cur">{!! $flag($cur) !!}<span class="langsw__code">{{ strtoupper($cur) }}</span><span class="langsw__name" lang="{{ \App\Http\Middleware\SetLocale::tag($cur) }}">{{ $names[$cur] }}</span></span>
    <svg class="langsw__chev" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 4.5 3 3 3-3"/></svg>
  </summary>
  <ul class="langsw__menu">
    @foreach($names as $code => $name)
    <li><a href="{{ $href($code) }}" hreflang="{{ \App\Http\Middleware\SetLocale::tag($code) }}" lang="{{ \App\Http\Middleware\SetLocale::tag($code) }}" @class(['is-on' => $cur === $code]) @if($cur === $code) aria-current="true" @endif>
      {!! $flag($code) !!}<span class="langsw__name">{{ $name }}</span>@if($cur === $code)<svg class="langsw__check" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3.5 8.5 3 3 6-7"/></svg>@else<span class="langsw__code">{{ strtoupper($code) }}</span>@endif</a></li>
    @endforeach
  </ul>
</details>
