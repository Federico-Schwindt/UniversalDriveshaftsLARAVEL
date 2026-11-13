{{-- The old store's measuring guides (App\Support\ResourceGuides) at their old address /resources. Linked from the footer only. --}}
@extends('layouts.app')
@php $base = rtrim(config('app.url'), '/'); @endphp
@section('title', 'Driveshaft measuring guides: yokes, u-joints, driveshafts | UDS')
@section('desc', 'How to identify and measure slip yokes, flange yokes, u-joints, end yokes, companion yokes and one or two piece driveshafts, with pictures and size charts.')
@section('canonical', '/resources')

@push('schema')
<script type="application/ld+json">{!! json_encode([
  '@@context' => 'https://schema.org', '@@type' => 'CollectionPage', '@@id' => $base . '/resources', 'name' => 'Measuring guides',
  'hasPart' => array_values(array_map(fn ($p) => ['@@type' => 'TechArticle', 'headline' => $p['title'], 'url' => $base . \App\Support\ResourceGuides::url($p['path'])], $all)),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@include('partials.breadcrumb-schema', ['crumbs' => [[__('Home'), $base . '/'], [__('Measuring guides'), $base . '/resources']]])
@endpush

@section('content')
<section class="phero guide-hero rg-hero"><div class="wrap">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Measuring guides') }}</span></nav>
  <div class="guide-hero__in">
    <h1>{{ __('Measuring guides') }}</h1>
    <p class="lead">{{ __('Identify and measure the part you have: yokes, u-joints and complete driveshafts, with pictures and size charts.') }}</p>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="rg-cards">
    @foreach($guides as $p)
      @php
        $img = collect($p['blocks'])->firstWhere('type', 'images')['items'][0] ?? null;
        $kids = \App\Support\ResourceGuides::children($p['path']);
      @endphp
      <article class="rg-card">
        <a class="rg-card__media" href="{{ \App\Support\ResourceGuides::url($p['path']) }}" tabindex="-1" aria-hidden="true">@if($img)<img src="{{ $img['src'] }}" alt="" width="{{ $img['w'] }}" height="{{ $img['h'] }}" loading="lazy">@else<x-icon name="ruler"/>@endif</a>
        <div class="rg-card__body">
          <h2><a href="{{ \App\Support\ResourceGuides::url($p['path']) }}">{{ $p['title'] }}</a></h2>
          <p>{{ $p['desc'] }}</p>
          @if($kids)<ul>@foreach($kids as $k)<li><a href="{{ \App\Support\ResourceGuides::url($k['path']) }}">{{ $k['title'] }}</a></li>@endforeach</ul>@endif
        </div>
      </article>
    @endforeach
  </div>
</div></section>
@endsection
