
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
    if(!/^\d{5}$/.test(z)){show('<p class="est__err">Enter a 5-digit US ZIP code.</p>');zip.focus();return;}
    try{localStorage.setItem(KEY,z);}catch(e){}
    go.disabled=true;show('<p class="est__wait">Getting UPS rates for '+esc(z)+'…</p>');
    fetch('/shop/estimate',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},
      body:new URLSearchParams({id:form.dataset.estId,qty:q,zip:z})})
      .then(function(r){return r.json();})
      .then(function(d){
        go.disabled=false;
        if(!d||!d.success||!d.rates||!d.rates.length){show('<p class="est__err">'+esc((d&&d.error)||'Could not get a rate, call (813) 524-6491.')+'</p>');return;}
        var h='',f=d.free||{};
        h+='<ul class="est__list">';
        if(f.eligible){h+='<li class="est__li-free"><span>Free shipping <small>UPS Ground · parts orders over '+money(f.min).replace(/\.00$/,'')+' ship free</small></span><b>Free</b></li>';}
        d.rates.forEach(function(r,i){if(f.eligible&&i===0)return;h+='<li><span>'+esc(r.label)+(f.eligible?'<small>faster option</small>':'')+(r.packages>1?'<small>'+r.packages+' packages</small>':'')+'</span><b>'+money(r.cost)+'</b></li>';});
        h+='</ul>';
        if(!f.eligible){
          if(f.excluded)h+='<p class="est__note">Tubing and complete driveshafts ship at the UPS rate and do not count toward free shipping.</p>';
          else if(f.remaining>0)h+='<p class="est__note">Add '+money(f.remaining)+' more in parts and the whole order ships free.</p>';
        }
        h+='<p class="est__note est__meta">Estimate for qty '+q+' to ZIP '+esc(z)+' · '+(d.packages>1?d.packages+' packages':'1 package')+' · ~'+Number(d.weight_lb).toLocaleString('en-US',{maximumFractionDigits:1})+' lb. Final rate is confirmed at checkout.</p>';
        show(h);
      })
      .catch(function(){go.disabled=false;show('<p class="est__err">Could not get a rate, call (813) 524-6491.</p>');});
  });
})();
