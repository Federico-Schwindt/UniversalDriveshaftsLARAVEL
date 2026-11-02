@guest
<div class="wrap" style="margin-top:14px">
  <div class="login-nudge">
    <span class="ln-ico"><x-icon name="info"/></span>
    <div class="ln-txt"><b>{{ $nudge ?? 'Log in to save your work' }}</b><span>{!! __('Track it in your dashboard, come back to it anytime, and check out faster.') !!}</span></div>
    <div class="ln-act"><a href="/login" class="btn btn-primary" style="padding:9px 16px">{!! __('Log In') !!}</a><a href="/register" class="ln-alt">{!! __('Create free account') !!}</a></div>
  </div>
</div>
<style>
.login-nudge{display:flex;align-items:center;gap:15px;background:var(--blue-soft,#eaf0fc);border:1px solid #c9d8f4;border-radius:14px;padding:14px 20px;flex-wrap:wrap}
.login-nudge .ln-ico{font-size:1.5rem;flex:none}
.login-nudge .ln-txt{flex:1;min-width:200px}
.login-nudge .ln-txt b{display:block;color:var(--blue-ink,#0c2f6e);font-size:.98rem}
.login-nudge .ln-txt span{display:block;color:var(--ink-soft,#4a5568);font-size:.84rem;margin-top:1px}
.login-nudge .ln-act{display:flex;align-items:center;gap:14px}
.login-nudge .ln-alt{color:var(--blue,#1246ab);font-weight:700;font-size:.86rem;text-decoration:none}
.login-nudge .ln-alt:hover{text-decoration:underline}
</style>
@endguest
