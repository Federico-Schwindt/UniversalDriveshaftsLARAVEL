@extends('layouts.app')
@section('robots','noindex,follow')
@section('title','Quick order by part number | Universal Driveshafts')
@section('desc','Enter part numbers (SKUs) and quantities to add multiple driveshaft parts to your cart at once.')
@section('bodyclass','pg')
@section('content')
<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><a href="/shop">{{ __('Catalog') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Quick order') }}</span></nav>
  <div class="pagehead__row">
    <h1>{!! __('Quick order by part number') !!}</h1>
    <div class="btn-row"><a class="btn btn-ghost btn-sm" href="/shop"><x-icon name="grid"/> {!! __('Browse the catalog') !!}</a></div>
  </div>
  <p class="pagehead__sub">{!! __('Start typing a part number or name, pick from the suggestions, set quantities, and add everything to your cart at once.') !!}</p>
</div>

<section class="catsec catsec--end"><div class="wrap">
  <div class="qo">
    <div class="qo-head"><span>{!! __('Part Number (SKU)') !!}</span><span>{!! __('Qty') !!}</span><span>{!! __('Product') !!}</span><span></span></div>
    <div id="qo-rows"></div>
    <button type="button" class="btn btn-ghost btn-sm u-mt" id="qo-more">{!! __('+ Add another row') !!}</button>
    <div class="qo-foot">
      <button type="button" class="btn btn-primary" id="qo-add">{!! __('Add all to Cart') !!} <x-icon name="arrow-right"/></button>
      <a href="/cart" class="btn btn-ghost">{!! __('View Cart') !!}</a>
    </div>
    <div id="qo-msg" class="qo-msg" hidden></div>
  </div>
  <p class="pg-note">{!! __('Parts that need a quote cannot be added here: call (813) 524-6491.') !!}</p>
</div></section>

<script>
(function(){
  var CSRF=document.querySelector('meta[name="csrf-token"]').content;
  var rows=document.getElementById('qo-rows');
  var DSICON='<svg viewBox="0 0 64 40" width="26" height="26" fill="none" stroke="currentColor" stroke-width="4" stroke-linejoin="round"><circle cx="12" cy="20" r="7"/><circle cx="52" cy="20" r="7"/><rect x="19" y="16.5" width="26" height="7" rx="2"/></svg>';
  function money(n){return '$'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c];});}

  function addRow(){
    var r=document.createElement('div'); r.className='qo-row';
    r.innerHTML='<div class="qo-skuwrap"><input type="text" class="qo-sku" placeholder="'+udsT('e.g. 5-1310X or “flange yoke”')+'" autocomplete="off"><div class="qo-sug" hidden></div></div>'+
      '<input type="number" class="qo-qty" value="1" min="1">'+
      '<div class="qo-prod">—</div>'+
      '<button type="button" class="qo-del" aria-label="'+udsT('Remove')+'"></button>';
    var sku=r.querySelector('.qo-sku'), prod=r.querySelector('.qo-prod'), sug=r.querySelector('.qo-sug');
    var t, hi=-1, cur=[];

    function setFound(p){ prod.innerHTML='<b>'+esc(p.name.slice(0,44))+'</b> · '+money(p.price); prod.classList.remove('bad'); r.dataset.ok='1'; }
    function setBad(){ prod.textContent=udsT('Not found'); prod.classList.add('bad'); r.dataset.ok=''; }
    function setBlank(){ prod.textContent='—'; prod.classList.remove('bad'); r.dataset.ok=''; }

    function showSug(list){
      cur=list; hi=-1;
      if(!list.length){ sug.hidden=true; return; }
      sug.innerHTML=list.map(function(p){
        var img=p.image?'<img src="'+p.image+'" alt="">':DSICON;
        return '<div class="qo-sug-item" data-sku="'+esc(p.sku||'')+'"><span class="qo-sug-img">'+img+'</span><span class="qo-sug-b"><b>'+esc(p.name)+'</b><span>SKU '+esc(p.sku||'')+'</span></span><span class="qo-sug-price">'+money(p.price)+'</span></div>';
      }).join('');
      sug.hidden=false;
      sug.querySelectorAll('.qo-sug-item').forEach(function(el,i){
        el.onmousedown=function(e){ e.preventDefault(); pick(list[i]); };
      });
    }
    function pick(p){ sku.value=p.sku; sug.hidden=true; setFound(p); }

    sku.addEventListener('input',function(){
      clearTimeout(t); var v=sku.value.trim();
      if(v.length<2){ sug.hidden=true; setBlank(); return; }
      t=setTimeout(function(){
        fetch('/search?q='+encodeURIComponent(v)).then(function(x){return x.json();}).then(function(d){
          var list=d.results||[]; showSug(list);
          // if exact SKU match present, confirm the row
          var exact=list.filter(function(p){return (p.sku||'').toLowerCase()===v.toLowerCase();})[0];
          if(exact) setFound(exact); else if(list.length) { prod.textContent=udsT('Pick a match'); prod.classList.remove('bad'); r.dataset.ok=''; } else setBad();
        }).catch(function(){});
      },170);
    });
    sku.addEventListener('keydown',function(e){
      if(sug.hidden||!cur.length) return;
      var items=sug.querySelectorAll('.qo-sug-item');
      if(e.key==='ArrowDown'){ e.preventDefault(); hi=Math.min(cur.length-1,hi+1); }
      else if(e.key==='ArrowUp'){ e.preventDefault(); hi=Math.max(0,hi-1); }
      else if(e.key==='Enter'){ if(hi>=0){ e.preventDefault(); pick(cur[hi]); } return; }
      else return;
      items.forEach(function(x,i){ x.classList.toggle('hi',i===hi); if(i===hi)x.scrollIntoView({block:'nearest'}); });
    });
    sku.addEventListener('blur',function(){ setTimeout(function(){ sug.hidden=true; },160); });
    r.querySelector('.qo-del').onclick=function(){ r.remove(); if(!rows.children.length)addRow(); };
    rows.appendChild(r);
  }
  addRow(); addRow(); addRow();
  document.getElementById('qo-more').onclick=addRow;

  document.getElementById('qo-add').onclick=function(){
    var lines=[];
    rows.querySelectorAll('.qo-row').forEach(function(r){
      var sku=r.querySelector('.qo-sku').value.trim(), qty=r.querySelector('.qo-qty').value;
      if(sku && r.dataset.ok) lines.push({sku:sku,qty:qty});
    });
    var msg=document.getElementById('qo-msg');
    if(!lines.length){ msg.hidden=false; msg.className='qo-msg bad'; msg.textContent=udsT('Pick at least one valid part from the suggestions.'); return; }
    fetch('/quick-order/add',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({lines:JSON.stringify(lines)})})
      .then(function(x){return x.json().catch(function(){return {success:false};});}).then(function(d){
        if(!d.success || !d.added){ msg.hidden=false; msg.className='qo-msg bad'; msg.textContent=d.error||udsT('Those parts could not be added. Some may need a quote — call (813) 524-6491.'); return; }
        msg.hidden=false; msg.className='qo-msg ok'; msg.innerHTML=udsT('Added :n item(s) to your cart.',{n:d.added})+' <a href="/cart" class="textlink">'+udsT('Go to cart')+'</a>';
        var b=document.querySelector('[data-cart-count]'); if(b){ b.textContent=d.count; b.hidden=false; }
      }).catch(function(){ msg.hidden=false; msg.className='qo-msg bad'; msg.textContent=udsT('Could not reach the store. Check your connection and try again.'); });
  };
})();
</script>
@endsection
