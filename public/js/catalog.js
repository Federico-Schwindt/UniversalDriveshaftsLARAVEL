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
    var cls='in',label=T('In Stock'),qty=(Number(stock)>0)?Number(stock):'';
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

  /* ---------- wishlist toggle (delegated: table rows re-render on filter/sort) ---------- */
  function toggleWish(btn){
    if(btn.classList.contains('is-busy'))return;
    var on=btn.classList.contains('is-on');
    btn.classList.add('is-busy');
    fetch(on?'/wishlist/remove':'/wishlist/add',{method:'POST',
      headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({product_id:btn.getAttribute('data-wish')})})
      .then(function(r){return r.json();}).then(function(d){
        btn.classList.remove('is-busy');
        if(!d.success)return;
        btn.classList.toggle('is-on',!on);
        btn.setAttribute('aria-pressed',(!on)?'true':'false');
        var lbl=(!on)?T('Remove from wishlist'):T('Add to wishlist');
        btn.setAttribute('aria-label',lbl); btn.title=lbl;
        var b=document.querySelector('[data-wish-count]');
        if(b){b.textContent=d.count;b.hidden=d.count===0;}
      }).catch(function(){btn.classList.remove('is-busy');});
  }

  /* ---------- add + quick view (click) ---------- */
  var back=document.getElementById('qv-back'), inn=document.getElementById('qv-in');
  document.addEventListener('click',function(e){
    var altBtn=e.target.closest('.p-alt[data-alt-for]');
    if(altBtn){ var pb=document.querySelector('.p-plus[data-add="'+altBtn.getAttribute('data-alt-for')+'"]'); if(pb){ clearTimeout(altHideT); showAltFor(pb); } return; }
    var add=e.target.closest('.p-plus');
    if(add){
      if(sp)sp.classList.remove('on');
      // phones/tablets have no hover: a part with alternates opens the chooser first, and nothing is added until a row's + is tapped
      if(add.hasAttribute('data-has-alt') && TOUCH.matches && !altPop.classList.contains('on')){ clearTimeout(altHideT); showAltFor(add); return; }
      addToCart(add.getAttribute('data-add'),1,add); return;
    }
    if(altPop.classList.contains('on') && !e.target.closest('#alt-pop')) altPop.classList.remove('on');
    var wish=e.target.closest('.p-wish');
    if(wish){ toggleWish(wish); return; }
    var view=e.target.closest('.p-view');
    if(view){ openQuick(view.getAttribute('data-view')); return; }
  });
  var qvOpener=null;
  if(back){
    var dlg=back.querySelector('.qv')||back;
    dlg.setAttribute('role','dialog'); dlg.setAttribute('aria-modal','true'); dlg.setAttribute('aria-labelledby','qv-title');
    document.getElementById('qv-x').onclick=closeQuick;
    back.addEventListener('click',function(e){ if(e.target===back)closeQuick(); });
    document.addEventListener('keydown',function(e){
      if(!back.classList.contains('on'))return;
      if(e.key==='Escape'){closeQuick();return;}
      if(e.key==='Tab'){ // focus stays inside the modal
        var f=Array.prototype.filter.call(dlg.querySelectorAll('a[href],button:not([disabled]),input,select,textarea,[tabindex]:not([tabindex="-1"])'),function(el){return el.offsetParent!==null;});
        if(!f.length)return;
        if(e.shiftKey&&document.activeElement===f[0]){e.preventDefault();f[f.length-1].focus();}
        else if(!e.shiftKey&&document.activeElement===f[f.length-1]){e.preventDefault();f[0].focus();}
        else if(!dlg.contains(document.activeElement)){e.preventDefault();f[0].focus();}
      }
    });
  }
  function closeQuick(){ if(!back||!back.classList.contains('on'))return; back.classList.remove('on'); if(qvOpener&&qvOpener.focus)qvOpener.focus(); qvOpener=null; }
  function openQuick(id){
    if(!back)return;
    qvOpener=document.activeElement;
    inn.innerHTML='<div id="qv-title" style="padding:40px;text-align:center;color:#8a94a6">'+T('Loading…')+'</div>';
    back.classList.add('on');
    var x=document.getElementById('qv-x'); if(x)x.focus();
    fetch('/shop/quick-view/'+id).then(function(r){return r.ok||r.status===404?r.json():{found:false};}).then(function(d){
      if(!d.found){inn.innerHTML='<div id="qv-title" style="padding:40px;text-align:center">'+T('Not found.')+'</div>';return;}
      inn.innerHTML=renderQuick(d.product);
      var ab=inn.querySelector('[data-qadd]');
      if(ab)ab.onclick=function(){
        var qty=inn.querySelector('.qv-qty').value||1;
        addToCart(ab.getAttribute('data-qadd'),qty,null).then(function(res){ if(res&&res.success){ab.textContent=T('Added');setTimeout(closeQuick,700);} });
      };
    });
  }
  function renderQuick(p){
    var onSale=p.on_sale && p.regular>p.price;
    var stockLine=stockHtml(p.stock,p.stock_status,p.stock_source), buy=Number(p.price)>0;
    var img=p.image?('<div class="qv-img"><img src="'+esc(p.image)+'" alt="'+esc(p.sku)+'"></div>'):'';
    return img+
      '<div class="qv-top"><div><div class="qv-name" id="qv-title">'+esc(p.name)+'</div>'+
        '<div class="qv-sku">'+esc(p.sku||'')+'</div>'+
        '<div class="qv-brand">'+(p.brand?('<span class="nw">'+esc(p.brand)+'</span>'):'')+(p.series?(T('Series ')+esc(p.series)):'')+'</div></div>'+
        '<div class="qv-price">'+(onSale?('<div class="was">'+money(p.regular)+'</div>'):'')+
          '<div class="now">'+money(p.price)+'</div>'+(onSale?('<div class="save">'+T('SAVE ')+money(p.regular-p.price)+'</div>'):'')+'</div>'+
      '</div>'+
      '<div class="qv-specs">'+
        (p.weight!==''?('<div class="qs"><span>'+T('Weight')+'</span><b>'+esc(p.weight)+'</b></div>'):'')+
        (p.style?('<div class="qs"><span>'+T('Style')+'</span><b>'+esc(p.style)+'</b></div>'):'')+
        '<div class="qs"><span>'+T('Availability')+'</span><b style="font-size:.85rem">'+stockLine+'</b></div>'+
      '</div>'+
      (p.notes?('<p style="color:#4a5568;font-size:.9rem;margin:0 0 16px"><b>'+T('Notes:')+'</b> '+esc(p.notes)+'</p>'):'')+
      '<div class="qv-act">'+
        (buy?'<input type="number" class="qv-qty" value="1" min="1" aria-label="'+esc(T('Quantity'))+'">':'')+
        '<a href="/parts/'+encodeURIComponent(p.sku)+'" class="btn btn-ghost" style="border-color:var(--line);color:var(--ink)">'+T('Details')+'</a>'+
        (buy?'<button class="btn btn-primary" data-qadd="'+p.id+'">'+T('+ Add to Cart')+'</button>'
            :'<button type="button" class="btn btn-primary" data-quote="'+esc(p.sku)+'" data-pn="'+esc(p.sku)+'">'+T('Get a quote')+'</button>')+
      '</div>';
  }
  /* wide tables: fade the right edge while there is more to scroll to */
  function fades(){document.querySelectorAll('.ptbl-wrap').forEach(function(w){
    var on=w.scrollWidth>w.clientWidth+2&&w.scrollLeft+w.clientWidth<w.scrollWidth-2; w.classList.toggle('is-scroll',on);
    if(!w._fade){w._fade=1;w.addEventListener('scroll',fades,{passive:true});}
  });}
  fades(); window.addEventListener('resize',fades);
  var st=document.getElementById('shop-table'); if(st&&window.MutationObserver)new MutationObserver(fades).observe(st,{childList:true});
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
  /* strings in the visitor's language: the page injects window.UDS_T; English falls through */
  function T(s){var d=window.UDS_T||{};return d[s]||s;}
})();
