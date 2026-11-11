(function () {
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  function money(n){return Number(n)>0?'$'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}):T('Call for price');}
  /* one polite live region for add-to-cart failures (the "!" glyph alone said nothing) */
  var note=null,noteT;
  function notify(msg){
    if(!note){note=document.createElement('div');note.setAttribute('role','alert');note.style.cssText='position:fixed;left:50%;bottom:20px;transform:translateX(-50%);z-index:300;max-width:min(92vw,460px);background:var(--ink,#1b2433);color:#fff;padding:.7rem 1rem;border-radius:10px;font-size:.9rem;box-shadow:0 8px 24px rgba(0,0,0,.2)';document.body.appendChild(note);}
    note.textContent=msg; note.hidden=false; clearTimeout(noteT); noteT=setTimeout(function(){note.hidden=true;},4200);
  }

  /* ---------- cart badge ---------- */
  function bumpCart(count){
    var b=document.querySelector('[data-cart-count]');
    if(b){b.textContent=count;b.hidden=false;}
  }
  // the + is an icon: keep its markup (textContent is empty) and restore it after the tick, even on fast double taps
  function flash(btn,ok){
    if(btn._orig==null)btn._orig=btn.innerHTML;
    clearTimeout(btn._ft); btn.textContent=ok?'\u2713':'!'; btn.style.filter='brightness(1.15)';
    btn._ft=setTimeout(function(){btn.innerHTML=btn._orig;btn._orig=null;btn.style.filter='';},900);
  }
  function addToCart(id,qty,btn){
    return fetch('/shop/catalog/add',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({id:id,qty:qty||1})})
      .then(function(r){
        if(r.status===419)return {success:false,error:T('Session expired — reload the page and try again.')};
        if(r.status===429)return {success:false,error:T('Too many tries — wait a minute and try again.')};
        return r.json().catch(function(){return {success:false};});
      }).then(function(d){
        if(d.success){bumpCart(d.count); if(btn)flash(btn,true);}
        else { if(btn)flash(btn,false); notify(d.error||T('Could not add to cart. Please try again.')); }
        return d;
      }).catch(function(){if(btn)flash(btn,false); notify(T('Network error. Please try again.'));});
  }

  /* ---------- stock popover (hover +) ---------- */
  var sp=document.getElementById('stock-pop');
  function stockHtml(stock,status,source){
    // a count only when it is > 0 (never "0 available"); supplier-only stock says so instead of a Florida count
    // the page sends the real count up to 50 and "50+" above it
    var cls='in',label=T('In Stock'),qty=(stock==='50+'||Number(stock)>0)?String(stock):'';
    if(status==='outofstock'){cls='out';label=T('Out of Stock');}
    else if(status==='onbackorder'){cls='bo';label=T('Backorder');}
    else if(source==='supplier'){label=T('Ships from our supplier');qty='';}
    var q=(cls==='in'&&qty!=='')?('<b>'+qty+'</b> '+T('available')):'';
    return '<div class="'+cls+'"><span class="dot"></span>'+label+'</div>'+q;
  }
  /* ---------- alternate-SKU popover (hover +) ---------- */
  var altPop=document.getElementById('alt-pop');
  if(!altPop){altPop=document.createElement('div');altPop.id='alt-pop';altPop.className='alt-pop';document.body.appendChild(altPop);}
  var altCache={},altHideT, TOUCH=window.matchMedia('(hover: none)');
  function positionAlt(btn){
    var r=btn.getBoundingClientRect(), w=altPop.offsetWidth||460, h=altPop.offsetHeight||120;
    var left=r.left+r.width/2-w/2; left=Math.max(8,Math.min(window.innerWidth-w-8,left));
    var top=r.bottom+8; if(top+h>window.innerHeight-8 && r.top-8-h>8) top=r.top-8-h;
    altPop.style.left=left+'px'; altPop.style.top=(top+window.scrollY)+'px'; altPop.style.transform='none';
  }
  function altRow(a,isCur,cls){
    var was=(a.regular>a.price)?'<span class="ap-was">$'+Number(a.regular).toFixed(2)+'</span>':'';
    var note=isCur?(/U$/i.test(a.sku||'')?T('UDS — our brand'):'Spicer'):esc(a.note||'');
    var type=isCur?T('Current'):esc(a.type||'');
    var buy=Number(a.price)>0;
    return '<div class="ap-row'+(isCur?' cur':'')+'">'+(buy?'<button class="ap-add" data-add="'+a.id+'" title="'+T('Add to cart')+'" aria-label="'+T('Add to cart')+' '+esc(a.sku)+'">+</button>':'<span></span>')+
      '<a class="ap-pn" href="/parts/'+encodeURIComponent(a.sku||'')+'">'+esc(a.sku)+'</a>'+
      '<span class="ap-note">'+note+'</span><span class="ap-type'+(isCur?' cur':'')+'">'+type+'</span>'+
      '<span class="ap-price '+cls+'">'+(buy?was+'$'+Number(a.price).toFixed(2):T('Call for price'))+'</span></div>';
  }
  function renderAlt(d){
    var rows=[]; if(d.current) rows.push(d.current); (d.alternates||[]).forEach(function(a){rows.push(a);});
    var prices=rows.map(function(x){return Number(x.price);}), mn=Math.min.apply(null,prices), mx=Math.max.apply(null,prices);
    function cls(p){ return (mn===mx)?'':(p===mn?'ap-lo':(p===mx?'ap-hi':'')); }
    altPop.innerHTML='<div class="ap-h"><span></span><span>'+T('Part Number')+'</span><span>'+T('Notes')+'</span><span>'+T('Type')+'</span><span class="r">'+T('Price')+'</span></div>'+
      rows.map(function(a,i){return altRow(a, !!d.current && i===0, cls(Number(a.price)));}).join('');
    altPop.querySelectorAll('.ap-add').forEach(function(b){b.onclick=function(e){e.preventDefault();e.stopPropagation();addToCart(b.getAttribute('data-add'),1,b).then(function(d){ if(d&&d.success&&TOUCH.matches) setTimeout(function(){altPop.classList.remove('on');},700); });};});
  }
  function showAltFor(btn){var id=btn.getAttribute('data-add');
    function present(d){if(!d||!d.alternates||!d.alternates.length)return;if(sp)sp.classList.remove('on');renderAlt(d);positionAlt(btn);altPop.classList.add('on');}
    if(altCache[id]){present(altCache[id]);return;}
    fetch('/shop/alternates/'+id).then(function(r){return r.json();}).then(function(d){altCache[id]=d||{};present(altCache[id]);}).catch(function(){});
  }
  altPop.addEventListener('mouseenter',function(){clearTimeout(altHideT);});
  altPop.addEventListener('mouseleave',function(){altHideT=setTimeout(function(){altPop.classList.remove('on');},200);});

  function showStock(e){
    var p=e.target.closest&&e.target.closest('.p-plus'); if(!p||!sp||TOUCH.matches)return;
    clearTimeout(altHideT);
    sp.innerHTML=stockHtml(p.getAttribute('data-stock'),p.getAttribute('data-status'),p.getAttribute('data-source'));
    var r=p.getBoundingClientRect();
    sp.style.left=(r.left+r.width/2)+'px'; sp.style.top=(r.top-12)+'px';
    sp.style.transform='translate(-50%,-100%)'; sp.classList.add('on');
    showAltFor(p);
  }
  function hideStock(e){
    if(e.target.closest&&e.target.closest('.p-plus') && !TOUCH.matches){ if(sp)sp.classList.remove('on'); altHideT=setTimeout(function(){ if(!altPop.contains(document.activeElement))altPop.classList.remove('on');},200); }
  }
  document.addEventListener('mouseover',showStock);
  document.addEventListener('mouseout',hideStock);
  document.addEventListener('focusin',showStock);   // keyboard users get the same stock / alternates popovers
  document.addEventListener('focusout',hideStock);

  /* ---------- image popover (hover pic) ---------- */
  var ip=document.getElementById('img-pop'), ipImg=ip?ip.querySelector('img'):null;
  document.addEventListener('mouseover',function(e){
    var pic=e.target.closest('.p-pic'); if(!pic||!ip)return;
    ipImg.src=pic.getAttribute('data-zoom')||pic.src;
    positionImg(e); ip.classList.add('on');
  });
  document.addEventListener('mousemove',function(e){ if(ip&&ip.classList.contains('on'))positionImg(e); });
  document.addEventListener('mouseout',function(e){ if(e.target.closest('.p-pic')&&ip)ip.classList.remove('on'); });
  function positionImg(e){
    var w=284,h=284, x=e.clientX+22, y=e.clientY-h/2;
    if(x+w>window.innerWidth-10)x=e.clientX-w-22;
    if(y<10)y=10; if(y+h>window.innerHeight-10)y=window.innerHeight-h-10;
    ip.style.left=x+'px'; ip.style.top=y+'px';
  }

  /* ---------- add (click) ---------- */
  document.addEventListener('click',function(e){
    var altBtn=e.target.closest('.p-alt[data-alt-for]');
    if(altBtn){ var pb=document.querySelector('.p-plus[data-add="'+altBtn.getAttribute('data-alt-for')+'"]'); if(pb){ clearTimeout(altHideT); showAltFor(pb); } return; }
    // catalog parts only: complete driveshafts carry data-ds-add and their own handler (partials/ds-add); without this both fired
    // and this one posted an empty id ("Product not found")
    var add=e.target.closest('.p-plus[data-add]');
    if(add){
      if(sp)sp.classList.remove('on');
      // phones/tablets have no hover: a part with alternates opens the chooser first, and nothing is added until a row's + is tapped
      if(add.hasAttribute('data-has-alt') && TOUCH.matches && !altPop.classList.contains('on')){ clearTimeout(altHideT); showAltFor(add); return; }
      addToCart(add.getAttribute('data-add'),1,add); return;
    }
    if(altPop.classList.contains('on') && !e.target.closest('#alt-pop')) altPop.classList.remove('on');
  });
  /* wide tables: fade the right edge while there is more to scroll to (a table in a .ptfit box fits itself instead) */
  function fades(){document.querySelectorAll('.ptbl-wrap').forEach(function(w){
    if(w.closest('.ptfit'))return;
    var on=w.scrollWidth>w.clientWidth+2&&w.scrollLeft+w.clientWidth<w.scrollWidth-2; w.classList.toggle('is-scroll',on);
    if(!w._fade){w._fade=1;w.addEventListener('scroll',fades,{passive:true});}
  });}
  fades(); window.addEventListener('resize',fades);
  function esc(s){return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });}
  /* strings in the visitor's language: the page injects window.UDS_T; English falls through */
  function T(s){var d=window.UDS_T||{};return d[s]||s;}
})();
