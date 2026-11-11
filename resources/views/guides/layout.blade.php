{{-- Shared long-form guide layout. Child views define $faqs ([q,a] pairs) in a PHP block and fill the article section; meta comes from GuideController::registry(). --}}
@extends('layouts.app')
@php
  // Spanish: the registry (GuideController) is English; lang/i18n/content/guides-es.php overlays it per slug
  if (app()->getLocale() !== 'en' && is_file($gFile = lang_path('i18n/content/guides-' . app()->getLocale() . '.php'))) {
    $gEs = (include $gFile);
    $g = array_replace_recursive($g, $gEs[$g['slug']] ?? []);
    foreach (($related ?? []) as $rs => $rr) $related[$rs] = array_replace($rr, array_intersect_key($gEs[$rs] ?? [], ['h1' => 1, 'card' => 1]));
  }
@endphp
@section('title', $g['title'])
@section('desc', $g['desc'])
@section('canonical', '/guides/' . $g['slug'])

@php
  $base    = rtrim(config('app.url'), '/');
  $pageUrl = $base . '/guides/' . $g['slug'];
  $body    = $__env->yieldContent('article');
  $words   = str_word_count(strip_tags($body));
  $minutes = max(3, (int) round($words / 220));
  $faqs    = $faqs ?? [];
  if (app()->getLocale() !== 'en' && !empty($g['faqs'])) $faqs = $g['faqs'];
  $crumbName = \Illuminate\Support\Str::limit(strtok($g['h1'], ':'), 48, '');
@endphp

