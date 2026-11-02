@extends('layouts.app')
@section('title', __('Wholesale and trade accounts | Universal Driveshafts'))
@section('desc', __('Apply for a Universal Driveshafts wholesale account: trade pricing on u-joints, yokes and driveshafts for repair shops, resellers and fleets, applied at checkout.'))
@section('content')
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">{{ __('Home') }}</a><span class="sep">/</span><span>{{ __('Wholesale') }}</span></div><div class="accentline"></div>
<h1>{{ __('Wholesale and trade accounts') }}</h1>
<p class="lead">{{ __('Shops, drivelines, resellers and fleets get standing trade pricing on driveshafts, yokes and parts.') }}</p></div></section>

<section class="section"><div class="wrap"><div class="shopwrap">
  <div class="gmain">
    <div class="why-grid" style="margin-bottom:22px">
      <div class="why-card"><h2><x-icon name="certificate"/> {{ __('Trade pricing') }}</h2><p>{{ __('Approved accounts get a standing discount off catalog pricing, applied automatically at checkout.') }}</p></div>
      <div class="why-card"><h2><x-icon name="gauge"/> {{ __('Fast reorders') }}</h2><p>{{ __('Quick-order by part number and saved builds make repeat orders effortless.') }}</p></div>
      <div class="why-card"><h2><x-icon name="certificate"/> {{ __('Dedicated Support') }}</h2><p>{{ __('Priority help from our team on fitment, custom builds and fulfillment.') }}</p></div>
    </div>

    @auth
      @if(session('acct_ok'))<p role="status" style="margin:0 0 14px;padding:10px 14px;border-radius:10px;background:var(--ok-bg);color:var(--ok);font-weight:600">{{ session('acct_ok') }}</p>@endif
      @if(session('acct_err'))<p role="alert" style="margin:0 0 14px;padding:10px 14px;border-radius:10px;background:var(--err-bg);color:var(--err);font-weight:600">{{ session('acct_err') }}</p>@endif
      @if(($authUser->wholesale_status ?? 'none') === 'approved')
        <div class="fit-thanks"><h3>@php $wTier = \App\Services\B2bAccounts::tierOf($authUser); $wPct = rtrim(rtrim(number_format(\App\Services\B2bAccounts::tiers()[$wTier]['pct'], 2), '0'), '.'); @endphp{{ __("You're a :group wholesale account", ['group' => \App\Services\B2bAccounts::tiers()[$wTier]['name']]) }} <x-icon name="check"/></h3><p>{{ __('Your :pct% trade discount is applied automatically. Happy ordering!', ['pct' => $wPct]) }}</p></div>
      @elseif(($authUser->wholesale_status ?? 'none') === 'pending')
        <div class="fit-thanks" style="background:#eef4ff;border-color:#c4d4f5;color:#1c3d7a"><h3 style="color:#1c3d7a">{{ __('Application received') }}</h3><p>{{ __("Our team is reviewing your wholesale application. We'll email you once it's approved.") }}</p></div>
      @else
        <div class="fitform-wrap"><h2>{{ __('Apply for a Wholesale Account') }}</h2>
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
      <div class="fitform-wrap" style="text-align:center">
        <h2>{{ __('Sign in to apply') }}</h2>
        <p class="sub">{{ __('Wholesale pricing is tied to your account. Sign in or create one to apply.') }}</p>
        <a href="/login?redirect=/wholesale" class="btn btn-primary">{{ __('Sign In') }}</a>
        <a href="/register?redirect=/wholesale" class="btn btn-ghost u-ml">{{ __('Create Account') }}</a>
      </div>
    @endauth
  </div>
  <aside class="totbox">
    <h2 style="font-size:1.35rem">{{ __('Questions?') }}</h2>
    <p style="color:var(--ink-soft);font-size:.9rem;line-height:1.6">{{ __('Call our team about wholesale terms:') }}</p>
    <p style="margin:10px 0"><a href="tel:+18135246491" style="color:var(--blue);font-weight:700;font-size:1.1rem">(813) 524-6491</a></p>
    <p style="color:var(--ink-soft);font-size:.85rem">{{ __('OEM & Parts') }}<br>inquiry@universaldriveshafts.com</p>
  </aside>
</div></div></section>
@endsection
