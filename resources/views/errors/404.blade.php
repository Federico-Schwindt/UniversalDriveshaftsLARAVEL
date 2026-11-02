@extends('layouts.app')
@php
  // Unmatched URLs never reach the web group, so SetLocale has not run: resolve the storefront language the same way here.
  if (!request()->route()) {
    $sup = \App\Http\Middleware\SetLocale::SUPPORTED;
    $l = strtolower((string) request()->query('lang', ''));
    if (!in_array($l, $sup, true)) $l = (string) request()->cookie(\App\Http\Middleware\SetLocale::COOKIE);
    if (!in_array($l, $sup, true)) $l = request()->getPreferredLanguage($sup) ?: 'en';
    app()->setLocale($l);
    \Carbon\Carbon::setLocale($l);
  }
@endphp
@section('robots','noindex,follow')
@section('title', __('Page not found (404) | Universal Driveshafts'))
@section('desc', __('That page is not in the Universal Driveshafts catalog. Search by part number, find a driveshaft by vehicle or build one online.'))
@section('bodyclass','is-404')
@php
  $svg = null;
  try { $svg = \App\Http\Controllers\DesignerController::render('01+01R', ['C1' => '404', 'W1' => __('NOT FOUND'), 'T1' => '—']); } catch (\Throwable $e) {}
@endphp
@section('content')
<section class="nf">
  <div class="wrap nf__in">
    <p class="nf__kicker"><span>{{ __('Error 404') }}</span> {{ __('Part not in the catalog') }}</p>
    <h1>{!! __('This page measures <b>404</b> and fits nothing we make.') !!}</h1>
    <p class="nf__lead">{{ __('The link may be old, the part number may have changed, or it was never here. Search by part number, name or series — or find a complete driveshaft by vehicle.') }}</p>
    @if($svg)<div class="nf__art" aria-hidden="true">{!! $svg !!}</div>@endif
    <form class="nf__search" action="/search" method="get" role="search">
      <x-icon name="search"/>
      <input type="search" name="q" placeholder="{{ __('Part number, e.g. 5-1310X or U2594-173') }}" aria-label="{{ __('Search parts') }}" autocomplete="off">
      <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
    </form>
    <div class="nf__links">
      <a href="/shop"><x-icon name="package"/><b>{{ __('Parts catalog') }}</b><span>{{ __('2,000+ yokes, u-joints, flanges and tubing') }}</span></a>
      <a href="/catalog/driveshafts"><x-icon name="crosshair"/><b>{{ __('Find by vehicle') }}</b><span>{{ __('Complete driveshafts by year, make and model') }}</span></a>
      <a href="/online-builder"><x-icon name="wrench"/><b>{{ __('Online builder') }}</b><span>{{ __('Spec a custom driveshaft in minutes') }}</span></a>
      <a href="/driveshaft-designer"><x-icon name="ruler"/><b>{{ __('Driveshaft designer') }}</b><span>{{ __('Measure, draw and send it to the shop') }}</span></a>
      <a href="/track-order"><x-icon name="truck"/><b>{{ __('Track an order') }}</b><span>{{ __('Status and tracking by order number') }}</span></a>
      <a href="/contact"><x-icon name="headset"/><b>{{ __('Talk to the shop') }}</b><span>{{ __('(813) 524-6491 · Mon–Fri') }}</span></a>
    </div>
  </div>
</section>
@endsection
