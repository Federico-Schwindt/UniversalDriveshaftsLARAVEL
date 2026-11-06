@extends('account.layout')
@section('active','settings')
@section('atitle','Profile & security')
@section('acct')
<div class="acct-h"><div><h2 class="t">{{ __('Profile & security') }}</h2><p>{{ __('Your contact details, sign-in e-mail, password and language.') }}</p></div></div>

<div class="apanel">
  {{-- profile picture (DiceBear) --}}
  @php
    $avStyle = \App\Support\Avatar::validStyle($user->avatar_style) ? $user->avatar_style : \App\Support\Avatar::DEFAULT_STYLE;
    $avSeed = \App\Support\Avatar::validSeed($user->avatar_seed) ? $user->avatar_seed : \App\Support\Avatar::newSeed();
  @endphp
  <div class="aset" id="avatar">
    <div class="aset-l"><h3>{{ __('Profile picture') }}</h3><p>{{ __('Shown on your account and in the UDS Assistant chat. Pick a style, shuffle until you like it, then save.') }}</p></div>
    <details class="avp-fold" data-avp-fold>
    <summary><img src="{{ $avatarUrl }}" alt="{{ __('Your current profile picture') }}" width="64" height="64"><span class="btn btn-ghost btn-sm avp-chg">{{ __('Change picture') }}</span></summary>
    <form method="POST" action="/account/avatar" class="avp" data-avp>@csrf
      <input type="hidden" name="avatar_seed" value="{{ $avSeed }}" data-avp-seed>
      <fieldset class="avp-grid"><legend class="sr-only">{{ __('Picture style') }}</legend>
        @foreach(\App\Support\Avatar::STYLES as $st => $label)
          <label class="avp-opt"><input type="radio" name="avatar_style" value="{{ $st }}" @checked($st === $avStyle)><img src="{{ \App\Support\Avatar::path($st, $avSeed) }}" alt="" loading="lazy" width="56" height="56" data-style="{{ $st }}"><span>{{ __($label) }}</span></label>
        @endforeach
      </fieldset>
      <div class="avp-act">
        <button type="button" class="btn btn-ghost btn-sm" data-avp-shuffle><x-icon name="refresh"/> {{ __('Shuffle') }}</button>
        <button type="submit" class="btn btn-primary btn-sm">{{ __('Save picture') }}</button>
      </div>
      <p class="aset-fine" style="margin:0;font-size:.78rem;color:var(--ink-faint)">{!! __('Avatars by <a href=":url" target="_blank" rel="noopener">DiceBear</a>.', ['url' => 'https://www.dicebear.com/']) !!}</p>
    </form>
    </details>
    <script>
    (function () {
      var f = document.querySelector('[data-avp]'); if (!f) return;
      if (location.hash === '#avatar') document.querySelector('[data-avp-fold]').open = true;
      var seed = f.querySelector('[data-avp-seed]');
      f.querySelector('[data-avp-shuffle]').addEventListener('click', function () {
        var a = new Uint8Array(6); (window.crypto || window.msCrypto).getRandomValues(a);
        seed.value = Array.prototype.map.call(a, function (b) { return ('0' + b.toString(36)).slice(-2); }).join('').slice(0, 10);
        f.querySelectorAll('img[data-style]').forEach(function (img) { img.src = '/avatar/' + img.getAttribute('data-style') + '/' + seed.value; });
      });
    })();
    </script>
  </div>

  {{-- saved cards --}}
  <div class="aset" id="cards">
    <div class="aset-l"><h3>{{ __('Saved cards') }}</h3><p>{{ __('Cards you paid with at checkout. They are stored securely by our payment processor, Stripe — we never see or keep the full number.') }}</p></div>
    <div>
      @if(!empty($cards))
        <div class="cards-list">
          @foreach($cards as $c)
            <div class="card-row">
              <x-icon name="card"/>
              <span class="card-row__brand">{{ $c['brand'] }}</span> <span class="mono">•••• {{ $c['last4'] }}</span>
              <span class="card-row__exp">{{ __('Exp. :date', ['date' => $c['exp']]) }}</span>
              <form method="POST" action="/account/cards/{{ $c['id'] }}/remove" onsubmit="return confirm(@js(__('Remove this card?')))">@csrf<button type="submit" class="btn btn-ghost btn-sm">{{ __('Remove') }}</button></form>
            </div>
          @endforeach
        </div>
      @else
        <p class="aset-cur" style="color:var(--ink-3);font-weight:400;font-size:.9rem">{{ __('No saved cards yet. The card you pay with at checkout is saved here for next time, unless you tick "Don\'t save my card".') }}</p>
      @endif
    </div>
  </div>

  {{-- personal details --}}
  <div class="aset">
    <div class="aset-l"><h3>{{ __('Personal details') }}</h3><p>{{ __('Printed on your invoices and used by the shop to reach you about an order.') }}</p></div>
    <form method="POST" action="/account/update" class="aform">@csrf
      <div><label for="p-name">{{ __('Full name') }}</label><input id="p-name" type="text" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required></div>
      <div><label for="p-co">{{ __('Company') }} <small>{{ __('(optional)') }}</small></label><input id="p-co" type="text" name="company" value="{{ old('company', $user->company) }}" autocomplete="organization"></div>
      <div><label for="p-ph">{{ __('Phone / Mobile') }}</label><input id="p-ph" type="tel" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel"></div>
      <div><label for="p-lang">{{ __('Language for e-mails and texts') }}</label><select id="p-lang" name="locale">@foreach(\App\Http\Middleware\SetLocale::NAMES as $code => $name)<option value="{{ $code }}" @selected(($user->locale ?: 'en') === $code) lang="{{ \App\Http\Middleware\SetLocale::tag($code) }}">{{ $name }}</option>@endforeach</select></div>
      <div class="aform-foot"><button type="submit" class="btn btn-primary">{{ __('Save details') }}</button></div>
    </form>
  </div>

  {{-- e-mail --}}
  <div class="aset" id="email">
    <div class="aset-l"><h3>{{ __('E-mail address') }}</h3><p>{{ __('You sign in with it, and order confirmations and tracking go there. We send a code to the new address to confirm it.') }}</p></div>
    <div>
      <div class="aset-cur">{{ $user->email }} @if($user->email_verified_at)<span class="chip chip--ok"><x-icon name="check"/> {{ __('Verified') }}</span>@else<span class="chip chip--warn">{{ __('Not verified') }}</span>@endif</div>
      @php $eb = $errors->getBag('email'); @endphp
      <form method="POST" action="/account/email" class="aform" novalidate>@csrf
        <div><label for="e-new">{{ __('New e-mail address') }}</label><input id="e-new" type="email" name="new_email" value="{{ old('new_email') }}" autocomplete="email" required @if($eb->has('new_email')) aria-invalid="true" aria-describedby="e-new-err" @endif>@if($eb->has('new_email'))<span class="ferr" id="e-new-err">{{ $eb->first('new_email') }}</span>@endif</div>
        @if($user->password)<div><label for="e-pw">{{ __('Current password') }}</label><input id="e-pw" type="password" name="current_password" autocomplete="current-password" required @if($eb->has('current_password')) aria-invalid="true" aria-describedby="e-pw-err" @endif>@if($eb->has('current_password'))<span class="ferr" id="e-pw-err">{{ $eb->first('current_password') }}</span>@endif</div>
        @elseif(!$reauthOk)@include('account.partials.reauth', ['id' => 'e-ra', 'bag' => $eb, 'for' => 'email'])@endif
        <div class="aform-foot"><button type="submit" class="btn btn-ghost">{{ __('Send code to new e-mail') }}</button></div>
      </form>
      @if(!$user->password && !$reauthOk)<form id="ra-send-email" method="POST" action="/account/reauth/send" hidden>@csrf<input type="hidden" name="for" value="email"></form>@endif
    </div>
  </div>

  {{-- password --}}
  <div class="aset">
    <div class="aset-l"><h3>{{ __($user->password ? 'Change Password' : 'Set a Password') }}</h3><p>{{ $user->password ? __('Use at least 8 characters. You stay signed in on this device.') : __('You signed up with Google. Set a password to also sign in with your e-mail.') }}</p></div>
    @php $pb = $errors->getBag('password'); @endphp
    <div>
    <form method="POST" action="/account/password" class="aform" novalidate>@csrf
      @if($user->password)<div class="full"><label for="pw-c">{{ __('Current password') }}</label><input id="pw-c" type="password" name="current_password" autocomplete="current-password" required @if($pb->has('current_password')) aria-invalid="true" aria-describedby="pw-c-err" @endif>@if($pb->has('current_password'))<span class="ferr" id="pw-c-err">{{ $pb->first('current_password') }}</span>@endif</div>
      @elseif(!$reauthOk)@include('account.partials.reauth', ['id' => 'pw-ra', 'bag' => $pb, 'for' => 'password'])@endif
      <div><label for="pw-n">{{ __('New password') }}</label><input id="pw-n" type="password" name="password" required minlength="8" autocomplete="new-password" @if($pb->has('password')) aria-invalid="true" aria-describedby="pw-n-err" @endif>@if($pb->has('password'))<span class="ferr" id="pw-n-err">{{ $pb->first('password') }}</span>@endif</div>
      <div><label for="pw-n2">{{ __('Confirm new password') }}</label><input id="pw-n2" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div>
      <div class="aform-foot"><button type="submit" class="btn btn-ghost">{{ __('Update Password') }}</button>@if($user->password)<span class="note">{{ __('Other devices signed in to your account are signed out.') }}</span>@endif</div>
    </form>
    @if(!$user->password && !$reauthOk)<form id="ra-send-password" method="POST" action="/account/reauth/send" hidden>@csrf<input type="hidden" name="for" value="password"></form>@endif
    </div>
  </div>

  {{-- wholesale --}}
  <div class="aset">
    <div class="aset-l"><h3>{{ __('Wholesale account') }}</h3><p>{{ __('Shops and resellers can apply for account pricing.') }}</p></div>
    <div>
      @if(($user->wholesale_status ?? 'none') === 'approved')
        {{-- the discount comes from the B2B tier (Bronze / Silver / Gold), not the old per-account wholesale % --}}
        <div class="aset-cur"><span class="chip chip--blue"><x-icon name="certificate"/> {{ \App\Services\B2bAccounts::label(\App\Services\B2bAccounts::tierOf($user)) }}</span> <a href="/account/business" style="color:var(--blue);font-weight:700;font-size:.9rem">{{ __('Tier, spend & invoices') }}</a></div>
      @elseif(($user->wholesale_status ?? 'none') !== 'none')
        <div class="aset-cur"><span class="chip chip--warn">{{ __('Application '.$user->wholesale_status) }}</span></div>
      @else
        <a href="/wholesale" class="btn btn-ghost">{{ __('Apply for a Wholesale Account') }}</a>
      @endif
    </div>
  </div>
</div>
@endsection
