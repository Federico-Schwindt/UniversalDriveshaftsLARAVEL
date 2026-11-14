@extends('layouts.app')
@section('title', __('Contact Universal Driveshafts | Odessa, FL · (813) 524-6491'))
@section('desc', __('Universal Driveshafts LLC, 11611 Prospect Drive STE 4, Odessa, FL 33556 (Tampa Bay). Call (813) 524-6491, e-mail inquiry@universaldriveshafts.com or send a message.'))
@section('bodyclass','pg')
@section('content')
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Contact us') }}</span></nav>
  <div class="pagehead__row">
    <h1>{{ __('Contact a driveline tech') }}</h1>
    <div class="btn-row"><a class="btn btn-primary btn-sm" href="tel:+18135246491"><x-icon name="phone"/> (813) 524-6491</a><a class="btn btn-ghost btn-sm" href="https://wa.me/18136445707" target="_blank" rel="noopener"><x-icon name="whatsapp"/> {{ __('WhatsApp') }}</a></div>
  </div>
  <p class="pagehead__sub">{{ __('Send us the part number, the measurements, or just the problem. A driveline tech reads every message.') }}</p>
</div>

<section class="catsec catsec--end"><div class="wrap"><div class="pg-cols">
  <div class="pg-form" id="fitment">
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
    <span class="dsg-attach__lbl">{{ __('Drawing attached') }}</span>
    <b>{{ substr_count($dsgSections, '+') + 0 === 2 ? __('Driveshaft two piece') : __('Driveshaft one piece') }} · <span class="u-mono">{{ $dsgSections }}</span></b>
    @if($dsgDims)<p class="u-mono">@foreach($dsgDims as $k => $v){{ $k }} {{ $v }}@if(!$loop->last) · @endif @endforeach</p>@else<p>{{ __('No measurements entered yet. You can still send it; we will ask for them.') }}</p>@endif
    <a href="{{ $dsgUrl }}" class="textlink">{{ __('Open the drawing') }} <x-icon name="arrow-right"/></a>
  </div>
</div>
@endif
<form class="fitform" method="POST" action="/fitment-inquiry#fitment">
        @csrf{!! \App\Support\SpamGuard::fields() !!}
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
        @if(\App\Services\SmsService::on())<div class="full"><label class="consent">
          <input type="checkbox" name="sms_consent" value="yes">
          <span>{!! __('By checking this box, I agree to receive SMS messages about Customer Services/Support from Universal Driveshafts LLC at the phone number provided above. The SMS frequency may vary. Data rates may apply. Reply HELP for assistance. Reply STOP to opt-out of receiving SMS messages. Check our :link.', ['link' => '<a href="/privacy-policy">' . e(__('Privacy Policy')) . '</a>']) !!}</span>
        </label></div>@endif
        <button type="submit">{{ __('Send message') }}</button>
      </form>
      @endif
  </div>

  <aside class="pg-side">
    <div class="pg-card">
      <h2>Universal Driveshafts LLC</h2>
      {{-- the full address is public for local search (owner, 2026-11-04: Odessa is the shop) --}}
      <p><a href="{{ config('uds.pickup.maps') }}" target="_blank" rel="noopener">11611 Prospect Drive STE 4<br>Odessa, FL 33556</a><br><span class="muted">{{ __('Tampa Bay area, Florida') }}</span></p>
      <p>{{ __('Monday to Friday, 8:00am to 5:00pm') }}<br>{{ __('Saturday, 9:00am to 2:00pm') }}</p>
    </div>
    <div class="pg-card">
      <h2>{{ __('Reach us directly') }}</h2>
      <div class="pg-rows">
        <a href="tel:+18135246491"><x-icon name="phone"/><span class="pg-tel">(813) 524-6491</span></a>
        <a href="tel:+18136445707"><x-icon name="phone"/><span class="pg-tel">(813) 644-5707</span> <span class="muted">{{ __('OEM and fleet') }}</span></a>
        <a href="mailto:inquiry@universaldriveshafts.com"><x-icon name="mail"/>inquiry@universaldriveshafts.com</a>
      </div>
    </div>
    <div class="pg-card">
      <h2>{{ __('Faster than a message') }}</h2>
      <div class="pg-rows">
        <a href="/track-order"><x-icon name="truck"/>{{ __('Track an order') }}</a>
        <a href="/rma"><x-icon name="returns"/>{{ __('Returns and RMA') }}</a>
        <a href="/online-builder"><x-icon name="wrench"/>{{ __('Price a custom driveshaft') }}</a>
      </div>
    </div>
  </aside>
</div></div></section>
@endsection
