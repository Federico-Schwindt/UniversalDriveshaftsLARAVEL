p='resources/views/checkout.blade.php'
s=open(p).read()
def rep(old,new):
    global s
    assert s.count(old)==1,(old[:70],s.count(old))
    s=s.replace(old,new)

rep("""                  stripe.confirmCardPayment(cs,{payment_method:savedPick()}).then(function(res){
                    if(res.error){ errEl.textContent=res.error.message; btn.disabled=false;btn.textContent='Place Order'; return; }""","""                  stripe.confirmCardPayment(cs,{payment_method:savedPick()}).then(function(res){
                    if(res.error){ errEl.textContent=res.error.message; if(window.udsPlaceFailed)window.udsPlaceFailed(res.error); else btn.disabled=false; return; }""")
rep("""                  if(res.error){
                    errEl.textContent=res.error.message;
                    document.getElementById('stripe-card').scrollIntoView({block:'center',behavior:'smooth'});
                    btn.disabled=false;btn.textContent='Place Order';return;
                  }""","""                  if(res.error){
                    errEl.textContent=res.error.message;
                    document.getElementById('stripe-card').scrollIntoView({block:'center',behavior:'smooth'});
                    if(window.udsPlaceFailed)window.udsPlaceFailed(res.error); else btn.disabled=false;
                    return;
                  }""")

rep("""  const SUBTOTAL={{ $subtotal }}, FREEMIN={{ $freeMin }}, FREEBASE={{ $freeBase ?? $subtotal }};
  const form=document.getElementById('co-form');
  let shipCost=0, shipId='', coupon={!! $coupon ? json_encode($coupon) : 'null' !!};""","""  let SUBTOTAL={{ $subtotal }};   /* replaced by the server's figure if the cart changed in another tab (409 total_changed) */
  const FREEMIN={{ $freeMin }}, FREEBASE={{ $freeBase ?? $subtotal }};
  const form=document.getElementById('co-form');
  let shipCost=0, shipId='', coupon={!! $coupon ? json_encode($coupon) : 'null' !!};
  /* the server's own totals after it refused a changed total; dropped as soon as shipping or the coupon changes */
  let override=null;
  /* a placed card order whose payment failed (declined, 3-D Secure closed): the next click confirms the same
     PaymentIntent again instead of placing a second order — while the total and the way of paying are unchanged */
  let retry=null;
  const M={!! json_encode([
    'place' => __('Place order&nbsp;&nbsp;'), 'placing' => __('Placing order…'), 'ship' => __('Please choose a shipping method.'),
    'pickup' => __('Please confirm you will pick this order up in person at our shop.'), 'terms' => __('Please read and accept the Terms of Use to place your order.'),
    'net' => __('Network error. Please try again.'), 'error' => __('Something went wrong. Please try again or call (813) 524-6491.'), 'free' => __('Free'),
  ], JSON_UNESCAPED_UNICODE) !!};""")

rep("""  function totals(){
    var d=discount(), disc=d.amt;
    var dr=document.getElementById('disc-row'); if(dr){dr.hidden=!(disc>0);document.getElementById('disc-code').textContent=d.label;document.getElementById('disc-amt').textContent=disc.toFixed(2);}
    var freeShip=coupon&&coupon.free_shipping&&!/^warp-|freight/.test(shipId); // coupons never cover freight (CartService::isFreight)
    var eff=freeShip?0:shipCost;
    document.getElementById('sum-ship').textContent=shipId?(eff>0?money(eff):'Free'):'TBD';
    var t=Math.max(0,SUBTOTAL-disc)+eff;
    document.getElementById('sum-total').textContent=money(t);
    document.getElementById('place-total').textContent=money(t);""","""  function totals(){
    var d=discount(), disc=override?Number(override.discount):d.amt;
    var dr=document.getElementById('disc-row'); if(dr){dr.hidden=!(disc>0);document.getElementById('disc-code').textContent=d.label;document.getElementById('disc-amt').textContent=disc.toFixed(2);}
    var freeShip=coupon&&coupon.free_shipping&&!/^warp-|freight/.test(shipId); // coupons never cover freight (CartService::isFreight)
    var eff=override?Number(override.shipping):(freeShip?0:shipCost);
    document.getElementById('sum-ship').textContent=shipId?(eff>0?money(eff):M.free):'TBD';
    var t=override?Number(override.total):Math.max(0,SUBTOTAL-disc)+eff;
    document.getElementById('sum-total').textContent=money(t);
    var pt=document.getElementById('place-total'); if(pt)pt.textContent=money(t);""")

rep("""  /* coupon */
  function applyCoupon(){""","""  /* the Place order button back to its label + live total (a bare textContent would drop #place-total) */
  function resetBtn(){
    var b=document.getElementById('place-btn'); b.disabled=false;
    b.innerHTML=M.place+'<span id="place-total"></span>'; totals();
  }
  window.udsResetPlace=resetBtn;
  function inlineMsg(id,msg,focusEl){
    var el=document.getElementById(id); if(el)el.textContent=msg||'';
    if(msg&&focusEl){focusEl.focus();focusEl.scrollIntoView({block:'center',behavior:'smooth'});}
  }
  function showErrors(list){
    var eb=document.getElementById('co-errors'), ul=eb.querySelector('ul'); ul.innerHTML='';
    (list&&list.length?list:[M.error]).forEach(function(e){var li=document.createElement('li');li.textContent=e;ul.appendChild(li);});
    eb.style.display='block'; eb.focus({preventScroll:true});
    window.scrollTo({top:eb.getBoundingClientRect().top+scrollY-120,behavior:'smooth'});
  }
  /* card payment refused after the order was placed: the button is ready to try the same payment again */
  window.udsPlaceFailed=function(err){
    // a PaymentIntent that can no longer be confirmed (cancelled, already paid): the next click asks the server
    if(err&&err.code==='payment_intent_unexpected_state') retry=null;
    resetBtn();
  };
  function payMode(){
    if((form.querySelector('[name=payment_method]:checked')||form.querySelector('[name=payment_method]')||{}).value==='net_terms') return 'terms';
    return (window.udsSavedCard&&window.udsSavedCard())||'new';
  }

  /* coupon */
  function applyCoupon(){""")

