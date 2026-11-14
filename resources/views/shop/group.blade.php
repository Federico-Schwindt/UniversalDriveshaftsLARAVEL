@extends('layouts.app')
@php
  $gCount = array_sum(array_column($group['subcategories'], 'count'));
  $gCats = count($group['subcategories']);
  $gLabel = __($group['label']);
  $gTitle = __(':group: :n parts in :c categories', ['group' => $gLabel, 'n' => number_format($gCount), 'c' => $gCats]) . ' | UDS';
  if (mb_strlen($gTitle) > 60) $gTitle = __(':group: :n parts in stock', ['group' => $gLabel, 'n' => number_format($gCount)]) . ' | UDS';
  $gDesc = __((string) (config('seo-copy.groups.' . $group['slug']) ?: $group['blurb'])) . ' ' . __(':n parts in stock, ships from Florida.', ['n' => number_format($gCount)]);
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
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}">
    <a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/>
    <a href="/shop">{{ __('Catalog') }}</a><x-icon name="caret-right"/>
    <span aria-current="page">{{ __($group['label']) }}</span>
  </nav>
  <div class="pagehead__row">
    <h1>{{ __($group['label']) }}</h1>
    @if($group['slug'] === 'complete-driveshafts')
      <div class="btn-row"><a class="btn btn-primary btn-sm" href="/catalog/driveshafts"><x-icon name="car"/> {{ __('Find by vehicle') }}</a><a class="btn btn-ghost btn-sm" href="/online-builder"><x-icon name="wrench"/> {{ __('Build one to spec') }}</a></div>
    @endif
  </div>
  @php $gAbout = config('seo-copy.groups.' . $group['slug']) ?: $group['blurb']; @endphp
  <p class="pagehead__sub">{{ __(':n parts in :c categories.', ['n' => number_format($gCount), 'c' => $gCats]) }}@if($gAbout) <span class="cinfo"><button type="button" class="cinfo__btn"><x-icon name="info"/>{{ __('About these parts') }}</button><span class="cinfo__pop" role="tooltip">{{ __((string) $gAbout) }}</span></span>@endif</p>
</div>
<section class="catsec"><div class="wrap">
  @include('shop.partials.cat-tiles', ['group' => $group['slug'], 'subs' => $group['subcategories']])
</div></section>
@endsection
