{{-- "As low as $X/mo with Affirm" (Stripe payment method messaging). Affirm covers $50 to $30,000; Stripe.js loads
     after the page is idle so it never slows the first paint. --}}
@php $amt = (float) ($amount ?? 0); @endphp
@if(config('services.stripe.key') && \App\Support\PayLink::stripeReady() && $amt >= 50 && $amt <= 30000)
<div class="affirm-msg {{ $class ?? '' }}" data-affirm-msg data-amount="{{ (int) round($amt * 100) }}"></div>
@once
<script>
(function(){
  function go(){
    var els=document.querySelectorAll('[data-affirm-msg]'); if(!els.length)return;
    var s=document.createElement('script'); s.src='https://js.stripe.com/v3/'; s.async=true;
    s.onload=function(){ try{ var st=Stripe(@json(config('services.stripe.key')));
      els.forEach(function(el){ st.elements().create('paymentMethodMessaging',{amount:Number(el.getAttribute('data-amount')),currency:'USD',paymentMethodTypes:['affirm'],countryCode:'US'}).mount(el); });
    }catch(e){} };
    document.head.appendChild(s);
  }
  if(document.readyState==='complete') (window.requestIdleCallback||setTimeout)(go); else addEventListener('load',function(){(window.requestIdleCallback||setTimeout)(go);});
})();
</script>
@endonce
@endif
