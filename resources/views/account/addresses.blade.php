@extends('account.layout')
@section('active','addresses')
@section('atitle','Addresses')
@section('acct')
@php
  $editing = $edit === 'new' ? (object) [] : ($edit ? $addresses->firstWhere('id', (int) $edit) : null);
  $f = fn ($k, $d = '') => old($k, $editing->$k ?? $d);
  $hasBilling = filled($user->billing_address_1);
  $editBilling = request()->has('billing') || $errors->hasAny(['billing_address_1', 'billing_city', 'billing_postcode', 'billing_country']);
@endphp

<div class="acct-h"><div><h2 class="t">{{ __('Addresses') }}</h2><p>{{ __('Your billing address, your own shipping addresses and the dropship addresses of your customers. Checkout fills them in for you.') }}</p></div></div>

{{-- billing --}}
<div class="apanel">
  <div class="apanel-h"><h2><x-icon name="user"/> {{ __('Billing address') }}</h2>@if($hasBilling && !$editBilling)<a class="more" href="/account/addresses?billing=1#billing">{{ __('Edit') }}</a>@endif</div>
  <div class="apanel-b" id="billing">
    @if($hasBilling && !$editBilling)
      <div style="line-height:1.55;font-size:.93rem">
        <b>{{ $user->name }}</b>@if($user->company)<br>{{ $user->company }}@endif<br>
        {{ $user->billing_address_1 }}@if($user->billing_address_2), {{ $user->billing_address_2 }}@endif<br>
        {{ $user->billing_city }}@if($user->billing_state), {{ $user->billing_state }}@endif {{ $user->billing_postcode }} · {{ config('countries')[$user->billing_country] ?? $user->billing_country }}
      </div>
    @elseif(!$editBilling)
      <div class="aempty" style="padding:8px 0;text-align:left">{{ __('No billing address yet. It is the address on your card, used for the invoice.') }} <a href="/account/addresses?billing=1#billing">{{ __('Add billing address') }} <x-icon name="arrow-right"/></a></div>
    @else
      <form method="POST" action="/account/billing" class="aform" data-geo-noip>@csrf
        <div class="full"><label for="b1">{{ __('Street address') }}</label><input id="b1" type="text" name="billing_address_1" value="{{ old('billing_address_1', $user->billing_address_1) }}" autocomplete="off" data-gplaces data-addr-group="billing" placeholder="{{ __('Start typing your address…') }}" required>@error('billing_address_1')<span class="ferr">{{ $message }}</span>@enderror</div>
        <div class="full"><label for="b2">{{ __('Apartment, suite, unit') }} <small>{{ __('(optional)') }}</small></label><input id="b2" type="text" name="billing_address_2" value="{{ old('billing_address_2', $user->billing_address_2) }}" autocomplete="billing address-line2"></div>
        <div><label for="bc">{{ __('City') }}</label><input id="bc" type="text" name="billing_city" value="{{ old('billing_city', $user->billing_city) }}" autocomplete="billing address-level2" data-geo="city" data-addr-group="billing" required>@error('billing_city')<span class="ferr">{{ $message }}</span>@enderror</div>
        <div><label for="bs">{{ __('State / province') }}</label><input id="bs" type="text" name="billing_state" value="{{ old('billing_state', $user->billing_state) }}" autocomplete="billing address-level1" data-geo="state" data-addr-group="billing"></div>
        <div><label for="bz">{{ __('ZIP / postal code') }}</label><input id="bz" type="text" name="billing_postcode" value="{{ old('billing_postcode', $user->billing_postcode) }}" autocomplete="billing postal-code" data-geo="zip" data-addr-group="billing" required>@error('billing_postcode')<span class="ferr">{{ $message }}</span>@enderror</div>
        <div><label for="bco">{{ __('Country') }}</label><select id="bco" name="billing_country" autocomplete="billing country" data-geo="country" data-addr-group="billing">@foreach(config('countries') as $cc => $cn)<option value="{{ $cc }}" @selected(old('billing_country', $user->billing_country ?: 'US') === $cc)>{{ $cn }}</option>@endforeach</select></div>
        <div class="aform-foot"><button class="btn btn-primary" type="submit">{{ __('Save billing address') }}</button>@if($hasBilling)<a class="btn btn-ghost" href="/account/addresses">{{ __('Cancel') }}</a>@endif</div>
      </form>
    @endif
  </div>
