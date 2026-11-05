@extends('layouts.app')
@section('title', __('Contact Universal Driveshafts | Odessa, FL · (813) 524-6491'))
@section('desc', __('Universal Driveshafts LLC, 11611 Prospect Drive STE 4, Odessa, FL 33556 (Tampa Bay). Call (813) 524-6491, e-mail inquiry@universaldriveshafts.com or send a message.'))
@section('content')
<section class="phero"><div class="wrap">
  <div class="crumb"><a href="/">{{ __('Home') }}</a><span class="sep">/</span><span>{{ __('Contact us') }}</span></div>
  <div class="accentline"></div>
  <h1>{{ __('Contact a driveline tech') }}</h1>
  <p class="lead">{{ __('Send us the part number, the measurements, or just the problem. A driveline tech reads every message.') }}</p>
</div></section>

<section class="section section--snug"><div class="wrap"><div class="guidewrap">
  <div class="gmain">
    <div class="fitform-wrap" id="fitment">
      @if(session('fitment_ok'))
        <div class="fit-thanks">
          <h2>{{ __('Message received') }}</h2>
          <p>{{ __('We will come back to you shortly. If it is urgent, call (813) 524-6491 and ask for the shop.') }}</p>
        </div>
      @else
      @php
  $dsgSections = preg_match('/^[A-Z0-9+]{2,20}$/', (string) request('designer')) ? request('designer') : null;
  $dsgDims = $dsgSections ? \App\Http\Controllers\DesignerController::parseDims((string) request('dims')) : [];
  $dsgUrl = $dsgSections ? url('/driveshaft-designer/' . (substr_count($dsgSections, '+') + 0) . '/' . $dsgSections) . ($dsgDims ? '#' . http_build_query($dsgDims, '', '&') : '') : null;
@endphp
@if($dsgSections)
<div class="dsg-attach" id="dsg-attach">
  <div class="dsg-attach__img"><img src="/driveshaft-designer/svg/{{ $dsgSections }}?style=uds&dims={{ urlencode(http_build_query($dsgDims, '', '&')) }}" alt="{{ __('Your driveshaft drawing') }}"></div>
  <div class="dsg-attach__body">
    <span class="kicker">{{ __('Drawing attached') }}</span>
    <b>{{ substr_count($dsgSections, '+') + 0 === 2 ? __('Driveshaft two piece') : __('Driveshaft one piece') }} · <span class="u-mono">{{ $dsgSections }}</span></b>
    @if($dsgDims)<p class="u-mono">@foreach($dsgDims as $k => $v){{ $k }} {{ $v }}@if(!$loop->last) · @endif @endforeach</p>@else<p>{{ __('No measurements entered yet. You can still send it; we will ask for them.') }}</p>@endif
    <a href="{{ $dsgUrl }}" class="textlink">{{ __('Open the drawing') }} <x-icon name="arrow-right"/></a>
  </div>
</div>
@endif
<form class="fitform" method="POST" action="/fitment-inquiry#fitment">
        @csrf
        <input type="hidden" name="source" value="contact-us">
        @if($dsgSections)<input type="hidden" name="designer_sections" value="{{ $dsgSections }}"><input type="hidden" name="designer_dims" value="{{ http_build_query($dsgDims, '', '&') }}"><input type="hidden" name="designer_url" value="{{ $dsgUrl }}">@endif
        <div><label for="c-first">{{ __('First name') }}</label><input type="text" id="c-first" name="first_name" autocomplete="given-name"></div>
        <div><label for="c-last">{{ __('Last name') }}</label><input type="text" id="c-last" name="last_name" autocomplete="family-name"></div>
        <div><label for="c-co">{{ __('Company') }} <span style="font-weight:400;color:var(--ink-faint)">{{ __('(optional)') }}</span></label><input type="text" id="c-co" name="company" autocomplete="organization"></div>
        <div><label for="c-email">{{ __('Email') }}</label><input type="email" id="c-email" name="email" autocomplete="email" required></div>
        <div class="full"><label for="c-tel">{{ __('Phone') }}</label><input type="tel" id="c-tel" name="phone" autocomplete="tel"></div>
        <div class="full"><label for="c-msg">{{ __('Your message') }}</label><textarea id="c-msg" name="message" rows="5" class="ftxa" placeholder="{{ __('Part number, measurements, vehicle or equipment') }}">@if($dsgSections)Driveshaft drawing: {{ $dsgUrl }}
@if($dsgDims)Measurements: @foreach($dsgDims as $k => $v){{ $k }}={{ $v }} @endforeach
@endif
@endif</textarea></div>
        <div class="full"><label class="consent">
          <input type="checkbox" name="sms_consent" value="yes">
          <span>{!! __('By checking this box, I agree to receive SMS messages about Customer Services/Support from Universal Driveshafts LLC at the phone number provided above. The SMS frequency may vary. Data rates may apply. Reply HELP for assistance. Reply STOP to opt-out of receiving SMS messages. Check our :link.', ['link' => '<a href="/privacy-policy">' . e(__('Privacy Policy')) . '</a>']) !!}</span>
        </label></div>
        <button type="submit">{{ __('Send message') }}</button>
      </form>
      @endif
    </div>
  </div>

  <aside class="gside">
    <div class="sbcard">
      <h2>Universal Driveshafts LLC</h2>
      <p style="font-family:var(--font-h);font-weight:600;color:var(--blue)">{{ __('OEM driveshafts and parts') }}</p>
      {{-- the full address is public for local search (owner, 2026-11-04: Odessa is the shop) --}}
      <p><a href="{{ config('uds.pickup.maps') }}" target="_blank" rel="noopener">11611 Prospect Drive STE 4<br>Odessa, FL 33556</a><br><span class="muted">{{ __('Tampa Bay area, Florida') }}</span></p>
      <h2 style="margin-top:1.4rem">{{ __('Opening hours') }}</h2>
      <p>{{ __('Monday to Friday, 8:00am to 5:00pm') }}<br>{{ __('Saturday, 9:00am to 2:00pm') }}</p>
    </div>
    <div class="sbcard">
      <h2>{{ __('Reach us directly') }}</h2>
      <a class="help-row" href="tel:+18135246491"><span class="ic ic-phone"><x-icon name="phone"/></span>(813) 524-6491</a>
      <a class="help-row" href="tel:+18136445707"><span class="ic ic-phone"><x-icon name="phone"/></span>(813) 644-5707</a>
      <a class="help-row" href="mailto:inquiry@universaldriveshafts.com"><span class="ic ic-mail"><x-icon name="mail"/></span>inquiry@universaldriveshafts.com</a>
    </div>
  </aside>
</div></div></section>
@endsection
