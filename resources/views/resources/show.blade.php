{{-- One of the old store's measuring guides (App\Support\ResourceGuides), at its old /resources address. Not in any menu: the footer and the guide list link it. --}}
@extends('layouts.app')
@php
  $base = rtrim(config('app.url'), '/');
  $url = \App\Support\ResourceGuides::url($g['path']);
  $catalog = app(\App\Services\CatalogService::class);
  $firstImg = collect($g['blocks'])->firstWhere('type', 'images')['items'][0]['src'] ?? null;
  $slug = fn ($t) => \Illuminate\Support\Str::slug($t);
  $seen = [];
  $family = $parent ? array_merge([$parent['path'] => $parent], $siblings) : array_merge([$g['path'] => $g], $siblings);
  $keys = array_keys($family); $at = array_search($g['path'], $keys, true);
  $prev = $at > 0 ? $family[$keys[$at - 1]] : null; $next = $at !== false && $at + 1 < count($keys) ? $family[$keys[$at + 1]] : null;
  $crumbs = [[__('Home'), $base . '/'], [__('Measuring guides'), $base . '/resources']];
  if ($parent) $crumbs[] = [$parent['title'], $base . \App\Support\ResourceGuides::url($parent['path'])];
  $crumbs[] = [$g['title'], $base . $url];
@endphp
@section('title', \Illuminate\Support\Str::limit($g['title'], 52, '') . ' | UDS')
@section('desc', $g['desc'])
@section('canonical', $url)
@if($firstImg)@section('ogimage', $base . $firstImg)@endif

@push('schema')
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org', '@@type' => 'TechArticle', '@@id' => $base . $url . '#article',
  'mainEntityOfPage' => ['@@type' => 'WebPage', '@@id' => $base . $url],
  'headline' => $g['title'], 'description' => $g['desc'], 'inLanguage' => 'en-US',
  'image' => $firstImg ? [$base . $firstImg] : [$base . '/img/og-default.jpg'],
  'author' => ['@@id' => $base . '/#organization'], 'publisher' => ['@@id' => $base . '/#organization'],
  'isPartOf' => ['@@type' => 'CollectionPage', '@@id' => $base . '/resources', 'name' => 'Measuring guides'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@include('partials.breadcrumb-schema', ['crumbs' => $crumbs])
@endpush

@section('content')
<section class="phero guide-hero rg-hero"><div class="wrap">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><a href="/resources">{{ __('Measuring guides') }}</a>
    @if($parent)<x-icon name="caret-right"/><a href="{{ \App\Support\ResourceGuides::url($parent['path']) }}">{{ $parent['title'] }}</a>@endif
    <x-icon name="caret-right"/><span aria-current="page">{{ $g['title'] }}</span>
  </nav>
  <div class="guide-hero__in">
    <h1>{{ $g['title'] }}</h1>
    <p class="lead">{{ $g['desc'] }}</p>
  </div>
</div></section>

<section class="section guide-section"><div class="wrap guide-layout">
  <aside class="guide-side">
    @if(count($family) > 1)
    <nav class="guide-toc rg-family" aria-label="{{ __('In this guide') }}">
      <details class="guide-toc__d" open>
        <summary>{{ $parent['title'] ?? $g['title'] }}</summary>
        <ol>@foreach($family as $f)<li><a href="{{ \App\Support\ResourceGuides::url($f['path']) }}" @if($f['path'] === $g['path']) aria-current="page" @endif>{{ $f['title'] }}</a></li>@endforeach</ol>
      </details>
    </nav>
    @endif
    <div class="guide-help">
      <b>{{ __('Not sure which part you have?') }}</b>
      <p>{{ __('Send us the measurements or a photo and we will find the part number.') }}</p>
      <a class="btn btn-primary btn-sm" href="/contact">{{ __('Contact us') }} <x-icon name="arrow-right"/></a>
      <a class="guide-help__tel" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a>
    </div>
    <a class="rg-all" href="/resources"><x-icon name="arrow-left"/> {{ __('All measuring guides') }}</a>
  </aside>

  <article class="guide-body rg-body" id="main-article">
    @foreach($g['blocks'] as $i => $b)
      @switch($b['type'])
        @case('heading')
          @php $id = $slug($b['text']); $id = isset($seen[$id]) ? $id . '-' . (++$seen[$id]) : $id; $seen[$id] ??= 1; @endphp
          <h2 id="{{ $id }}">{{ $b['text'] }}</h2>
          @break
        @case('text')
          <div class="rg-text">{!! $b['html'] !!}</div>
          @break
        @case('images')
          <div class="rg-figs rg-figs--{{ min(4, count($b['items'])) }}">
            @foreach($b['items'] as $k => $im)
              @php $alt = $im['alt'] ?: ($g['title'] . ($k ? ' — ' . __('detail') : '')); $href = ($im['link']['kind'] ?? '') === 'guide' ? \App\Support\ResourceGuides::url($im['link']['path']) : null; @endphp
              @if($href && $im['caption'])
              {{-- a picture with its title that opens a guide (the old "select the option below" grids) --}}
              <a class="rg-fig rg-pick" href="{{ $href }}"><img src="{{ $im['src'] }}" alt="{{ $alt }}" width="{{ $im['w'] }}" height="{{ $im['h'] }}" loading="lazy" decoding="async"><b>{{ $im['caption'] }} <x-icon name="arrow-right"/></b></a>
              @continue
              @endif
              <figure class="rg-fig">
                @if($href)<a href="{{ $href }}">@endif
                <img src="{{ $im['src'] }}" alt="{{ $alt }}" width="{{ $im['w'] }}" height="{{ $im['h'] }}" @if($i > 1 || $k > 0) loading="lazy" @endif decoding="async">
                @if($href)</a>@endif
                @if($im['caption'])<figcaption>{{ $im['caption'] }}</figcaption>@endif
              </figure>
            @endforeach
          </div>
          @break
        @case('defs')
          <dl class="rg-defs">@foreach($b['items'] as $d)<div><dt>{{ $d['mark'] }}</dt><dd>{{ $d['text'] }}</dd></div>@endforeach</dl>
          @break
        @case('table')
          <div class="rg-table"><table>
            @if(array_filter($b['head']))<thead><tr>@foreach($b['head'] as $h)<th scope="col">{{ $h }}</th>@endforeach</tr></thead>@endif
            <tbody>@foreach($b['rows'] as $r)<tr>@foreach($r as $c => $cell)@if($c === 0)<th scope="row">{{ $cell }}</th>@else<td>{{ $cell }}</td>@endif @endforeach</tr>@endforeach</tbody>
          </table></div>
          @break
        @case('link')
          @if($b['kind'] === 'guide' && ($to = \App\Support\ResourceGuides::find($b['path'])))
            <a class="rg-next" href="{{ \App\Support\ResourceGuides::url($to['path']) }}"><span>{{ __('Guide') }}</span><b>{{ $to['title'] }}</b><x-icon name="arrow-right"/></a>
          @elseif($b['kind'] === 'category' && ($term = $catalog->term($b['slug'])))
            <p class="rg-cta"><a class="btn btn-primary" href="{{ $catalog->categoryPath($b['slug']) }}">{{ __('Shop') }} {{ \App\Support\PartGlossary::t(html_entity_decode($term->name)) }} <x-icon name="arrow-right"/></a></p>
          @elseif($b['kind'] === 'pdf')
            <p class="rg-cta"><a class="btn btn-ghost" href="{{ $b['href'] }}" download><x-icon name="download"/> {{ __('Download the PDF guide') }} <small>(12 MB)</small></a></p>
          @endif
          @break
        @case('bearing-chart')
          @if($chart)
          @php $mm = fn ($v) => ($v = trim((string) $v)) === '' || (float) $v == 0 ? '—' : $v; @endphp
          <label class="rg-filter"><x-icon name="search"/><input type="search" data-rg-filter placeholder="{{ __('Filter by part # or measurement') }}" aria-label="{{ __('Filter by part # or measurement') }}"></label>
          <div class="rg-table rg-chart"><table>
            <thead><tr><th scope="col">{{ __('Part #') }}</th><th scope="col"><span class="rg-l">{{ __('Bearing ID') }}</span><span class="rg-s">{{ __('ID') }}</span></th><th scope="col"><span class="rg-l">{{ __('Center bearing to base') }}</span><span class="rg-s">{{ __('To base') }}</span></th><th scope="col"><span class="rg-l">{{ __('Mount holes, center to center') }}</span><span class="rg-s">{{ __('Hole spacing') }}</span></th><th scope="col" class="rg-chart__app">{{ __('Application') }}</th><th scope="col">{{ __('Price') }}</th></tr></thead>
            <tbody>
            @foreach($chart as $p)
              <tr>
                <th scope="row"><a href="{{ part_path($p['sku']) }}">{{ $p['sku'] }}</a></th>
                <td>{{ $mm($p['attrs']['pa_bearing-id'] ?? '') }}</td><td>{{ $mm($p['attrs']['pa_center-bearing-to-base'] ?? '') }}</td>
                <td>{{ $mm($p['attrs']['pa_ctr-to-ctr-of-mount-holes'] ?? '') }}</td><td class="rg-chart__app">{{ $p['attrs']['pa_application'] ?? '' }}</td>
                <td class="u-nums">@if($p['price'] > 0)${{ number_format($p['price'], 2) }}@if($p['stock_status'] === 'instock') <small class="rg-ok">{{ __('In stock') }}</small>@endif @else<small>{{ __('Call') }}</small>@endif</td>
              </tr>
            @endforeach
            </tbody>
          </table></div>
          <p class="rg-filter__none" hidden>{{ __('No bearing matches that. Call us at (813) 524-6491 and we will find it.') }}</p>
          <p class="rg-cta"><a class="btn btn-primary" href="{{ $catalog->categoryPath('center-support-bearing') }}">{{ __('Shop center support bearings') }} <x-icon name="arrow-right"/></a></p>
          @endif
          @break
      @endswitch
    @endforeach

    @if($children = \App\Support\ResourceGuides::children($g['path']))
      @if(!collect($g['blocks'])->contains(fn ($b) => ($b['kind'] ?? '') === 'guide' || collect($b['items'] ?? [])->contains(fn ($im) => isset($im['link']))))
      <h2 id="guides">{{ __('Pick your style') }}</h2>
      @foreach($children as $c)<a class="rg-next" href="{{ \App\Support\ResourceGuides::url($c['path']) }}"><span>{{ __('Guide') }}</span><b>{{ $c['title'] }}</b><x-icon name="arrow-right"/></a>@endforeach
      @endif
    @endif

    @if($prev || $next)
    <nav class="rg-pager" aria-label="{{ __('More in this guide') }}">
      @if($prev)<a href="{{ \App\Support\ResourceGuides::url($prev['path']) }}" rel="prev"><small>{{ __('Previous') }}</small><b>{{ $prev['title'] }}</b></a>@else<span></span>@endif
      @if($next)<a href="{{ \App\Support\ResourceGuides::url($next['path']) }}" rel="next" class="rg-pager__next"><small>{{ __('Next') }}</small><b>{{ $next['title'] }}</b></a>@endif
    </nav>
    @endif
    <div class="guide-help rg-help-end">
      <b>{{ __('Not sure which part you have?') }}</b>
      <p>{{ __('Send us the measurements or a photo and we will find the part number.') }}</p>
      <a class="btn btn-primary btn-sm" href="/contact">{{ __('Contact us') }} <x-icon name="arrow-right"/></a>
      <a class="guide-help__tel" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a>
    </div>
  </article>
</div></section>
@if($chart)
@push('scripts')
<script>
(() => {
  const box = document.querySelector('[data-rg-filter]'); if (!box) return;
  const rows = [...document.querySelectorAll('.rg-chart tbody tr')], none = document.querySelector('.rg-filter__none');
  box.addEventListener('input', () => {
    const q = box.value.trim().toLowerCase(); let shown = 0;
    for (const r of rows) { const hit = !q || r.textContent.toLowerCase().includes(q); r.hidden = !hit; shown += hit; }
    none.hidden = shown > 0;
  });
})();
</script>
@endpush
@endif
@endsection
