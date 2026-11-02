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
    <input type="hidden" name="source" value="end-yoke">
    <div><label>{{ __('First Name') }}</label><input type="text" name="first_name" placeholder="{{ __('First Name') }}"></div>
    <div><label>{{ __('Last Name') }}</label><input type="text" name="last_name" placeholder="{{ __('Last Name') }}"></div>
    <div><label>{{ __('Phone/Mobile') }}</label><input type="tel" name="phone" placeholder="{{ __('Mobile Number') }}"></div>
    <div><label>{{ __('Email') }}</label><input type="email" name="email" placeholder="{{ __('Email Address') }}" required></div>
    <div><label>{{ __('A) Spline Count') }}</label><input type="text" name="spline_count" placeholder="{{ __('Spline Count') }}"></div>
    <div><label>{{ __('B) Spline Outside Diameter') }}</label><input type="text" name="spline_outside_diameter" placeholder="{{ __('Farthest point to farthest point') }}"></div>
    <div><label>{{ __('C) Hub Outside Diameter') }}</label><input type="text" name="hub_outside_diameter" placeholder="{{ __('Hub Outside Diameter') }}"></div>
    <div><label>{{ __('D) Overall Height') }}</label><input type="text" name="overall_height" placeholder="{{ __('Overall Height') }}"></div>
    <div class="full"><label>{{ __('E) Through Hole') }}</label><input type="text" name="through_hole" placeholder="{{ __('Through Hole') }}"></div>
    <button type="submit">{{ __('Submit Form') }}</button>
  </form>
  @endif
</div>
