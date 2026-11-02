{{-- Google-only accounts: a code to the current mailbox stands in for the password they don't have (AccountController::reauthFails) --}}
<div class="full">
  <label for="{{ $id }}">{{ __('Code sent to :email', ['email' => \App\Support\EmailOtp::mask($user->email)]) }}</label>
  <div style="display:flex;gap:8px">
    <input id="{{ $id }}" type="text" name="reauth_code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" pattern="[0-9 ]*" placeholder="123456" style="flex:1" aria-describedby="{{ $id }}-hint{{ $bag->has('reauth_code') ? ' ' . $id . '-err' : '' }}" @if($bag->has('reauth_code')) aria-invalid="true" @endif>
    <button type="submit" form="ra-send-{{ $for }}" class="btn btn-ghost" style="flex:none">{{ session('reauth_sent') === $for ? __('Send again') : __('Send code') }}</button>
  </div>
  <span class="hint" id="{{ $id }}-hint">{{ __('You signed in with Google, so we confirm it is you with a code to your current e-mail.') }}</span>
  @if($bag->has('reauth_code'))<span class="ferr" id="{{ $id }}-err">{{ $bag->first('reauth_code') }}</span>@endif
</div>
