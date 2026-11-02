@extends('layouts.app')
@php
  $gCount = array_sum(array_column($group['subcategories'], 'count'));
  $gCats = count($group['subcategories']);
  $gLabel = __($group['label']);
  $gTitle = __(':group: :n parts in :c categories', ['group' => $gLabel, 'n' => number_format($gCount), 'c' => $gCats]) . ' | UDS';
  if (mb_strlen($gTitle) > 60) $gTitle = __(':group: :n parts in stock', ['group' => $gLabel, 'n' => number_format($gCount)]) . ' | UDS';
  $gDesc = __((string) (config('seo-copy.groups.' . $group['slug']) ?: $group['blurb'])) . ' ' . __(':n parts in stock, ships same day from Florida.', ['n' => number_format($gCount)]);
  if (mb_strlen($gDesc) > 160) $gDesc = rtrim(mb_substr($gDesc, 0, 157), ' ,.;') . '...';
@endphp
@section('title', $gTitle)
@section('desc', $gDesc)
@section('canonical', '/shop/' . $group['slug'])
@push('schema')
@include('partials.breadcrumb-schema', ['crumbs' => [
  [__('Home'), rtrim(config('app.url'),'/').'/'], [__('Catalog'), rtrim(config('app.url'),'/').'/shop'], [__($group['label']), url()->current()],
]])
@endpush

@section('content')
@include('shop.partials.style')
<div class="wrap">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <a href="/shop">{{ __('Catalog') }}</a><x-icon name="caret-right"/>
    <span aria-current="page">{{ __($group['label']) }}</span>
  </nav>
</div>
<section class="section" style="padding-top:1rem">
  <div class="wrap">
    <div class="s-head">
      <h1>{{ __($group['label']) }}</h1>
      @if(!empty($group['blurb']) && !config('seo-copy.groups.' . $group['slug']))<p>{{ __($group['blurb']) }}</p>@endif
      @if(config('seo-copy.groups.' . $group['slug']))<p style="color:var(--ink-2);max-width:72ch">{{ config('seo-copy.groups.' . $group['slug']) }} {{ number_format($gCount) }} {{ __('parts across') }} {{ $gCats }} {{ __('categories.') }}</p>@endif
      @if($group['slug'] === 'complete-driveshafts')
        <div class="btn-row"><a class="btn btn-primary" href="/catalog/driveshafts"><x-icon name="crosshair"/> {{ __('Find by vehicle') }}</a><a class="btn btn-ghost" href="/online-builder">{{ __('Build one to spec') }}</a></div>
      @endif
    </div>
    <div class="uhub">
      @foreach($group['subcategories'] as $s)
        <a class="uhubcard" href="/shop/{{ $group['slug'] }}/{{ $s['slug'] }}">
          <span class="uhubcard__media">
            @if($s['image'])<img src="{{ $s['image'] }}" alt="{{ \App\Support\PartGlossary::t($s['name']) }}" width="400" height="300" loading="lazy">
            @else<x-icon name="package"/>@endif
          </span>
          <span class="uhubcard__body"><b>{{ \App\Support\PartGlossary::t($s['name']) }}</b><span>{{ number_format($s['count']) }} {{ __('parts') }}{{ ($t = config("seo-copy.categories.{$s['slug']}.intro")) ? ' · ' . \Illuminate\Support\Str::limit(preg_split('/(?<=[.!?])\s+/', $t)[0], 90) : '' }}</span></span>
        </a>
      @endforeach
    </div>
  </div>
</section>
@endsection
