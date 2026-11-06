{{-- U-joint line drawing for a series, with its cap diameter and width on the dimension lines (inches).
     Outside snap ring drawing for 1310 to 1550, inside snap ring for 7260/7290 and 3R; nothing for a series we have no
     numbers for. Data: PartIdentifier::SERIES. Drawings: public/img/store/ujoints.svg. --}}
@php
  $ujAll  = \App\Support\PartIdentifier::SERIES;
  $ujName = strtoupper(trim(explode(',', (string) ($series ?? ''))[0]));
  $ujRow  = $ujAll[$ujName] ?? ($ujName === '3R' || $ujName === 'S44' ? $ujAll['3R/S44'] : null);
@endphp
@if($ujRow)
  @php
    [$ujCap, $ujWidth, $ujRing] = $ujRow;
    $ujKind = $ujRing === 'inside' ? 'isr' : 'osr';
    // where the values sit on each traced drawing (viewBox units): cap diameter on top, width on the right
    $ujGeo = ['osr' => [1360, 1296, 552, 144, 1248, 776], 'isr' => [1520, 1392, 608, 136, 1408, 804]][$ujKind];
    [$vw, $vh, $cx, $cy, $wx, $wy] = $ujGeo;
    $ujCapS = number_format($ujCap, 3); $ujWidthS = number_format($ujWidth, 3);
  @endphp
  <svg class="ujdwg {{ $class ?? '' }}" viewBox="0 0 {{ $vw }} {{ $vh }}" role="img" aria-label="{{ __(':s u-joint: :cap in. cap diameter, :width in. across the caps', ['s' => $ujName, 'cap' => $ujCapS, 'width' => $ujWidthS]) }}">
    <use href="{{ asset('img/store/ujoints.svg') }}#{{ $ujKind }}" width="{{ $vw }}" height="{{ $vh }}"/>
    <text x="{{ $cx }}" y="{{ $cy }}">{{ $ujCapS }}</text>
    <text x="{{ $wx }}" y="{{ $wy }}" transform="rotate(-90 {{ $wx }} {{ $wy }})">{{ $ujWidthS }}</text>
  </svg>
@endif
