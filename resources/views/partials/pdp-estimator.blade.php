{{-- Shipping estimator: live UPS options for this product to a US ZIP (POST /shop/estimate, CommerceController::estimate).
     Receives $p (id, sku, weight, price). ZIP is remembered in localStorage "uds.zip"; qty follows #pdp-qty. --}}
<section class="est" id="pdp-est" aria-labelledby="pdp-est-h">
  <div class="est__head">
    <h2 id="pdp-est-h"><x-icon name="truck"/> {{ __('Estimate shipping') }}</h2>
    <p>{{ __('Live UPS rates from our shop in Florida. Parts orders over $') }}{{ number_format(\App\Services\CartService::freeMin()) }} {{ __('ship free — tubing and complete driveshafts always ship at the UPS rate.') }}</p>
  </div>
  <form class="est__form" id="pdp-est-form" data-est-id="{{ $p['id'] }}" novalidate>
    <div class="field est__zip"><label for="est-zip">{{ __('ZIP code') }}</label><input type="text" id="est-zip" name="zip" inputmode="numeric" autocomplete="postal-code" pattern="[0-9]{5}" maxlength="5" placeholder="e.g. 77092" required></div>
    <div class="field est__qty"><label for="est-qty">{{ __('Qty') }}</label><input type="number" id="est-qty" name="qty" min="1" max="999" value="1" inputmode="numeric"></div>
    <button type="submit" class="btn btn-ghost est__go"><span>{{ __('Get rates') }}</span></button>
  </form>
  <div class="est__out" id="pdp-est-out" hidden aria-live="polite"></div>
  <p class="est__pickup"><x-icon name="pin"/> {{ __('Local pickup in Florida — free') }}</p>
</section>
@if(app()->getLocale() !== 'en')
<script>window.UDS_T=Object.assign(window.UDS_T||{},{!! json_encode(collect(['Enter a 5-digit US ZIP code.','Getting UPS rates for','Free shipping','UPS Ground · parts orders over','ship free','Free','faster option','packages','Tubing and complete driveshafts ship at the UPS rate and do not count toward free shipping.','Add','more in parts and the whole order ships free.','Estimate for qty','lb. Final rate is confirmed at checkout.','Could not get a rate, call (813) 524-6491.'])->mapWithKeys(fn ($k) => [$k => __($k)]), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) !!});</script>
@endif
<script>var TT=function(s){return (window.UDS_T||{})[s]||s;};
(function(){
  var form=document.getElementById('pdp-est-form'),out=document.getElementById('pdp-est-out');if(!form||!out)return;
  var CSRF=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
  var zip=form.zip,qty=form.qty,go=form.querySelector('.est__go'),pageQty=document.getElementById('pdp-qty'),KEY='uds.zip';
  function money(n){return '$'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  try{var saved=localStorage.getItem(KEY);if(saved&&/^\d{5}$/.test(saved))zip.value=saved;}catch(e){}
  function syncQty(){if(pageQty){var n=parseInt(pageQty.value,10);if(n>0)qty.value=n;}}
  syncQty();
  if(pageQty){pageQty.addEventListener('input',syncQty);pageQty.addEventListener('change',syncQty);document.querySelectorAll('[data-qty]').forEach(function(b){b.addEventListener('click',function(){setTimeout(syncQty,0);});});}
  zip.addEventListener('input',function(){zip.value=zip.value.replace(/\D/g,'').slice(0,5);});
  function show(html,cls){out.className='est__out'+(cls?' '+cls:'');out.innerHTML=html;out.hidden=false;}
  form.addEventListener('submit',function(e){
    e.preventDefault();
    var z=zip.value.trim(),q=Math.max(1,parseInt(qty.value,10)||1);
    if(!/^\d{5}$/.test(z)){show('<p class="est__err">'+TT("Enter a 5-digit US ZIP code.")+'</p>');zip.focus();return;}
    try{localStorage.setItem(KEY,z);}catch(e){}
    go.disabled=true;show('<p class="est__wait">'+TT("Getting UPS rates for")+' '+esc(z)+'…</p>');
    fetch('/shop/estimate',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},
      body:new URLSearchParams({id:form.dataset.estId,qty:q,zip:z})})
      .then(function(r){return r.json();})
      .then(function(d){
        go.disabled=false;
        if(!d||!d.success||!d.rates||!d.rates.length){show('<p class="est__err">'+esc((d&&d.error)||'Could not get a rate, call (813) 524-6491.')+'</p>');return;}
        var h='',f=d.free||{};
        h+='<ul class="est__list">';
        if(f.eligible){h+='<li class="est__li-free"><span>'+TT("Free shipping")+' <small>'+TT("UPS Ground · parts orders over")+' '+money(f.min).replace(/\.00$/,'')+' '+TT("ship free")+'</small></span><b>'+TT("Free")+'</b></li>';}
        d.rates.forEach(function(r,i){if(f.eligible&&i===0)return;h+='<li><span>'+esc(r.label)+(f.eligible?'<small>'+TT("faster option")+'</small>':'')+(r.packages>1?'<small>'+r.packages+' '+TT("packages")+'</small>':'')+'</span><b>'+money(r.cost)+'</b></li>';});
        h+='</ul>';
        if(!f.eligible){
          if(f.excluded)h+='<p class="est__note">'+TT("Tubing and complete driveshafts ship at the UPS rate and do not count toward free shipping.")+'</p>';
          else if(f.remaining>0)h+='<p class="est__note">'+TT("Add")+' '+money(f.remaining)+' '+TT("more in parts and the whole order ships free.")+'</p>';
        }
        h+='<p class="est__note est__meta">'+TT("Estimate for qty")+' '+q+' to ZIP '+esc(z)+' · '+(d.packages>1?d.packages+' packages':'1 package')+' · ~'+Number(d.weight_lb).toLocaleString('en-US',{maximumFractionDigits:1})+' '+TT("lb. Final rate is confirmed at checkout.")+'</p>';
        show(h);
      })
      .catch(function(){go.disabled=false;show('<p class="est__err">'+TT("Could not get a rate, call (813) 524-6491.")+'</p>');});
  });
})();
</script>
