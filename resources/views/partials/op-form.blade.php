<div class="fitform-wrap" id="fitment">
  <h2>{{ __('Let us help you find the right part') }}</h2>
  <p class="sub">{{ __('Fill out the form below and we will contact you with the right fit part number.') }}</p>
  @if(session('fitment_ok'))
    <div class="fit-thanks">
      <h3>{{ __('Thank you for reaching out.') }}</h3>
      <p>{{ __('We\'ve received your request and our team will get back to you shortly with the perfect solution for your vehicle. If you need immediate assistance, feel free to give us a call.') }}</p>
    </div>
  @else
  <form class="fitform" method="POST" action="/fitment-inquiry#fitment">
    @csrf{!! \App\Support\SpamGuard::fields() !!}
    <input type="hidden" name="source" value="one-piece-driveshaft">
    <div><label>{{ __('First Name') }}</label><input type="text" name="first_name" placeholder="{{ __('First Name') }}"></div>
    <div><label>{{ __('Last Name') }}</label><input type="text" name="last_name" placeholder="{{ __('Last Name') }}"></div>
    <div><label>{{ __('Phone/Mobile') }}</label><input type="tel" name="phone" placeholder="{{ __('Mobile Number') }}"></div>
    <div><label>{{ __('Email') }}</label><input type="email" name="email" placeholder="{{ __('Email Address') }}" required></div>
    <div class="full"><label>{{ __('Transmission Output Type') }}</label>
      <select name="output_type" class="fsel">
        <option value="">{{ __('- Select -') }}</option>
        <option value="Seal">{{ __('Seal') }}</option>
        <option value="Rubber Boot Extension">{{ __('Rubber Boot Extension') }}</option>
        <option value="End Yoke">{{ __('End Yoke') }}</option>
        <option value="Flange">{{ __('Flange') }}</option>
      </select>
    </div>
    <div><label>{{ __('A) Measurement') }}</label><input type="text" name="measurement_a" placeholder="{{ __('A) Measurement') }}"></div>
    <div><label>{{ __('B) Measurement') }}</label><input type="text" name="measurement_b" placeholder="{{ __('B) Measurement') }}"></div>
    <button type="submit">{{ __('Submit Form') }}</button>
  </form>
  @endif
</div>
