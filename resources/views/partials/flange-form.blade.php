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
    @csrf
    <input type="hidden" name="source" value="flange-yoke">
    <div><label>{{ __('First Name') }}</label><input type="text" name="first_name" placeholder="{{ __('First Name') }}"></div>
    <div><label>{{ __('Last Name') }}</label><input type="text" name="last_name" placeholder="{{ __('Last Name') }}"></div>
    <div><label>{{ __('Phone/Mobile') }}</label><input type="tel" name="phone" placeholder="{{ __('Mobile Number') }}"></div>
    <div><label>{{ __('Email') }}</label><input type="email" name="email" placeholder="{{ __('Email Address') }}" required></div>
    <div class="full"><label>{{ __('Type of Flange Pilot') }}</label>
      <select name="flange_pilot_type" class="fsel">
        <option value="">{{ __('- Select -') }}</option>
        <option value="Male Flange">{{ __('Male Flange') }}</option>
        <option value="Female Flange">{{ __('Female Flange') }}</option>
      </select>
    </div>
    <div class="full"><label>{{ __('A) Bolt Circle Diameter:') }}</label><input type="text" name="bolt_circle_diameter" placeholder="{{ __('Bolt Circle Diameter') }}"></div>
    <div><label>{{ __('B) Holes:') }}</label>
      <select name="holes" class="fsel">
        <option value="">{{ __('- Select -') }}</option>
        <option value="Threaded">{{ __('Threaded') }}</option>
        <option value="Drilled">{{ __('Drilled') }}</option>
      </select>
    </div>
    <div><label>{{ __('B) Hole Pattern:') }}</label>
      <select name="hole_pattern" class="fsel">
        <option value="">{{ __('- Select -') }}</option>
        <option value="Rectangular">{{ __('Rectangular') }}</option>
        <option value="Square">{{ __('Square') }}</option>
      </select>
    </div>
    <button type="submit">{{ __('Submit Form') }}</button>
  </form>
  @endif
</div>
