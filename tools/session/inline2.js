
(function(){
  const CSRF=document.querySelector('meta[name="csrf-token"]').content;
  const SUBTOTAL=97.1, FREEMIN=750;
  const form=document.getElementById('co-form');
  let shipCost=0, shipId='', coupon=null;
  const PICKUP='local_pickup', PICKUP_LABEL="Local pickup at Odessa, FL (Mon to Fri, 3 to 5 pm)", PICKUP_HOURS="Monday to Friday, 3:00 to 5:00 pm", PICKUP_PHONE="(813) 524-6491";
  const SHIP_DATE=document.getElementById('meta-date-v').textContent;

  function money(n){return '$'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function freeBar(){
    var bar=document.getElementById('freeship-bar');
    var rem=FREEMIN-SUBTOTAL;
    if(rem<=0){bar.className='freeship done';bar.textContent='Free US ground shipping applied.';}
    else{bar.className='freeship';bar.textContent='Only '+money(rem)+' left to qualify for FREE Shipping!';}
  }
  function totals(){
    var disc=coupon?Number(coupon.discount||0):0;
    var freeShip=coupon&&coupon.free_shipping;
    var eff=freeShip?0:shipCost;
    document.getElementById('sum-ship').textContent=shipId?(eff>0?money(eff):'Free'):'TBD';
    var t=Math.max(0,SUBTOTAL-disc)+eff;
    document.getElementById('sum-total').textContent=money(t);
    document.getElementById('place-total').textContent=money(t);
    var mb=document.getElementById('mbar-total'); if(mb)mb.textContent=money(t);
  }
  /* coupon */
  function applyCoupon(){
    var code=document.getElementById('coupon-code').value.trim(); if(!code)return;
    fetch('/cart/coupon',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({code:code})})
      .then(r=>r.json()).then(res=>{
        var err=document.getElementById('coupon-err');
        if(!res.success){err.textContent=res.message||'Invalid coupon';return;}
        err.textContent='';coupon=res.data;
        document.getElementById('coupon-in').style.display='none';
        document.getElementById('coupon-on').style.display='flex';
        document.getElementById('coupon-label').textContent=res.data.code;
        document.getElementById('disc-code').textContent=res.data.code;
        document.getElementById('disc-amt').textContent=Number(res.data.discount).toFixed(2);
        document.getElementById('disc-row').hidden=false;totals();
      });
  }
  function removeCoupon(){
    fetch('/cart/coupon/remove',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'}}).then(()=>{
      coupon=null;document.getElementById('coupon-in').style.display='flex';
      document.getElementById('coupon-on').style.display='none';
      document.getElementById('disc-row').hidden=true;totals();
    });
  }
  document.getElementById('coupon-apply').onclick=applyCoupon;
  document.getElementById('coupon-remove').onclick=removeCoupon;

  /* dropship / ship-to-different */
  function showDrop(on){
    document.getElementById('ship-diff').checked=on;
    document.getElementById('ship-sec').style.display=on?'grid':'none';
    document.getElementById('ship-same').style.display=on?'none':'flex';
    document.getElementById('dropship-btn').textContent=on?'Using dropship address':'Use dropship address';
    loadShipping();
  }
  document.getElementById('dropship-btn').onclick=function(){showDrop(!document.getElementById('ship-diff').checked);};
  document.getElementById('dropship-cancel').onclick=function(){showDrop(false);};
  document.querySelectorAll('.ss-item').forEach(function(el){
    el.onclick=function(){
      var d={}; try{ d=JSON.parse(el.getAttribute('data-ds')); }catch(e){}
      function set(n,val){var f=form.querySelector('[name='+n+']'); if(f)f.value=(val==null?'':val);}
      set('shipping_company',d.company); set('shipping_address_1',d.street); set('shipping_address_2',d.unit);
      set('shipping_city',d.city); set('shipping_state',d.state); set('shipping_postcode',d.postcode);
      var cc=document.getElementById('shipping_country'); if(cc)cc.value=d.country||'US';
      showDrop(true);
      document.querySelectorAll('.ss-item').forEach(function(x){x.classList.remove('on');}); el.classList.add('on');
    };
  });

  /* shipping rates -> select
     Debounced + sequenced: picking a Google Places suggestion (or the /geo
     IP prefill on load) updates several fields at once, each of which used to
     fire its own independent request. Those could resolve OUT OF ORDER — a
     slow, stale "US" quote arriving after a correct international one would
     silently overwrite it with domestic rates. A request token plus aborting
     the in-flight call means only the response to the LATEST request is ever
     applied, and the debounce collapses a burst of field changes into one. */
  let shipReqToken = 0, shipAbort = null, shipDebounce = null;
  function loadShipping(){
    clearTimeout(shipDebounce);
    shipDebounce = setTimeout(doLoadShipping, 180);
  }
  function doLoadShipping(){
    const diff=document.getElementById('ship-diff').checked;
    const country=(diff?document.getElementById('shipping_country'):document.getElementById('billing_country')).value;
    const state=(diff?document.getElementById('shipping_state'):document.getElementById('billing_state')).value;
    const zip=(document.querySelector(diff?'[name=shipping_postcode]':'[name=billing_postcode]')||{}).value||'';
    const sel=document.getElementById('ship-select'), note=document.getElementById('ship-note');
    const myToken = ++shipReqToken;
    if (shipAbort) shipAbort.abort();
    if(!zip){sel.innerHTML='<option value="">Enter your ZIP to load rates…</option><option value="'+PICKUP+'" data-cost="0">'+PICKUP_LABEL+' — Free</option>';shipId='';shipCost=0;totals();pickupUI();return;}
    // clear stale options immediately rather than leaving a previous (possibly
    // wrong-country) rate sitting there, selectable, while the new quote loads
    sel.innerHTML='<option value="">Loading rates…</option>';shipId='';shipCost=0;totals();
    note.textContent='Loading live UPS & FedEx rates…';
    shipAbort = new AbortController();
    fetch('/checkout/shipping-options',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({country,state,zip}),signal:shipAbort.signal})
      .then(r=>r.json()).then(({data})=>{
        if(myToken!==shipReqToken)return; // a newer request has since started — this reply is stale, ignore it
        if(!data||!data.length){sel.innerHTML='<option value="">No rates for this address</option>';note.textContent='';shipId='';shipCost=0;totals();return;}
        sel.innerHTML='<option value="">Choose a Method…</option>'+data.map(o=>`<option value="${o.id}" data-cost="${o.cost}">${o.label} — ${o.cost>0?money(o.cost):'Free'}</option>`).join('');
        note.textContent='Rates shown ship from Odessa, FL.';
        // preselect the cheapest SHIPPING option. Pickup is $0 and must never
        // win this: a customer who meant "ship it" would end up with nothing shipped.
        var shippable=data.filter(o=>o.id!==PICKUP);
        if(shippable.length){var cheapest=shippable.reduce((a,b)=>b.cost<a.cost?b:a,shippable[0]);sel.value=cheapest.id;shipId=cheapest.id;shipCost=cheapest.cost;}
        else{sel.value='';shipId='';shipCost=0;}
        totals();pickupUI();
      }).catch((e)=>{if(e.name==='AbortError'||myToken!==shipReqToken)return;note.textContent='Could not load rates — call (813) 524-6491.';});
  }
  document.getElementById('ship-select').onchange=function(){
    var o=this.options[this.selectedIndex];shipId=this.value;shipCost=o?Number(o.getAttribute('data-cost')||0):0;totals();pickupUI();
  };

  /* local pickup: notice + acknowledgement, out-of-state warning, pay-at-pickup option */
  function pickupUI(){
    var on=shipId===PICKUP, box=document.getElementById('pickup-box'), far=document.getElementById('pickup-far');
    box.hidden=!on;
    if(on){
      var diff=document.getElementById('ship-diff').checked;
      var st=((diff?document.getElementById('shipping_state'):document.getElementById('billing_state')).value||'').trim().toUpperCase();
      var cc=(diff?document.getElementById('shipping_country'):document.getElementById('billing_country')).value||'US';
      var isFL=cc==='US'&&(st==='FL'||st==='FLORIDA');
      far.hidden=isFL||!st;
      document.getElementById('pickup-far-state').textContent=st+(cc!=='US'?' ('+cc+')':'');
    }else{document.getElementById('pickup-ack').checked=false;}
    document.getElementById('meta-from-l').textContent=on?'Pick up at':'Shipping From';
    document.getElementById('meta-date-l').textContent=on?'Pickup hours':'Est. Ship Date';
    document.getElementById('meta-date-v').textContent=on?PICKUP_HOURS:SHIP_DATE;
    document.getElementById('meta-date-s').textContent=on?'Other times: call '+PICKUP_PHONE+' first':'Orders before 2pm ET ship same day';
    var pm=document.getElementById('pay-method'), opt=document.getElementById('pay-pickup-opt');
    if(on&&!opt){opt=document.createElement('option');opt.id='pay-pickup-opt';opt.value='pay_at_pickup';opt.textContent='Pay at pickup (at the Odessa counter)';pm.appendChild(opt);}
    if(!on&&opt){if(pm.value==='pay_at_pickup')pm.value='card';opt.remove();}
    payUI();
  }
  function payUI(){document.getElementById('pay-pickup-note').hidden=document.getElementById('pay-method').value!=='pay_at_pickup';}
  document.getElementById('pay-method').addEventListener('change',payUI);
  ['billing_country','billing_state','shipping_country','shipping_state'].forEach(id=>{
    const el=document.getElementById(id); if(el)el.addEventListener('change',loadShipping);
  });
  document.querySelectorAll('[name=billing_postcode],[name=shipping_postcode]').forEach(el=>el.addEventListener('blur',loadShipping));
  document.addEventListener('uds:geo',loadShipping);
  document.addEventListener('uds:address-selected',loadShipping);

  /* estimated shipping modal (once per session) */
  var esm=document.getElementById('esm-back');
  // opened on request only. It used to auto-open over the form on first visit,
  // which is an interstitial between the customer and the pay button.
  function esmOpen(e){ if(e)e.preventDefault(); esm.classList.add('on'); document.getElementById('esm-ok').focus(); }
  function esmClose(){ esm.classList.remove('on'); }
  document.querySelectorAll('[data-esm-open]').forEach(function(t){ t.addEventListener('click', esmOpen); });
  document.getElementById('esm-ok').onclick=esmClose;
  esm.addEventListener('click',function(e){ if(e.target===esm) esmClose(); });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&esm.classList.contains('on')) esmClose(); });

  /* place order */
  document.getElementById('place-btn').onclick=function(){
    if(!shipId){alert('Please choose a shipping method.');return;}
    if(shipId===PICKUP){var pa=document.getElementById('pickup-ack');if(!pa.checked){alert('Please confirm you are picking this order up in person at Odessa, FL.');pa.focus();pa.closest('.co-agree').style.outline='2px solid var(--err)';return;}}
    var ag=document.getElementById('agree-terms'); if(ag&&!ag.checked){alert('Please read and accept the Terms of Use to place your order.');ag.focus();ag.closest('.co-agree').style.outline='2px solid #e0503a';ag.closest('.co-agree').style.borderRadius='12px';return;}
    const btn=this;btn.disabled=true;btn.textContent='Placing order…';
    fetch('/checkout/place',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest'},body:new URLSearchParams(new FormData(form))})
      .then(r=>r.json()).then(res=>{
        if(!res.success){
          const eb=document.getElementById('co-errors');
          eb.style.display='block';eb.querySelector('ul').innerHTML=(res.errors||['Error']).map(e=>`<li>${e}</li>`).join('');
          window.scrollTo({top:eb.getBoundingClientRect().top+scrollY-120,behavior:'smooth'});
          btn.disabled=false;btn.innerHTML='Place Order — <span id="place-total">'+money(SUBTOTAL+shipCost)+'</span>';return;
        }
        if(res.data.client_secret && window.udsStripeConfirm){window.udsStripeConfirm(res.data.client_secret,res.data.redirect,btn);}
        else{location.href=res.data.redirect;}
      }).catch(()=>{btn.disabled=false;btn.textContent='Place Order';alert('Network error. Please try again.');});
  };

  freeBar();totals();

  /* mobile sticky bar: hide while the real summary/Place Order is on screen */
  var mbar=document.querySelector('.ck-mbar'), sumEl=document.getElementById('summary');
  if(mbar&&sumEl&&'IntersectionObserver' in window){
    new IntersectionObserver(function(es){es.forEach(function(e){mbar.classList.toggle('hide',e.isIntersecting);});},{threshold:0.15}).observe(sumEl);
  }
})();
