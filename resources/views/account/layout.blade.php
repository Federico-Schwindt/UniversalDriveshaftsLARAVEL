@extends('layouts.app')
@section('robots','noindex,follow')
{{-- a string @section is stored escaped: decode it so the __() key matches and the title isn't escaped twice --}}
@section('title', __(html_entity_decode(trim($__env->yieldContent('atitle','My Account')), ENT_QUOTES | ENT_HTML5, 'UTF-8')).' | Universal Driveshafts')
@section('desc', __('Your Universal Driveshafts account dashboard, orders, tracking, saved driveshaft builds, dropship addresses and custom requests.'))
@section('content')
@include('account.partials.style')
@php
  $first = explode(' ', trim($user->name ?: __('there')))[0];
  $initials = collect(preg_split('/\s+/', trim($user->name ?: $user->email)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
  $wholesale = ($user->wholesale_status ?? 'none') === 'approved';
@endphp

<div class="acct-hero">
  <div class="wrap acct-hero-in">
    <a href="/account/settings#avatar" class="acct-av acct-av--img" title="{{ __('Change profile picture') }}"><img src="{{ \App\Support\Avatar::url($user) }}" alt="{{ __('Profile picture') }}" width="64" height="64"></a>
    <div class="acct-idt">
      <h1>{{ $user->name ?: $user->email }}</h1>
      <div class="acct-meta">
        <span>{{ $user->email }}</span>
        @if($user->email_verified_at)<span class="chip chip--ok"><x-icon name="check"/> {{ __('Verified') }}</span>@else<span class="chip chip--warn"><x-icon name="warning"/> {{ __('Not verified') }}</span>@endif
        @if($wholesale)<span class="chip chip--blue"><x-icon name="certificate"/> {{ __('Wholesale') }}</span>@endif
        <span class="mono">{{ __('Account #') }}{{ str_pad($user->id, 5, '0', STR_PAD_LEFT) }}</span>
      </div>
    </div>
    <div class="acct-hero-act">
      <a href="/shop" class="btn btn-primary"><x-icon name="cart"/> {{ __('Shop parts') }}</a>
      <a href="/quick-order" class="btn btn-ghost">{{ __('Quick order') }}</a>
    </div>
  </div>
</div>

<section class="acct-page"><div class="wrap"><div class="acct-shell">
  @include('account.partials.sidebar', ['active' => trim($__env->yieldContent('active','dashboard'))])
  <main class="acct-main" id="acct-main">
    @if(empty($user->email_verified_at))
      <div class="acct-note">
        <x-icon name="mail"/>
        <span>{!! __('<b>Confirm your e-mail</b> to see orders and requests placed with :email before you registered.', ['email' => e($user->email)]) !!}</span>
        <form method="post" action="/account/verify/send">@csrf<button class="btn btn-primary btn-sm">{{ __('Send me a code') }}</button></form>
      </div>
    @endif
    @if(session('acct_ok'))<div class="acct-ok" role="status"><x-icon name="check"/> <span>{{ session('acct_ok') }}</span></div>@endif
    @if(session('acct_err'))<div class="acct-err" role="alert"><x-icon name="warning"/> <span>{{ session('acct_err') }}</span></div>@endif
    @if(($errors ?? null) && $errors->any())<div class="acct-err" role="alert"><x-icon name="warning"/> <span>{{ $errors->first() }}</span></div>@endif
    @yield('acct')
  </main>
</div></div></section>
@endsection
