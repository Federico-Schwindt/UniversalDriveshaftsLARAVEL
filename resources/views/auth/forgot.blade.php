@extends('layouts.app')
@section('robots','noindex,follow')
@section('title', __('Reset your password | Universal Driveshafts'))
@section('desc', __('Request a password reset link for your Universal Driveshafts account.'))
@section('content')
<section class="section"><div class="wrap">
  <div class="auth-split">
    <div class="auth-form">
      <div class="authbox" style="box-shadow:none;border:0;padding:0;max-width:none">
        <h1>{{ __('Reset your password') }}</h1>
        @if(session('reset_sent'))
          <p class="sub">{{ __('If an account exists for that e-mail, a reset link is on its way. It works for 60 minutes. Check spam if you do not see it in a couple of minutes.') }}</p>
          <a class="btn btn-ghost" href="/login"><x-icon name="arrow-left"/> {{ __('Back to sign in') }}</a>
        @else
          <p class="sub">{{ __('Enter the e-mail on your account and we will send you a link to choose a new password.') }}</p>
          @if($errors->any())<div class="autherr">{{ $errors->first() }}</div>@endif
          <form method="POST" action="/forgot-password" class="authform" novalidate>
            @csrf
            <div class="field"><label for="f-email">{{ __('Email address') }}</label><input id="f-email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></div>
            <button type="submit" class="btn btn-primary btn-block">{{ __('Send reset link') }} <x-icon name="arrow-right"/></button>
          </form>
          <p class="authalt">{{ __('Remembered it?') }} <a href="/login">{{ __('Sign in') }}</a></p>
        @endif
      </div>
    </div>
    <div class="auth-benes">
      <div><h2>{{ __('Locked out of a build?') }}</h2><p class="bsub">{{ __('Saved builds, orders and dropship addresses stay on the account. A new password gets you back to all of it.') }}</p></div>
      <ul>
        <li><span class="bi"><x-icon name="lock"/></span><div><b>{{ __('Links expire in 60 minutes') }}</b><span>{{ __('Request another any time.') }}</span></div></li>
        <li><span class="bi"><x-icon name="headset"/></span><div><b>{{ __('No e-mail access?') }}</b><span>{{ __('Call (813) 524-6491 and we will sort it out.') }}</span></div></li>
      </ul>
      <div class="auth-trust"><span>Florida</span><span>(813) 524-6491</span></div>
    </div>
  </div>
</div></section>
@endsection
