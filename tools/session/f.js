var TT=function(s){return (window.UDS_T||{})[s]||s;};
(function(){
  var LABELS={"make":"Make","year":"Year","model":"Model","drive":"Drive","loc":"Shaft location","engine":"Engine","trans":"Transmission","trans_speeds":"Transmission speeds","trans_code":"Transmission code","cab":"Cab","bed":"Bed length","chassis":"Wheelbase","doors":"Doors","trim":"Trim","rear_wheels":"Rear wheels","transfer_case":"Transfer case","differential":"Differential"}, LOGOS=["acura","audi","bmw","buick","cadillac","chevrolet","chrysler","dodge","eagle","fiat","ford","genesis","geo","gmc","honda","hummer","hyundai","infiniti","international","isuzu","jaguar","jeep","kia","land-rover","lexus","lincoln","mazda","mercedes","mercury","merkur","mitsubishi","nissan","oldsmobile","plymouth","pontiac","porsche","range-rover","saab","saturn","scion","subaru","suzuki","toyota","volkswagen","volvo"], state={"next":{"facet":"model","label":"Model","options":["230i","330i","340ix","430i","540i","540iX","M240i","M340i","M340iX","M550i","X3","X4"]},"candidate_count":12,"chosen":{"make":"BMW","year":2020},"fitment_rows":0,"results":null};
  var CSRF=document.querySelector('meta[name="csrf-token"]').content;
  var main=document.getElementById('fdr-main'), bar=document.getElementById('fdr-bar'), quick=document.getElementById('fdr-quick'), root=document.getElementById('fdr');
  var ORDER=['make','year','model','drive','loc','engine','trans','trans_speeds','trans_code','cab','bed','chassis','doors','trim','rear_wheels','transfer_case','differential'];
  var SHORT={loc:TT("Loc"),trans_speeds:TT("Speeds"),trans_code:TT("Trans code"),chassis:TT("Wheelbase"),bed:TT("Bed"),rear_wheels:TT("Rear wheels")};
  var PH='<svg class="fdr-ph" viewBox="0 0 64 40" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"><circle cx="9" cy="20" r="6"/><circle cx="55" cy="20" r="6"/><path d="M15 17h34v6H15z"/></svg>';
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':String(s);return d.innerHTML;}
  function money(n){return '$'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function slug(s){return String(s).toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');}
  function logo(m){var s=slug(m);return LOGOS.indexOf(s)>-1?'/media/160/'+btoa('/img/makes/'+s+'.png').replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'')+'.webp':null;}
  function lab(k){return SHORT[k]||LABELS[k]||k;}
  function qs(c){var p=new URLSearchParams();ORDER.forEach(function(k){if(c[k]!==undefined&&c[k]!=='')p.set(k,c[k]);});return p.toString();}
  function keys(c){return ORDER.filter(function(k){return c[k]!==undefined;});}

  function load(chosen,push){
    root.classList.add('fdr-busy'); var q=qs(chosen);
    fetch('/api/catalog/driveshafts/step'+(q?'?'+q:''),{headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();}).then(function(d){
      state=d; if(push)history.pushState(d.chosen,'',location.pathname+(qs(d.chosen)?'?'+qs(d.chosen):'')); render();
    }).catch(function(){main.innerHTML='<p class="fdr-note">'+TT("Could not load the catalog. Please try again or call (813) 524-6491.")+'</p>';})
    .finally(function(){root.classList.remove('fdr-busy');});
  }
  function render(){
    var c=state.chosen||{}, ks=keys(c);
    var n=state.next;
    // the stepper: make/year/model always; drive and any extra question (engine, cab…) only once it is asked or
    // answered — a "Drive · Next" card the catalog never asks about read as an unfinished step
    var steps=['make','year','model'];
    ks.forEach(function(k){if(steps.indexOf(k)<0)steps.push(k);});
    if(n&&steps.indexOf(n.facet)<0)steps.push(n.facet);
    // drive settled by the data (every result has the same one): shown as done with that value, not as a question
    var auto={};
    if(c.drive===undefined&&(!n||n.facet!=='drive')&&state.results&&state.results.length&&c.model!==undefined){
      var dv=[];state.results.forEach(function(r){((r.facets||{}).drive||{values:[]}).values.forEach(function(x){if(dv.indexOf(x)<0)dv.push(x);});});
      if(dv.length===1){auto.drive=dv[0];steps.push('drive');}
    }
    steps.sort(function(a,b){return ORDER.indexOf(a)-ORDER.indexOf(b);});
    var CHECK='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
    bar.innerHTML='<ol class="fdr-steps" aria-label="'+TT("Steps")+'">'+steps.map(function(k,i){
      var num='<span class="fdr-step__n">'+(c[k]!==undefined||(!n&&state.results&&state.results.length)?CHECK:(i+1))+'</span>';
      if(auto[k]!==undefined)return '<li><div class="fdr-step is-done"><span class="fdr-step__t"><span class="fdr-step__n">'+CHECK+'</span>'+esc(lab(k))+'</span><span class="fdr-step__v"><span>'+esc(auto[k])+'</span></span></div></li>';
      if(c[k]!==undefined)return '<li><button type="button" class="fdr-step is-done" data-drop="'+k+'" aria-label="'+esc(TT("Change :item").replace(':item',lab(k)))+'"><span class="fdr-step__t">'+num+esc(lab(k))+'</span><span class="fdr-step__v"><span>'+esc(c[k])+'</span><em>'+TT("Change")+'</em></span></button></li>';
      var now=n&&n.facet===k;
      return '<li><div class="fdr-step'+(now?' is-now':'')+'"'+(now?' aria-current="step"':'')+'><span class="fdr-step__t">'+num+esc(lab(k))+'</span><span class="fdr-step__v">'+(now?TT("Choose below"):TT("Next"))+'</span></div></li>';
    }).join('')+(ks.length?'<li style="display:flex"><button type="button" class="fdr-reset" id="fdr-clear">'+TT("Start over")+'</button></li>':'')+'</ol>';
    quick.innerHTML='';
    var html=n?question(n):'';
    if(state.results)html+=results(state.results,c,!!n);
    else if(!n)html+=results([],c,false);
    main.innerHTML=html;
  }
  function question(n){
    var isMake=n.facet==='make', longest=n.options.reduce(function(a,o){return Math.max(a,String(o).length);},0);
    var tiles=n.options.map(function(o){
      if(isMake){var l=logo(o);return '<button type="button" class="fdr-tile is-make" data-facet="make" data-val="'+esc(o)+'">'+(l?'<img src="'+l+'" alt="" loading="lazy">':'<i class="mono">'+TT("UDS")+'</i>')+'<span>'+esc(o)+'</span></button>';}
      return '<button type="button" class="fdr-tile'+(longest>12?' is-text':'')+'" data-facet="'+esc(n.facet)+'" data-val="'+esc(o)+'"><b>'+esc(o)+'</b></button>';
    }).join('');
    return '<h2 class="sr-only">'+esc(TT("Choose")+' '+lab(n.facet))+'</h2><div class="fdr-tiles'+(isMake?' is-makes':(n.facet==='year'?' is-years':''))+(n.options.length<5&&!isMake?' few':'')+'">'+tiles+'</div>';
  }
  function results(rows,c,pending,title){
    if(!rows.length)return '<div class="fdr-card"><h2 style="margin-top:0">'+TT("No driveshaft found")+'</h2><p>'+TT("Nothing in the catalog matches that combination. Change a selection, or call (813) 524-6491 and we will build one to your measurements.")+'</p><a class="btn btn-primary" href="/online-builder">'+TT("Open the online builder")+'</a></div>';
    var l=c.make?logo(c.make):null;
    var head=title?'<div class="fdr-veh"><div><h2>'+esc(title)+'</h2></div></div>':'<div class="fdr-veh">'+(l?'<img src="'+l+'" alt="">':'')+'<div><h2>'+esc([c.make,c.model].filter(Boolean).join(' '))+'</h2><p>'+esc([c.year,c.drive].filter(Boolean).join(' · '))+'</p></div></div>';
    var note=(!pending&&!title&&rows.length>1)?'<p class="fdr-note">'+TT("More than one driveshaft fits these answers. Match the ID code or center bearing number on your current shaft against the notes below, or call us and we will confirm it.")+'</p>':'';
    var nf=state.next?state.next.facet:null;
    var body=rows.map(function(r){
      var f=r.facets||{}, v=function(k){return f[k]?f[k].values.join(' / '):'-';};
      var extra=Object.keys(f).filter(function(k){return ['drive','loc'].indexOf(k)<0;}).map(function(k){return lab(k)+': '+f[k].values.join(' / ');});
      if(r.weld_to_weld&&r.weld_to_weld.length)extra.push(TT("Weld to weld: ")+r.weld_to_weld.join(' / '));
      var price=r.can_buy?((r.price_list?'<span class="fdr-was">'+money(r.price_list)+'</span>':'')+'<span class="fdr-now">'+money(r.price)+'</span>'+(r.price_list?'<span class="fdr-save">'+TT("(Save")+' '+money(r.price_list-r.price)+')</span>':'')+(r.core_price?'<span class="fdr-core">+ '+money(r.core_price)+' '+TT("core")+'</span>':'')):'<span class="fdr-call">'+TT("Call for price")+'</span>';
      var add=pending&&!title?'<span class="fdr-sel">'+TT("Please select")+'<b>'+esc(lab(nf))+'</b></span>':(r.can_buy?'<span class="p-add"><button class="p-plus" data-add="'+esc(r.part_number)+'" aria-label="'+TT("Add :sku to cart").replace(':sku',esc(r.sku||r.part_number))+'" title="'+TT("Add to cart")+'">+<span class="fdr-addtxt">'+TT("Add to cart")+'</span></button></span>':'<button type="button" class="btn btn-line btn-sm" data-quote="'+esc(r.sku||r.part_number)+'" data-pn="'+esc(r.part_number)+'" data-vehicle="'+esc([c.year,c.make,c.model,c.drive].filter(Boolean).join(' '))+'">'+TT("Get a quote")+'</button>');
      var fits=r.fits&&r.fits.length?'<ul class="fdr-fits">'+r.fits.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul>':'';
      return '<tr><td data-label="Add">'+add+'</td><td data-label="Pic">'+(r.image?'<img class="fdr-pic" width="70" height="52" loading="lazy" src="'+esc(r.image)+'" data-full="'+esc(r.image)+'" alt="'+esc(r.sku||r.part_number)+'">':PH)+'</td><td data-label="Price">'+price+'</td>'+
        '<td data-label="Part number"><a class="fdr-pn" href="/catalog/driveshafts/part/'+encodeURIComponent(r.sku||r.part_number)+'" title="'+TT("Open part page")+'">'+esc(r.sku||r.part_number)+' &#8599;</a><span class="fdr-desc">'+esc(r.description||TT("Complete driveshaft"))+(r.condition&&r.condition!=='new'?' ('+esc(r.condition)+')':'')+'</span>'+fits+'</td>'+
        (title?'':'<td data-label="Model">'+esc(c.model||'-')+'</td>')+
        '<td data-label="Drive">'+esc(v('drive'))+'</td><td data-label="Loc">'+esc(v('loc'))+'</td>'+
        '<td data-label="Ctr. brg"><span class="fdr-id">'+esc((r.c_brg||[]).join(' & ')||'-')+'</span></td>'+
        '<td data-label="Notes" class="l"><span class="fdr-id">'+esc((r.notes||[]).join(' | ')||'-')+'</span>'+(extra.length?'<span class="fdr-more">'+esc(extra.join(' · '))+'</span>':'')+'</td></tr>';
    }).join('');
    var th=function(k,t){return '<th'+(c[k]!==undefined?' class="hl"':'')+'>'+t+(c[k]!==undefined?'<small>['+esc(c[k])+']</small>':'')+'</th>';};
    return '<div class="fdr-card">'+head+note+'<div class="ptbl-wrap"><table class="ptbl" id="fdr-table"><thead><tr><th>'+TT("Add")+'</th><th>'+TT("Pic")+'</th><th>'+TT("Price")+'</th><th>'+TT("Part number")+'</th>'+(title?'':th('model',TT("Model")))+th('drive',TT("Drive"))+th('loc',TT("Loc"))+'<th>'+TT("Ctr. brg")+'</th><th class="l">'+TT("Notes")+'</th></tr></thead><tbody>'+body+'</tbody></table></div></div>';
  }
  document.addEventListener('click',function(e){
    var o=e.target.closest('.fdr-tile'); if(o){var c=Object.assign({},state.chosen||{});c[o.getAttribute('data-facet')]=o.getAttribute('data-val');load(c,true);window.scrollTo({top:root.getBoundingClientRect().top+scrollY-100,behavior:'smooth'});return;}
    var d=e.target.closest('[data-drop]'); if(d){dropFrom(d.getAttribute('data-drop'));return;}
    if(e.target.closest('#fdr-back')){var ks=keys(state.chosen||{});if(ks.length)dropFrom(ks[ks.length-1]);return;}
    if(e.target.closest('#fdr-clear')){load({},true);return;}
    var a=e.target.closest('[data-add]'); if(a){a.disabled=true;var c3=state.chosen||{};fetch('/catalog/driveshafts/add',{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({part_number:a.getAttribute('data-add'),qty:1,vehicle:[c3.year,c3.make,c3.model,c3.drive].filter(Boolean).join(' ')})}).then(function(r){return r.json();}).then(function(d){a.disabled=false;if(d.success){var was=a.innerHTML;a.textContent='✓';document.querySelectorAll('[data-cart-count]').forEach(function(b){b.textContent=d.count;b.hidden=false;});setTimeout(function(){a.innerHTML=was;},1400);}else{alert(d.message||TT("Could not add this part."));}}).catch(function(){a.disabled=false;});}
  });
  function dropFrom(k){var i=ORDER.indexOf(k),c2={};
    // make/year/model/drive are a chain: removing one clears everything after it. Deeper filters are independent.
    ORDER.forEach(function(x,j){if(state.chosen[x]===undefined)return; if(i<=3?j<i:x!==k)c2[x]=state.chosen[x];}); load(c2,true);}
  document.addEventListener('change',function(e){if(e.target.id==='fdr-select'&&e.target.value){var c=Object.assign({},state.chosen||{});c[state.next.facet]=e.target.value;load(c,true);}});
  // (the old #fdr-search part-number box is gone: part numbers go through the site search)
  // hover-zoom popover (desktop) + tap lightbox (touch) for every result thumbnail
  var _zoom=document.createElement('div');_zoom.className='fdr-zoom';_zoom.innerHTML='<img alt="">';document.body.appendChild(_zoom);var _zi=_zoom.querySelector('img');
  var _lb=document.createElement('div');_lb.className='fdr-lb';_lb.innerHTML='<img alt="">';document.body.appendChild(_lb);var _lbi=_lb.querySelector('img');
  function _pos(e){var w=320,h=320,x=e.clientX+20,y=e.clientY-h/2;if(x+w>innerWidth-10)x=e.clientX-w-20;if(y<10)y=10;if(y+h>innerHeight-10)y=innerHeight-h-10;_zoom.style.left=x+'px';_zoom.style.top=y+'px';}
  document.addEventListener('mouseover',function(e){var im=e.target.closest('.fdr-pic');if(!im)return;_zi.src=im.getAttribute('data-full')||im.src;_pos(e);_zoom.style.display='block';});
  document.addEventListener('mousemove',function(e){if(_zoom.style.display==='block'&&e.target.closest('.fdr-pic'))_pos(e);});
  document.addEventListener('mouseout',function(e){if(e.target.closest('.fdr-pic'))_zoom.style.display='none';});
  document.addEventListener('click',function(e){var im=e.target.closest('.fdr-pic');if(im){_zoom.style.display='none';_lbi.src=im.getAttribute('data-full')||im.src;_lb.classList.add('on');return;}if(e.target===_lb||e.target===_lbi)_lb.classList.remove('on');});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')_lb.classList.remove('on');});
  window.addEventListener('popstate',function(){var c={};new URLSearchParams(location.search).forEach(function(v,k){c[k]=v;});load(c,false);});
  render();
})();
