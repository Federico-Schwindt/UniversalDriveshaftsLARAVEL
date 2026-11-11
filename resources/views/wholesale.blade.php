@extends('layouts.app')
@section('title', __('Wholesale and trade accounts | Universal Driveshafts'))
@section('desc', __('Apply for a Universal Driveshafts wholesale account: trade pricing on u-joints, yokes and driveshafts for repair shops, resellers and fleets, applied at checkout.'))
@section('bodyclass','pg')
@section('content')
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Wholesale') }}</span></nav>
  <div class="pagehead__row">
    <h1>{{ __('Wholesale and trade accounts') }}</h1>
    <div class="btn-row"><a class="btn btn-ghost btn-sm" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a></div>
  </div>
  <p class="pagehead__sub">{{ __('Shops, drivelines, resellers and fleets get standing trade pricing on driveshafts, yokes and parts.') }} {{ __('Approved accounts get a standing discount off catalog pricing, applied automatically at checkout.') }}</p>
</div>

<section class="catsec catsec--end"><div class="wrap"><div class="pg-cols">
  <div>
    @auth
      @if(session('acct_ok'))<p role="status" class="pg-msg ok">{{ session('acct_ok') }}</p>@endif
      @if(session('acct_err'))<p role="alert" class="pg-msg bad">{{ session('acct_err') }}</p>@endif
      @if(($authUser->wholesale_status ?? 'none') === 'approved')
        <div class="pg-form"><div class="fit-thanks"><h3>@php $wTier = \App\Services\B2bAccounts::tierOf($authUser); $wPct = rtrim(rtrim(number_format(\App\Services\B2bAccounts::tiers()[$wTier]['pct'], 2), '0'), '.'); @endphp{{ __("You're a :group wholesale account", ['group' => \App\Services\B2bAccounts::tiers()[$wTier]['name']]) }} <x-icon name="check"/></h3><p>{{ __('Your :pct% trade discount is applied automatically. Happy ordering!', ['pct' => $wPct]) }}</p></div></div>
      @elseif(($authUser->wholesale_status ?? 'none') === 'pending')
        <div class="pg-form"><div class="fit-thanks" style="background:#eef4ff;border-color:#c4d4f5;color:#1c3d7a"><h3 style="color:#1c3d7a">{{ __('Application received') }}</h3><p>{{ __("Our team is reviewing your wholesale application. We'll email you once it's approved.") }}</p></div></div>
      @else
        <div class="pg-form"><h2>{{ __('Apply for a Wholesale Account') }}</h2>
          <p class="sub">{{ __("Tell us about your business and we'll set up trade pricing.") }}</p>
          <form class="fitform" method="POST" action="/wholesale">@csrf
            <div><label>{{ __('Business Name') }}</label><input type="text" name="business_name" required></div>
            <div><label>{{ __('Business Type') }}</label><select name="business_type" class="fsel">@foreach(['Driveline / Repair Shop', 'Reseller / Distributor', 'Fleet Operator', 'Manufacturer / OEM', 'Other'] as $bt)<option value="{{ $bt }}">{{ __($bt) }}</option>@endforeach</select></div>
            <div><label>{{ __('Tax ID / Resale #') }}</label><input type="text" name="tax_id"></div>
            <div><label>{{ __('Est. Monthly Volume') }}</label><input type="text" name="volume" placeholder="{{ __('e.g. 10-20 shafts') }}"></div>
            <div class="full"><label>{{ __('Anything else?') }}</label><input type="text" name="notes" placeholder="{{ __('Tell us about your business') }}"></div>
            <button type="submit">{{ __('Submit Application') }}</button>
          </form>
        </div>
      @endif
    @else
      <div class="pg-form is-center">
        <h2>{{ __('Sign in to apply') }}</h2>
        <p class="sub">{{ __('Wholesale pricing is tied to your account. Sign in or create one to apply.') }}</p>
        <a href="/login?redirect=/wholesale" class="btn btn-primary">{{ __('Sign In') }}</a>
        <a href="/register?redirect=/wholesale" class="btn btn-ghost">{{ __('Create Account') }}</a>
      </div>
    @endauth
  </div>
  <aside class="pg-side">
    <div class="pg-card">
      <h2>{{ __('What an account gets you') }}</h2>
      <ul class="pg-checks">
        <li>{{ __('Trade pricing') }}: {{ __('a standing discount, applied automatically at checkout') }}</li>
        <li>{{ __('Fast reorders') }}: <a href="/quick-order">{{ __('quick order by part number') }}</a> {{ __('and saved builds') }}</li>
        <li>{{ __('Priority help from our team on fitment, custom builds and fulfillment.') }}</li>
      </ul>
    </div>
    <div class="pg-card">
      <h2>{{ __('Questions?') }}</h2>
      <p>{{ __('Call our team about wholesale terms:') }}</p>
      <div class="pg-rows">
        <a href="tel:+18135246491"><x-icon name="phone"/><span class="pg-tel">(813) 524-6491</span></a>
        <a href="mailto:inquiry@universaldriveshafts.com"><x-icon name="mail"/>inquiry@universaldriveshafts.com</a>
      </div>
    </div>
  </aside>
</div></div></section>
@endsection
