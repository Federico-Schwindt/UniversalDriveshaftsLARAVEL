{{-- Abandoned-cart capture: saves the checkout e-mail / phone with the current cart so a reminder can bring the customer back. --}}
<script>
(function(){
  var f=document.querySelector('input[name="email"]'), m=document.querySelector('meta[name="csrf-token"]'); if(!f||!m)return;
  var AUTH=@json(auth()->check()), last='', timer=null;
  function phone(){var h=document.querySelector('input[type="hidden"][name="phone"]')||document.querySelector('input[name="phone"]');return h?String(h.value||'').trim():'';}
  function send(){
    var email=f.value.trim(), ph=phone();
    if(email&&!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email))email='';
    if(!email&&!AUTH)return;
    var sig=email+'|'+ph; if(sig===last)return; last=sig;   // one request per distinct e-mail/phone (shared form throttle)
    fetch('/cart/recovery/capture',{method:'POST',keepalive:true,headers:{'X-CSRF-TOKEN':m.content,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({email:email,phone:ph})}).catch(function(){});
  }
  function later(){clearTimeout(timer);timer=setTimeout(send,250);}
  f.addEventListener('blur',later); f.addEventListener('change',later);
  document.addEventListener('blur',function(e){if(e.target&&e.target.matches&&e.target.matches('input[type="tel"]'))later();},true);
  if(f.value.trim()||AUTH)setTimeout(send,900);   // logged-in customers (and browser-autofilled e-mails) are captured on load
})();
</script>
