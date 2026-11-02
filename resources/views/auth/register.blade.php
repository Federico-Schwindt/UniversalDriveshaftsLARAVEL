@extends('layouts.app')
@section('robots','noindex,follow')
@section('title', __('Create an account | Universal Driveshafts'))
@section('desc', __('Create a free Universal Driveshafts account to track orders, save driveshaft builds and dropship addresses, and check out faster.'))
@section('content')
<section class="section"><div class="wrap">
  <div class="auth-split">
    <div class="auth-form">
      <div class="authbox" style="box-shadow:none;border:0;padding:0;max-width:none">
        <h1>{{ __('Create a free account') }}</h1>
        <p class="sub">{{ __('Takes a minute. Nothing is charged until you place an order.') }}</p>
        @if($errors->any())<div class="autherr">{{ $errors->first() }}</div>@endif
        <div class="otp-steps" aria-hidden="true" style="display:flex;gap:6px;margin:0 0 14px;font-size:.74rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-3)"><span style="color:var(--blue)">1 · {{ __('Details') }}</span><span>· 2 · {{ __('Verify e-mail') }}</span></div>
        <form method="POST" action="/register" class="authform" novalidate>
          @csrf
          <input type="hidden" name="_ts" value="{{ $formTs }}">
          {{-- left empty by people; bots fill every field --}}
          <div aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden"><label for="r-website">Website</label><input id="r-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="fgrid">
            <div class="field"><label for="r-name">{{ __('Full name') }}</label><input id="r-name" type="text" name="name" value="{{ old('name', $prefill['name'] ?? '') }}" autocomplete="name" required></div>
            <div class="field"><label for="r-phone">{{ __('Phone') }} <small>{{ __('(optional)') }}</small></label><input id="r-phone" type="tel" name="phone" value="{{ old('phone', $prefill['phone'] ?? '') }}" autocomplete="tel" inputmode="tel"></div>
          </div>
          <div class="field"><label for="r-email">{{ __('Email address') }}</label><input id="r-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required><small>{{ __('We send a 6-digit code here to confirm it. Order confirmations and tracking go here too.') }}</small></div>
          <div class="fgrid">
            <div class="field"><label for="r-pw">{{ __('Password') }}</label><div class="pw"><input id="r-pw" type="password" name="password" autocomplete="new-password" required minlength="8"><button type="button" data-toggle-pw="r-pw" aria-label="{{ __('Show password') }}">{{ __('Show') }}</button></div><small>{{ __('At least 8 characters.') }}</small></div>
            <div class="field"><label for="r-pw2">{{ __('Confirm password') }}</label><div class="pw"><input id="r-pw2" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8"><button type="button" data-toggle-pw="r-pw2" aria-label="{{ __('Show password') }}">{{ __('Show') }}</button></div></div>
          </div>
          <button type="submit" class="btn btn-primary btn-block">{{ __('Continue') }} <x-icon name="arrow-right"/></button>
          <p class="legal">{{ __('By creating an account you agree to the') }} <a href="/terms">{{ __('Terms of use') }}</a> {{ __('and the') }} <a href="/privacy-policy">{{ __('Privacy policy') }}</a>.</p>
        </form>
        @if($googleClient)
          <div class="ordiv"><span>{{ __('or') }}</span></div>
          <form method="POST" action="/auth/google" id="g-form">@csrf<input type="hidden" name="credential" id="g-cred"></form>
          <div id="g_id_onload" data-client_id="{{ $googleClient }}" data-callback="udsGoogleCb" data-auto_prompt="false"></div>
          <div class="g_id_signin" data-type="standard" data-theme="outline" data-size="large" data-width="360" data-text="signup_with"></div>
          <script>function udsGoogleCb(r){document.getElementById('g-cred').value=r.credential;document.getElementById('g-form').submit();}</script>
          <script src="https://accounts.google.com/gsi/client" async defer></script>
        @endif
        <p class="authalt">{{ __('Already have an account?') }} <a href="/login">{{ __('Sign in') }}</a></p>
      </div>
    </div>
    <div class="auth-benes">
      <div>
        <h2>{{ __('Built for shops that order twice.') }}</h2>
        <p class="bsub">{{ __('One account keeps your builds, your addresses and your history in one place.') }}</p>
      </div>
      <ul>
        <li><span class="bi"><x-icon name="package"/></span><div><b>{{ __('Track every order') }}</b><span>{{ __('Live UPS and FedEx tracking, invoices and history.') }}</span></div></li>
        <li><span class="bi"><x-icon name="wrench"/></span><div><b>{{ __('Save your driveshaft builds') }}</b><span>{{ __('Spec it once in the builder, reopen it any time to reorder.') }}</span></div></li>
        <li><span class="bi"><x-icon name="truck"/></span><div><b>{{ __('Dropship addresses') }}</b><span>{{ __('Save the yards and shops you ship to and reuse them at checkout.') }}</span></div></li>
        <li><span class="bi"><x-icon name="certificate"/></span><div><b>{{ __('Wholesale pricing') }}</b><span>{{ __('Shops and resellers can apply for account pricing.') }}</span></div></li>
      </ul>
      <div class="auth-trust"><span>Florida</span><span>{{ __('Since 2018') }}</span><span>(813) 524-6491</span></div>
    </div>
  </div>
</div></section>
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-toggle-pw]').forEach(function(b){b.addEventListener('click',function(){var i=document.getElementById(b.dataset.togglePw);var show=i.type==='password';i.type=show?'text':'password';b.textContent=show?@json(__('Hide')):@json(__('Show'));b.setAttribute('aria-label',show?@json(__('Hide password')):@json(__('Show password')));});});</script>
@endpush
