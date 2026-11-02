{{-- EN / ES / FR with flags. A plain link (works without JS); the middleware stores the choice in a cookie. --}}
@php
  $cur = app()->getLocale();
  $q = request()->except('lang');
  $href = fn ($l) => url()->current() . '?' . http_build_query($q + ['lang' => $l]);
@endphp
<span class="langsw {{ $class ?? '' }}" role="group" aria-label="{{ __('Language') }}">
  <a href="{{ $href('en') }}" hreflang="en" lang="en" title="{!! __('English') !!}" @class(['is-on' => $cur === 'en']) @if($cur === 'en') aria-current="true" @endif>
    <svg class="langsw__flag" viewBox="0 0 19 13" aria-hidden="true"><rect width="19" height="13" fill="#b22234"/><path d="M0 1.5h19M0 3.5h19M0 5.5h19M0 7.5h19M0 9.5h19M0 11.5h19" stroke="#fff" stroke-width="1"/><rect width="7.6" height="7" fill="#3c3b6e"/><g fill="#fff"><circle cx="1.3" cy="1.2" r=".45"/><circle cx="3.8" cy="1.2" r=".45"/><circle cx="6.3" cy="1.2" r=".45"/><circle cx="2.55" cy="2.6" r=".45"/><circle cx="5.05" cy="2.6" r=".45"/><circle cx="1.3" cy="4" r=".45"/><circle cx="3.8" cy="4" r=".45"/><circle cx="6.3" cy="4" r=".45"/><circle cx="2.55" cy="5.4" r=".45"/><circle cx="5.05" cy="5.4" r=".45"/></g></svg>EN</a>
  <a href="{{ $href('es') }}" hreflang="es" lang="es" title="{!! __('Español') !!}" @class(['is-on' => $cur === 'es']) @if($cur === 'es') aria-current="true" @endif>
    <svg class="langsw__flag" viewBox="0 0 19 13" aria-hidden="true"><rect width="6.34" height="13" fill="#006847"/><rect x="6.33" width="6.34" height="13" fill="#fff"/><rect x="12.66" width="6.34" height="13" fill="#ce1126"/><circle cx="9.5" cy="6.5" r="1.9" fill="none" stroke="#8c5a2b" stroke-width=".7"/><circle cx="9.5" cy="6.2" r=".8" fill="#6b4423"/><path d="M8 7.9q1.5 1 3 0" fill="none" stroke="#1f7a3a" stroke-width=".5"/></svg>ES</a>
  <a href="{{ $href('fr') }}" hreflang="fr" lang="fr" title="Français" @class(['is-on' => $cur === 'fr']) @if($cur === 'fr') aria-current="true" @endif>
    <svg class="langsw__flag" viewBox="0 0 19 13" aria-hidden="true"><rect width="6.34" height="13" fill="#002654"/><rect x="6.33" width="6.34" height="13" fill="#fff"/><rect x="12.66" width="6.34" height="13" fill="#ce1126"/></svg>FR</a>
</span>