@push('schema')
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org',
  '@@type' => 'TechArticle',
  '@@id' => $pageUrl . '#article',
  'mainEntityOfPage' => ['@@type' => 'WebPage', '@@id' => $pageUrl],
  'headline' => $g['h1'],
  'description' => $g['desc'],
  'articleSection' => $g['kicker'],
  'inLanguage' => app()->getLocale() === 'en' ? 'en-US' : app()->getLocale(),
  'wordCount' => $words,
  'image' => [$base . '/img/photos/hero-weld.jpg'],
  'datePublished' => \App\Http\Controllers\GuideController::PUBLISHED,
  'dateModified' => \App\Http\Controllers\GuideController::PUBLISHED,
  'author' => ['@@type' => 'Organization', 'name' => 'Universal Driveshafts', 'url' => $base . '/', '@@id' => $base . '/#organization'],
  'publisher' => ['@@id' => $base . '/#organization'],
  'about' => ['@@type' => 'Thing', 'name' => 'Driveshaft'],
  'isPartOf' => ['@@type' => 'CollectionPage', '@@id' => $base . '/guides', 'name' => 'Driveshaft guides'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@include('partials.breadcrumb-schema', ['crumbs' => [[__('Home'), $base . '/'], [__('Guides'), $base . '/guides'], [$crumbName, $pageUrl]]])
@endpush

@section('content')
<section class="phero phero--rich guide-hero"><div class="wrap">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><a href="/guides">{{ __('Guides') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ $crumbName }}</span></nav>
  <div class="guide-hero__in">
    <span class="kicker">{{ $g['kicker'] }}</span>
    <h1>{{ $g['h1'] }}</h1>
    <p class="lead">{{ $g['lead'] }}</p>
    <p class="guide-meta u-mono">{{ __('By Universal Driveshafts') }} <span aria-hidden="true">&middot;</span> {{ __('Updated') }} <time datetime="{{ \App\Http\Controllers\GuideController::PUBLISHED }}">{{ \Carbon\Carbon::parse(\App\Http\Controllers\GuideController::PUBLISHED)->translatedFormat(['es' => 'j M Y', 'fr' => 'j M Y', 'zh' => 'Y年n月j日', 'ja' => 'Y年n月j日'][app()->getLocale()] ?? 'M j, Y') }}</time> <span aria-hidden="true">&middot;</span> {{ __(':n min read', ['n' => $minutes]) }}</p>
  </div>
</div></section>

<section class="section guide-section"><div class="wrap guide-layout">
  <aside class="guide-side">
    <nav class="guide-toc" aria-label="{{ __('On this page') }}">
      <details class="guide-toc__d" open>
        <summary>{{ __('On this page') }}</summary>
        <ol>
          @foreach($g['toc'] as [$id, $label])<li><a href="#{{ $id }}">{{ $label }}</a></li>@endforeach
          @if(count($faqs))<li><a href="#faq">{{ __('Frequently asked questions') }}</a></li>@endif
        </ol>
      </details>
    </nav>
    <div class="guide-help">
      <b>{{ __('Need it done rather than explained?') }}</b>
      <p>{{ __('Build and price a shaft online, or call our shop. We measure, build and balance in house.') }}</p>
      <a class="btn btn-primary btn-sm" href="/online-builder">{{ __('Open the online builder') }} <x-icon name="arrow-right"/></a>
      <a class="guide-help__tel" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a>
    </div>
  </aside>

  <article class="guide-body" id="main-article">
    {!! $body !!}

    @if(count($faqs))
    <h2 id="faq">{{ __('Frequently asked questions') }}</h2>
    @include('partials.faq', ['faqs' => $faqs])
    @endif
  </article>
</div></section>

@if(count($parts ?? []))
<section class="section section--tint guide-parts-section"><div class="wrap">
  <div class="s-head s-head--between">
    <div><h2>{{ $g['parts']['label'] }}</h2><p>{{ __('Live from the catalog, lowest price first. Every part page lists the series and dimensions.') }}</p></div>
    <a class="s-link" href="{{ $g['parts']['href'] }}">{{ __('Browse the category') }} <x-icon name="arrow-right"/></a>
  </div>
  <div class="guide-parts">
    @foreach($parts as $p)
      @php $nm = preg_replace('/^\s*'.preg_quote($p['sku'],'/').'\s*[\x{2013}\x{2014}|:-]*\s*/u', '', $p['name']) ?: $p['name']; @endphp
      <a class="pcard" href="/parts/{{ rawurlencode($p['sku']) }}">
        <span class="pcard__media">@if($p['image'])<img src="{{ $p['image'] }}" alt="{{ $nm }}" loading="lazy" width="320" height="320">@else<x-icon name="package"/>@endif</span>
        <span class="pcard__body">
          <span class="pcard__sku u-mono">{{ $p['sku'] }}</span>
          <span class="pcard__name">{{ \Illuminate\Support\Str::limit($nm, 72) }}</span>
          <span class="pcard__foot"><b class="u-mono u-nums">${{ number_format($p['price'], 2) }}</b>@if($p['stock_status'] === 'instock')<small class="pcard__ok">{{ __('In stock') }}</small>@else<small>{{ __('Call for stock') }}</small>@endif</span>
        </span>
      </a>
    @endforeach
  </div>
</div></section>
@endif

@if(count($related ?? []))
<section class="section"><div class="wrap">
  <div class="s-head s-head--between">
    <div><h2>{{ __('Related guides') }}</h2></div>
    <a class="s-link" href="/guides">{{ __('All driveshaft guides') }} <x-icon name="arrow-right"/></a>
  </div>
  <div class="uguides guide-related">
    @foreach($related as $slug => $r)
      <a class="uguide" href="/guides/{{ $slug }}"><x-icon :name="$r['icon']"/><b>{{ strtok($r['h1'], ':') }}</b><span>{{ $r['card'] }}</span></a>
    @endforeach
  </div>
</div></section>
@endif

<section class="section section--tint"><div class="wrap"><div class="ctaband">
  <h2>{{ __('Put the numbers to work') }}</h2>
  <p>{{ __('Enter your measurements in the online builder and see the price of the exact assembly, or draw the shaft in the designer and send it to the shop. Questions: (813) 524-6491, Florida.') }}</p>
  <div class="cta"><a href="/online-builder" class="btn btn-primary">{{ __('Open the online builder') }} <x-icon name="arrow-right"/></a><a href="/driveshaft-designer" class="btn btn-ghost">{{ __('Driveshaft designer') }}</a><a href="/contact" class="btn btn-ghost">{{ __('Talk to a tech') }}</a></div>
</div></div></section>
@endsection