rep("""        err.textContent='';coupon=res.data;""","""        err.textContent='';coupon=res.data;override=null;""")
rep("""      coupon=null;document.getElementById('coupon-in').style.display='flex';""","""      coupon=null;override=null;document.getElementById('coupon-in').style.display='flex';""")
rep("""  function doLoadShipping(){
    const diff""","""  function doLoadShipping(){
    override=null;
    const diff""")
rep("""  document.getElementById('ship-select').onchange=function(){
    var o=this.options[this.selectedIndex];shipId=this.value;shipCost=o?Number(o.getAttribute('data-cost')||0):0;totals();pickupUI();
  };""","""  document.getElementById('ship-select').onchange=function(){
    var o=this.options[this.selectedIndex];override=null;shipId=this.value;shipCost=o?Number(o.getAttribute('data-cost')||0):0;inlineMsg('ship-msg','');totals();pickupUI();
  };
  document.getElementById('pickup-ack').addEventListener('change',function(){inlineMsg('pickup-msg','');this.closest('.co-agree').style.outline='';});
  document.getElementById('agree-terms').addEventListener('change',function(){inlineMsg('terms-msg','');this.closest('.co-agree').style.outline='';});""")

start=s.index("  /* place order */\n  document.getElementById('place-btn').onclick=function(){")
end=s.index("  /* card vs invoice (business accounts) */")
s=s[:start]+"""  /* place order */
  document.getElementById('place-btn').onclick=function(){
    document.getElementById('co-errors').style.display='none';
    if(!form.reportValidity())return;   // the browser points at the first empty required field
    if(!shipId){inlineMsg('ship-msg',M.ship,document.getElementById('ship-select'));return;}
    if(shipId===PICKUP){var pa=document.getElementById('pickup-ack');if(!pa.checked){pa.closest('.co-agree').style.outline='2px solid var(--err)';inlineMsg('pickup-msg',M.pickup,pa);return;}}
    var ag=document.getElementById('agree-terms'); if(ag&&!ag.checked){ag.closest('.co-agree').style.outline='2px solid var(--err)';ag.closest('.co-agree').style.borderRadius='12px';inlineMsg('terms-msg',M.terms,ag);return;}
    const btn=this;btn.disabled=true;btn.textContent=M.placing;
    /* deferred-intent mode: the Payment Element must validate before the order is created,
       otherwise an unpayable order is left behind every time a card detail is wrong */
    var mode=payMode(), onTerms=mode==='terms', onSaved=!onTerms&&mode!=='new';
    var cents=window.UDS_TOTAL_CENTS;
    if(retry&&(retry.cents!==cents||retry.mode!==mode)) retry=null;   // the total or the card changed: place it again (the server updates the same order)
    ((window.udsStripeValidate&&!onTerms&&!onSaved)?window.udsStripeValidate():Promise.resolve({}))
      .then(sub=>{
        if(sub&&sub.error){
          document.getElementById('stripe-card-err').textContent=sub.error.message||udsT('Please check your payment details.');
          document.getElementById('stripe-card').scrollIntoView({block:'center',behavior:'smooth'});
          resetBtn();
          return Promise.reject('invalid');
        }
        // same order, same total: try the payment again without placing anything new
        if(retry&&window.udsStripeConfirm){window.udsStripeConfirm(retry.cs,retry.redirect,btn);return Promise.reject('retrying');}
        var fd=new URLSearchParams(new FormData(form)); fd.set('expected_total',String(cents));
        return fetch('/checkout/place',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body:fd});
      })
      .then(r=>r.json()).then(res=>{
        if(!res.success){
          if(res.code==='total_changed'&&res.totals){ SUBTOTAL=Number(res.totals.subtotal); override=res.totals; retry=null; }
          showErrors(res.errors);
          resetBtn();return;
        }
        if(res.data.client_secret && window.udsStripeConfirm){
          retry={cs:res.data.client_secret,redirect:res.data.redirect,cents:cents,mode:mode};
          window.udsStripeConfirm(res.data.client_secret,res.data.redirect,btn);
        }
        else{location.href=res.data.redirect;}
      }).catch(e=>{
        if(e==='invalid'||e==='retrying')return;   // the card form already said what is wrong / the payment is being retried
        resetBtn();showErrors([M.net]);
      });
  };

"""+s[end:]

rep("""body:new URLSearchParams({country:a.country||'US',state:a.state||'',zip:a.postal_code||'',city:a.city||''})})""","""body:new URLSearchParams({country:a.country||'US',state:a.state||'',zip:a.postal_code||'',city:a.city||'',express:'1'})})""")
rep("""        shipping_method:(e.shippingRate||{}).id||'',payment_method:'card',express:'1',agree_terms:'1'});""","""        shipping_method:(e.shippingRate||{}).id||'',payment_method:'card',express:'1',agree_terms:'1'});
      var picked=rates[(e.shippingRate||{}).id]||chosen; fd.set('expected_total',String(cents(totalWith(picked?picked.cost:0))));""")
open(p,'w').write(s)
