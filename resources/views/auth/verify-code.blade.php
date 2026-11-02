@extends('layouts.app')
@section('robots','noindex,nofollow')
@section('title', __('Enter your verification code | Universal Driveshafts'))
@section('content')
<style>
.otp-page{background:var(--paper-2);padding:48px 0 72px}
.otp-card{max-width:460px;margin:0 auto;background:var(--paper);border:1px solid var(--line);border-radius:var(--r-lg,16px);padding:32px 28px}
.otp-ic{width:48px;height:48px;border-radius:50%;background:var(--blue-050);color:var(--blue);display:grid;place-items:center;margin-bottom:16px}
.otp-ic svg{width:22px;height:22px}
.otp-card h1{font-size:1.4rem;margin:0 0 8px;text-wrap:balance}
.otp-card p.sub{margin:0 0 22px;color:var(--ink-3);font-size:.94rem;line-height:1.5}
.otp-card p.sub b{color:var(--ink)}
.otp-steps{display:flex;gap:6px;margin-bottom:18px;font-size:.74rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-3)}
.otp-steps span{display:flex;align-items:center;gap:6px}
.otp-steps span.on{color:var(--blue)}
.otp-steps span+span::before{content:"";width:18px;height:2px;background:var(--line-strong)}
.otp-in{width:100%;height:60px;text-align:center;font:600 1.9rem/1 "IBM Plex Mono","Plex Mono Fallback",monospace;letter-spacing:.5em;padding-left:.5em;border:1.5px solid var(--line-strong);border-radius:12px;background:var(--paper);color:var(--ink);font-variant-numeric:tabular-nums}
.otp-in:focus{outline:none;border-color:var(--blue);box-shadow:0 0 0 3px var(--blue-100)}
.otp-in[aria-invalid=true]{border-color:var(--err)}
.otp-err{margin:10px 0 0;color:var(--err);font-size:.88rem;font-weight:600}
.otp-ok{margin:0 0 14px;padding:10px 12px;border-radius:10px;background:var(--ok-bg);color:var(--ok);font-size:.88rem;font-weight:600}
.otp-card .btn-block{margin-top:16px;width:100%;justify-content:center}
.otp-foot{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:20px;padding-top:16px;border-top:1px solid var(--line-2);font-size:.88rem}
.otp-foot form{margin:0}
.otp-foot button,.otp-foot a{background:none;border:0;padding:0;font:600 .88rem/1.4 inherit;color:var(--blue);cursor:pointer;text-decoration:none}
.otp-foot button[disabled]{color:var(--ink-3);cursor:default}
.otp-tip{margin:14px 0 0;font-size:.82rem;color:var(--ink-3);line-height:1.5}
@media (max-width:520px){.otp-page{padding:20px 0 48px}.otp-card{padding:24px 18px;border-radius:14px}.otp-in{font-size:1.6rem;letter-spacing:.38em;padding-left:.38em}}
</style>
<section class="otp-page"><div class="wrap">
  <div class="otp-card">
    @if($purpose === 'register')<div class="otp-steps" aria-hidden="true"><span>{{ __('Details') }}</span><span class="on">{{ __('Verify e-mail') }}</span><span>{{ __('Done') }}</span></div>@endif
    <div class="otp-ic"><x-icon name="mail"/></div>
    <h1>{{ $purpose === 'email' ? __('Confirm your new e-mail') : __('Check your e-mail') }}</h1>
    <p class="sub">{!! __('We sent a 6-digit code to <b>:email</b>. Enter it below to continue. It expires in 10 minutes.', ['email' => e($masked)]) !!}</p>
    @if(session('code_sent'))<div class="otp-ok" role="status">{{ session('code_sent') }}</div>@endif
    <form method="POST" action="{{ $action }}" id="otp-form">@csrf
      <label for="otp" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">{{ __('Verification code') }}</label>
      <input id="otp" class="otp-in" name="code" type="text" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus required placeholder="••••••" @if($errors->has('code')) aria-invalid="true" aria-describedby="otp-err" @endif>
      @error('code')<p class="otp-err" id="otp-err" role="alert">{{ $message }}</p>@enderror
      <button class="btn btn-primary btn-block" type="submit">{{ $purpose === 'register' ? __('Verify and create account') : __('Verify') }} <x-icon name="arrow-right"/></button>
    </form>
    <p class="otp-tip">{{ __('Not in your inbox? Check spam or promotions — it comes from Universal Driveshafts.') }}</p>
    <div class="otp-foot">
      <form method="POST" action="{{ $resend }}">@csrf<button type="submit" id="otp-resend" @if($cooldown) disabled data-wait="{{ $cooldown }}" @endif>{{ __('Send a new code') }}</button></form>
      <a href="{{ $back[0] }}">{{ $back[1] }}</a>
    </div>
  </div>
</div></section>
@endsection
@push('scripts')
<script>
(function(){
  var inp=document.getElementById('otp'), form=document.getElementById('otp-form');
  // digits only; submit by itself once six are in (typed, pasted or filled from the SMS/e-mail suggestion)
  inp.addEventListener('input',function(){var d=inp.value.replace(/\D/g,'').slice(0,6); if(inp.value!==d) inp.value=d; if(d.length===6 && !form.dataset.sent){form.dataset.sent=1; form.submit();}});
  var b=document.getElementById('otp-resend'), w=+(b.dataset.wait||0), label=b.textContent;
  if(w>0){var t=setInterval(function(){w--; if(w<=0){clearInterval(t); b.disabled=false; b.textContent=label;} else b.textContent=label+' ('+w+'s)';},1000); b.textContent=label+' ('+w+'s)';}
})();
</script>
@endpush