</div>

{{-- address book: the customer's own places, then dropship addresses for their customers --}}
@php
  $kind = old('kind', $editing->kind ?? $newKind);
  $own = $addresses->where('kind', '!=', 'dropship');
  $drop = $addresses->where('kind', 'dropship');
@endphp
@if($editing)
<div class="apanel">
  <div class="apanel-h"><h2><x-icon name="{{ $kind === 'dropship' ? 'package' : 'truck' }}"/> {{ !empty($editing->id) ? __('Edit address') : ($kind === 'dropship' ? __('New dropship address') : __('New shipping address')) }}</h2></div>
  <div class="apanel-b">
      <form method="POST" action="/account/addresses" class="aform" id="addr-form" data-geo-noip>@csrf
        @if(!empty($editing->id))<input type="hidden" name="id" value="{{ $editing->id }}">@endif
        <fieldset class="full addr-kind"><legend>{{ __('Address type') }}</legend>
          <label class="chk"><input type="radio" name="kind" value="shipping" @checked($kind !== 'dropship')> <span><b>{{ __('My address') }}</b> {{ __('Your shop, another location of your company, or your home') }}</span></label>
          <label class="chk"><input type="radio" name="kind" value="dropship" @checked($kind === 'dropship')> <span><b>{{ __('Dropship address') }}</b> {{ __('One of your customers: we ship straight to them') }}</span></label>
        </fieldset>
        <div><label for="a-label">{{ __('Label') }} <small>{{ __('(optional)') }}</small></label><input id="a-label" type="text" name="label" value="{{ $f('label') }}" placeholder="{{ __('Main shop, Warehouse 2, customer name…') }}" maxlength="60"></div>
        <div><label for="a-name">{{ __('Contact name') }}</label><input id="a-name" type="text" name="name" value="{{ $f('name', empty($editing->id) && $kind !== 'dropship' ? $user->name : '') }}" autocomplete="shipping name">@error('name')<span class="ferr">{{ $message }}</span>@enderror</div>
        <div class="full"><label for="a-co">{{ __('Company') }} <small>{{ __('(optional)') }}</small></label><input id="a-co" type="text" name="company" value="{{ $f('company', empty($editing->id) && $kind !== 'dropship' ? ($user->company ?? '') : '') }}" autocomplete="shipping organization"></div>
        <div class="full"><label for="a-st">{{ __('Street address') }}</label><input id="a-st" type="text" name="street" value="{{ $f('street') }}" autocomplete="off" data-gplaces data-addr-group="ship" placeholder="{{ __('Start typing your address…') }}" required>@error('street')<span class="ferr">{{ $message }}</span>@enderror</div>
        <div class="full"><label for="a-un">{{ __('Apartment, suite, unit') }} <small>{{ __('(optional)') }}</small></label><input id="a-un" type="text" name="unit" value="{{ $f('unit') }}" autocomplete="shipping address-line2"></div>
        <div><label for="a-ci">{{ __('City') }}</label><input id="a-ci" type="text" name="city" value="{{ $f('city') }}" autocomplete="shipping address-level2" data-geo="city" data-addr-group="ship" required>@error('city')<span class="ferr">{{ $message }}</span>@enderror</div>
        <div><label for="a-sa">{{ __('State / province') }}</label><input id="a-sa" type="text" name="state" value="{{ $f('state') }}" autocomplete="shipping address-level1" data-geo="state" data-addr-group="ship"></div>
        <div><label for="a-zp">{{ __('ZIP / postal code') }}</label><input id="a-zp" type="text" name="postcode" value="{{ $f('postcode') }}" autocomplete="shipping postal-code" data-geo="zip" data-addr-group="ship" required>@error('postcode')<span class="ferr">{{ $message }}</span>@enderror</div>
        <div><label for="a-cc">{{ __('Country') }}</label><select id="a-cc" name="country" autocomplete="shipping country" data-geo="country" data-addr-group="ship">@foreach(config('countries') as $cc => $cn)<option value="{{ $cc }}" @selected($f('country', 'US') === $cc)>{{ $cn }}</option>@endforeach</select>@error('country')<span class="ferr">{{ $message }}</span>@enderror</div>
        <div><label for="a-ph">{{ __('Phone') }} <small>{{ __('(for the carrier)') }}</small></label><input id="a-ph" type="tel" name="phone" value="{{ $f('phone') }}" autocomplete="shipping tel"></div>
        <div class="full" style="display:flex;flex-direction:column;gap:10px">
          <label class="chk"><input type="checkbox" name="residential" value="1" @checked(session()->hasOldInput('street') ? old('residential') : ($editing->residential ?? false))> {{ __('This is a home (residential) address') }}</label>
          <label class="chk" id="addr-def"><input type="checkbox" name="is_default" value="1" @checked(session()->hasOldInput('street') ? old('is_default') : ($editing->is_default ?? $own->isEmpty()))> {{ __('Use as my default shipping address') }}</label>
        </div>
        <div class="aform-foot"><button class="btn btn-primary" type="submit">{{ __('Save address') }}</button><a class="btn btn-ghost" href="/account/addresses">{{ __('Cancel') }}</a><span class="note">{{ __('PO boxes cannot receive UPS or FedEx deliveries.') }}</span></div>
      </form>
      <script>
        // a dropship address is never the default: hide the box while "Dropship address" is picked
        (function(){var f=document.getElementById('addr-form'),d=document.getElementById('addr-def');
          function sync(){var on=f.querySelector('[name=kind]:checked').value==='dropship';d.hidden=on;if(on)d.querySelector('input').checked=false;}
          f.querySelectorAll('[name=kind]').forEach(function(r){r.addEventListener('change',sync);});sync();})();
      </script>
  </div>
</div>
@else
@foreach([
  ['shipping', $own, 'truck', __('My shipping addresses'), __('Your shop, other locations of your company, or your home.'), __('Add a shipping address')],
  ['dropship', $drop, 'package', __('Dropship addresses'), __('Your customers. We ship straight to them.'), __('Add a dropship address')],
] as [$k, $list, $icon, $title, $sub, $addLabel])
<div class="apanel">
  <div class="apanel-h"><div><h2><x-icon name="{{ $icon }}"/> {{ $title }}</h2><p class="muted" style="margin:4px 0 0;font-size:.86rem">{{ $sub }}</p></div>@if($list->count())<a class="more" href="/account/addresses?edit=new&kind={{ $k }}#addr-form">{{ __('Add address') }}</a>@endif</div>
  <div class="apanel-b">
      <div class="addr-grid">
        @foreach($list as $a)
          <div class="addr {{ $a->is_default ? 'is-def' : '' }}">
            @php $head = $a->label ?: ($a->company ?: $a->name); @endphp
            <div class="lbl"><b>{{ $head }}</b>@if($a->is_default)<span class="chip chip--blue">{{ __('Default') }}</span>@endif</div>
            @if($a->name && $a->name !== $head)<span class="who">{{ $a->name }}</span>@endif
            @if($a->company && $a->company !== $head)<span>{{ $a->company }}</span>@endif
            <span>{{ $a->street }}@if($a->unit), {{ $a->unit }}@endif</span>
            <span>{{ $a->city }}@if($a->state), {{ $a->state }}@endif {{ $a->postcode }}</span>
            <span class="muted">{{ config('countries')[$a->country] ?? $a->country }}@if($a->residential) · {{ __('Residential') }}@endif</span>
            @if($a->phone)<span class="muted">{{ $a->phone }}</span>@endif
            <div class="addr-act">
              <a href="/account/addresses?edit={{ $a->id }}#addr-form">{{ __('Edit') }}</a>
              @if($k === 'shipping' && !$a->is_default)<form method="POST" action="/account/addresses/{{ $a->id }}/default">@csrf<button type="submit">{{ __('Make default') }}</button></form>@endif
              <form method="POST" action="/account/addresses/{{ $a->id }}/delete" onsubmit="return confirm(@json(__('Remove this address?')))">@csrf<button type="submit" class="rm">{{ __('Remove') }}</button></form>
            </div>
          </div>
        @endforeach
        <a class="addr-add" href="/account/addresses?edit=new&kind={{ $k }}#addr-form"><x-icon name="plus"/>{{ $addLabel }}</a>
      </div>
  </div>
</div>
@endforeach
@endif
@if($editing || $editBilling)
{{-- the Maps key rides on the tag: geo.js fetches Google only when a street field is focused --}}
<script src="/js/geo.js?v={{ @filemtime(public_path('js/geo.js')) }}" data-gmaps-key="{{ config('uds.gmaps_key') }}"></script>
@endif
@endsection
