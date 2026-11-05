@php $ga = config('uds.ga4'); $aw = config('uds.google_ads'); $fb = config('uds.fb_pixel'); @endphp
{{-- staff browsing the store are not visitors: no tags for admins (Site Kit excluded logged-in staff too) --}}
@if(config('uds.analytics_enabled') && !auth()->user()?->is_admin)
  @if($ga)
  {{-- Google Analytics 4 + Google Ads (gtag) --}}
  <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    // consent mode as on the old site: EEA/UK/Swiss visitors start denied (no cookies, modelled data only)
    gtag('consent', 'default', {ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied', analytics_storage: 'denied',
      region: ['AT','BE','BG','CH','CY','CZ','DE','DK','EE','ES','FI','FR','GB','GR','HR','HU','IE','IS','IT','LI','LT','LU','LV','MT','NL','NO','PL','PT','RO','SE','SI','SK']});
    gtag('js', new Date());
    gtag('config', '{{ $ga }}');
    @if($aw) gtag('config', 'AW-{{ $aw }}'); @endif
  </script>
  @endif
  @if($fb)
  {{-- Meta (Facebook) Pixel --}}
  <script>
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
    n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '{{ $fb }}'); fbq('track', 'PageView');
  </script>
  <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $fb }}&ev=PageView&noscript=1"/></noscript>
  @endif
@endif

@if(config('uds.recaptcha_enabled'))
  <script src="https://www.google.com/recaptcha/api.js?render={{ config('uds.recaptcha_site') }}"></script>
  <script>
    (function(){
      var KEY='{{ config('uds.recaptcha_site') }}';
      function attach(){
        document.querySelectorAll('form.fitform, form.apt-form').forEach(function(f){
          if(f.dataset.rc)return; f.dataset.rc='1';
          f.addEventListener('submit', function(){ /* token refreshed on the click handlers below */ });
        });
      }
      window.udsRecaptcha=function(cb){
        if(!window.grecaptcha){cb('');return;}
        grecaptcha.ready(function(){ grecaptcha.execute(KEY,{action:'submit'}).then(function(t){cb(t);}); });
      };
      document.addEventListener('DOMContentLoaded', attach);
    })();
  </script>
@endif
