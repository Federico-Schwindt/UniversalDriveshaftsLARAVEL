@extends('layouts.app')
@section('robots','noindex,follow')
@section('title', __('Choose a new password | Universal Driveshafts'))
@section('content')
<section class="section"><div class="wrap">
  <div class="auth-split">
    <div class="auth-form">
      <div class="authbox" style="box-shadow:none;border:0;padding:0;max-width:none">
        <h1>{{ __('Choose a new password') }}</h1>
        <p class="sub">{{ __('At least 8 characters. You will be signed in as soon as it is saved.') }}</p>
        @if($errors->any())<div class="autherr">{{ $errors->first() }} @if($errors->has('email'))<a href="/forgot-password">{{ __('Request a new link') }}</a>@endif</div>@endif
        <form method="POST" action="/reset-password" class="authform" novalidate>
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">
          <div class="field"><label for="r-email">{{ __('Email address') }}</label><input id="r-email" type="email" name="email" value="{{ old('email', $email) }}" autocomplete="email" required></div>
          <div class="field"><label for="r-pw">{{ __('New password') }}</label><div class="pw"><input id="r-pw" type="password" name="password" autocomplete="new-password" required minlength="8"><button type="button" data-toggle-pw="r-pw" aria-label="{{ __('Show password') }}">{{ __('Show') }}</button></div></div>
          <div class="field"><label for="r-pw2">{{ __('Confirm new password') }}</label><div class="pw"><input id="r-pw2" type="password" name="password_confirmation" autocomplete="new-password" required minlength="8"><button type="button" data-toggle-pw="r-pw2" aria-label="{{ __('Show password') }}">{{ __('Show') }}</button></div></div>
          <button type="submit" class="btn btn-primary btn-block">{{ __('Save new password') }} <x-icon name="arrow-right"/></button>
        </form>
      </div>
    </div>
    <div class="auth-benes">
      <div><h2>{{ __('Almost there.') }}</h2><p class="bsub">{{ __('Pick something you do not use anywhere else. A password manager makes that easy.') }}</p></div>
      <div class="auth-trust"><span>Florida</span><span>(813) 524-6491</span></div>
    </div>
  </div>
</div></section>
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-toggle-pw]').forEach(function(b){b.addEventListener('click',function(){var i=document.getElementById(b.dataset.togglePw);var show=i.type==='password';i.type=show?'text':'password';b.textContent=show?@json(__('Hide')):@json(__('Show'));});});</script>
@endpush
