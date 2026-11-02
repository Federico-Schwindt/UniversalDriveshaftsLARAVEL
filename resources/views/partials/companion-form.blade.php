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
    <input type="hidden" name="source" value="companion-yoke">
    <div><label>{{ __('First Name') }}</label><input type="text" name="first_name" placeholder="{{ __('First Name') }}"></div>
    <div><label>{{ __('Last Name') }}</label><input type="text" name="last_name" placeholder="{{ __('Last Name') }}"></div>
    <div><label>{{ __('Phone/Mobile') }}</label><input type="tel" name="phone" placeholder="{{ __('Mobile Number') }}"></div>
    <div><label>{{ __('Email') }}</label><input type="email" name="email" placeholder="{{ __('Email Address') }}" required></div>
    <div class="full"><label>{{ __('Type of Companion Yoke') }}</label>
      <select name="yoke_type" id="yokeType" class="fsel">
        <option value="with-keyway">{{ __('With Keyway') }}</option>
        <option value="splined">{{ __('Splined Yoke') }}</option>
      </select>
    </div>

    <div class="full fgroup" data-group="with-keyway">
      <div class="fgrid2">
        <div><label>{{ __('A) Outside Diameter') }}</label><input type="text" name="outside_diameter" placeholder="{{ __('Outside Diameter') }}"></div>
        <div><label>{{ __('B) Bolt Circle Diameter') }}</label><input type="text" name="bolt_circle_diameter" placeholder="{{ __('Bolt Circle Diameter') }}"></div>
        <div><label>{{ __('C) Pilot Bore Diameter') }}</label><input type="text" name="pilot_bore_diameter" placeholder="{{ __('Pilot Bore Diameter') }}"></div>
        <div><label>{{ __('D) Bore Diameter') }}</label><input type="text" name="bore_diameter" placeholder="{{ __('Bore Diameter') }}"></div>
        <div><label>{{ __('E) Hub Diameter') }}</label><input type="text" name="hub_diameter" placeholder="{{ __('Hub Diameter') }}"></div>
        <div><label>{{ __('F) Overall Length') }}</label><input type="text" name="overall_length" placeholder="{{ __('Overall Length') }}"></div>
        <div><label>{{ __('K) Keyway') }}</label><input type="text" name="keyway" placeholder="{{ __('Keyway') }}"></div>
      </div>
    </div>

    <div class="full fgroup" data-group="splined" hidden>
      <div class="fgrid2">
        <div><label>{{ __('A) Splines Diameter') }}</label><input type="text" name="splines_diameter" placeholder="{{ __('Splines Diameter') }}"></div>
        <div><label>{{ __('B) Bolt Circle Diameter') }}</label><input type="text" name="bolt_circle_diameter_s" placeholder="{{ __('Bolt Circle Diameter') }}"></div>
        <div><label>{{ __('C) Pilot Bore Diameter') }}</label><input type="text" name="pilot_bore_diameter_s" placeholder="{{ __('Pilot Bore Diameter') }}"></div>
      </div>
    </div>

    <button type="submit">{{ __('Submit Form') }}</button>
  </form>
  <script>
    (function(){
      var sel=document.getElementById('yokeType');
      if(!sel)return;
      function upd(){document.querySelectorAll('.fgroup').forEach(function(g){g.hidden=(g.getAttribute('data-group')!==sel.value);});}
      sel.addEventListener('change',upd);upd();
    })();
  </script>
  @endif
</div>
