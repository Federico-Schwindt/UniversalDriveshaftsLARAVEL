@extends('layouts.app')
@section('robots','noindex,follow')
@section('title', __('Sign in | Universal Driveshafts'))
@section('desc', __('Sign in to your Universal Driveshafts account to track orders, save driveshaft builds, save dropship addresses and check out faster.'))
@section('content')
<section class="section"><div class="wrap">
  <div class="auth-split">    <div class="auth-form">
      <div class="authbox" style="box-shadow:none;border:0;padding:0;max-width:none">
        <h1>{{ __('Sign in') }}</h1>
        <p class="sub">{{ __('Welcome back, pick up right where you left off.') }}</p>
        @if($errors->any())<div class="autherr">{{ $errors->first() }}</div>@endif
        <form method="POST" action="/login" class="authform" novalidate>
          @csrf
          <div class="field"><label for="l-email">{{ __('Email address') }}</label><input id="l-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
          <div class="field"><div class="authrow"><label for="l-pw">{{ __('Password') }}</label><a href="/forgot-password">{{ __('Forgot your password?') }}</a></div><div class="pw"><input id="l-pw" type="password" name="password" autocomplete="current-password" required><button type="button" data-toggle-pw="l-pw" aria-label="{{ __('Show password') }}">{{ __('Show') }}</button></div></div>
          {{-- untick on a shared computer: the session then ends when the browser closes --}}
          <label for="l-keep" style="display:flex;align-items:center;gap:9px;margin:-2px 0 14px;font-size:.9rem;font-weight:500;color:var(--ink-2);cursor:pointer"><input id="l-keep" type="checkbox" name="remember" value="1" checked style="width:18px;height:18px;flex:none;margin:0;padding:0;accent-color:var(--blue)"> {{ __('Keep me signed in') }}</label>
          <button type="submit" class="btn btn-primary btn-block">{{ __('Sign in') }} <x-icon name="arrow-right"/></button>
        </form>
        @if($googleClient)
          <div class="ordiv"><span>{{ __('or') }}</span></div>
          <form method="POST" action="/auth/google" id="g-form">@csrf<input type="hidden" name="credential" id="g-cred"></form>
          <div id="g_id_onload" data-client_id="{{ $googleClient }}" data-callback="udsGoogleCb" data-auto_prompt="false"></div>
          <div class="g_id_signin" data-type="standard" data-theme="outline" data-size="large" data-width="360" data-text="continue_with"></div>
          <script>function udsGoogleCb(r){document.getElementById('g-cred').value=r.credential;document.getElementById('g-form').submit();}</script>
          <script src="https://accounts.google.com/gsi/client" async defer></script>
        @else
          <div class="ordiv"><span>{{ __('or') }}</span></div>
          <button type="button" class="gbtn" onclick="var n=document.getElementById('gbtn-note');if(n)n.hidden=false;">
            <svg class="gicon" width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
            <span>{{ __('Continue with Google') }}</span>
          </button>
          <div class="gbtn-note" id="gbtn-note" hidden>{{ __('Google sign-in is being set up, please sign in with your email for now.') }}</div>
        @endif
        <p class="authalt">{{ __('No account yet?') }} <a href="/register">{{ __('Create a free account') }}</a></p>
      </div>
    </div>

    <div class="auth-benes">
      <h2>{{ __('Not every driveshaft is universal.') }}</h2>
      <p class="bsub">{{ __('Create a free account and get the full Universal Driveshafts experience.') }}</p>
      <ul>
        <li><span class="bi"><x-icon name="package"/></span><div><b>{{ __('Track every order') }}</b><span>{{ __('Live UPS/FedEx tracking, invoices and history in one place.') }}</span></div></li>
        <li><span class="bi"><x-icon name="wrench"/></span><div><b>{{ __('Save your driveshaft builds') }}</b><span>{{ __('Design it once, reopen it in the builder anytime to order.') }}</span></div></li>
        <li><span class="bi"><x-icon name="truck"/></span><div><b>{{ __('Dropship addresses') }}</b><span>{{ __('Save the shops and yards you ship to and reuse them at checkout.') }}</span></div></li>
        <li><span class="bi"><x-icon name="heart"/></span><div><b>{{ __('Wishlist & faster checkout') }}</b><span>{{ __('Save parts for later and check out in seconds.') }}</span></div></li>
        <li><span class="bi"><x-icon name="certificate"/></span><div><b>{{ __('Wholesale pricing') }}</b><span>{{ __('Shops & resellers can apply for B2B account pricing.') }}</span></div></li>
      </ul>
    </div>
  </div>
</div></section>
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-toggle-pw]').forEach(function(b){b.addEventListener('click',function(){var i=document.getElementById(b.dataset.togglePw);var show=i.type==='password';i.type=show?'text':'password';b.textContent=show?@json(__('Hide')):@json(__('Show'));});});</script>
@endpush
