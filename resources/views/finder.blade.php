@extends('layouts.app')
@section('title', __('Find your driveshaft by year, make and model') . ' | UDS')
@section('desc', __('Choose your make, year and model and we narrow the catalog to the complete driveshaft that fits. 4,500+ driveshafts, 1955 to 2026.'))
@section('bodyclass','pg')
@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">
<style>
.fdr-body{background:var(--paper,#fff);padding:28px 0 64px;min-height:60vh}
/* stepper: make → year → model → drive (+ any extra question the catalog asks) */
.fdr-steps{list-style:none;margin:0 0 26px;padding:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,230px));gap:10px;align-items:stretch}
.fdr-steps>li{min-width:0}
.fdr-step{display:flex;flex-direction:column;gap:8px;width:100%;height:100%;padding:12px 14px 13px;border:1px solid var(--line,#e4e8f0);border-radius:12px;background:var(--paper,#fff);text-align:left;font:inherit;color:var(--ink-3,#5c6879)}
.fdr-step__t{display:flex;align-items:center;gap:8px;font-weight:700;font-size:.86rem;color:var(--ink,#141b28)}
.fdr-step__n{flex:none;display:grid;place-items:center;width:20px;height:20px;border-radius:50%;border:1px solid var(--line-strong,#d4dae6);font:600 .7rem/1 var(--font-m,monospace);color:var(--ink-3,#5c6879)}
.fdr-step__v{font-size:.84rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.fdr-step.is-now{border:2px solid var(--blue,#1246ab);background:var(--blue-050,#eef3fc);padding:11px 13px 12px}
.fdr-step.is-now .fdr-step__n{background:var(--blue,#1246ab);border-color:var(--blue,#1246ab);color:#fff}
.fdr-step.is-now .fdr-step__v{color:var(--blue,#1246ab);font-weight:600}
button.fdr-step{cursor:pointer;transition:border-color .15s,box-shadow .15s}
button.fdr-step:hover{border-color:var(--blue,#1246ab);box-shadow:0 6px 18px -12px rgba(18,70,171,.5)}
button.fdr-step:focus-visible{outline:2px solid var(--blue,#1246ab);outline-offset:2px}
.fdr-step.is-done .fdr-step__n{background:var(--teal-500,#14a38b);border-color:var(--teal-500,#14a38b);color:#fff}
.fdr-step.is-done .fdr-step__n svg{width:11px;height:11px}
.fdr-step.is-done .fdr-step__v{display:flex;justify-content:space-between;gap:8px;color:var(--ink,#141b28);font-weight:600}
.fdr-step.is-done .fdr-step__v em{font-style:normal;font-weight:600;font-size:.76rem;color:var(--blue,#1246ab)}
.fdr-reset{align-self:center;justify-self:start;border:0;background:none;color:var(--ink-3,#5c6879);font:600 .86rem/1 inherit;cursor:pointer;padding:10px 4px;text-decoration:underline;text-underline-offset:3px}
.fdr-reset:hover{color:var(--blue,#1246ab)}
.fdr-quick{display:none}
/* the answers to the current step */
.fdr-tiles{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
.fdr-tiles.is-years{grid-template-columns:repeat(auto-fill,minmax(104px,1fr));gap:10px}
.fdr-tiles.is-makes{grid-template-columns:repeat(auto-fill,minmax(156px,1fr));gap:14px}
.fdr-tiles.few{grid-template-columns:repeat(auto-fill,minmax(200px,260px))}
.fdr-tile{background:var(--paper,#fff);border:1px solid var(--line,#e4e8f0);border-radius:14px;cursor:pointer;font:inherit;padding:14px 12px;min-height:64px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:var(--ink,#141b28);transition:transform .15s,box-shadow .15s,border-color .15s,background .15s;min-width:0}
.fdr-tile:hover,.fdr-tile:focus-visible{transform:translateY(-2px);border-color:var(--blue,#1246ab);box-shadow:0 12px 26px -16px rgba(18,70,171,.55);outline:0}
.fdr-tile:focus-visible{box-shadow:0 0 0 3px rgba(18,70,171,.25)}
.fdr-tile:active{transform:none;background:var(--blue-050,#eef3fc)}
.fdr-tile b{font-family:var(--font-h,inherit);font-weight:700;font-size:1.06rem;letter-spacing:-.005em;overflow-wrap:anywhere;text-align:center;line-height:1.2}
.fdr-tiles.is-years .fdr-tile{min-height:56px;padding:12px 8px}
.fdr-tiles.is-years .fdr-tile b{font:600 1.08rem/1 var(--font-m,monospace);font-variant-numeric:tabular-nums;letter-spacing:-.02em}
.fdr-tile.is-text b{font-size:.98rem}
.fdr-tile.is-make{min-height:132px;padding:18px 12px 14px}
.fdr-tile.is-make img{width:100%;max-width:112px;height:64px;object-fit:contain;transition:transform .2s}
.fdr-tile.is-make:hover img{transform:scale(1.05)}
.fdr-tile.is-make .mono{width:64px;height:64px;border-radius:50%;background:var(--blue-050,#eef3fc);display:grid;place-items:center;font-family:var(--font-h,inherit);font-weight:800;font-size:1.25rem;color:var(--blue,#1246ab)}
.fdr-tile.is-make span{font-size:.82rem;font-weight:600;letter-spacing:.01em;text-align:center;color:var(--ink-2,#3a4557)}
@media (prefers-reduced-motion:reduce){.fdr-tile,.fdr-tile.is-make img{transition:none}.fdr-tile:hover{transform:none}}
@media(max-width:760px){
  .fdr-steps{display:flex;overflow-x:auto;scrollbar-width:none;margin:0 0 20px;padding:2px;scroll-snap-type:x proximity}
  .fdr-steps::-webkit-scrollbar{display:none}
  .fdr-steps>li{flex:0 0 auto;min-width:138px;max-width:180px;scroll-snap-align:start}
  .fdr-tiles.is-makes{grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
  .fdr-tiles.is-years{grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
  .fdr-tiles{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
  .fdr-tile.is-make{min-height:104px;padding:12px 6px 10px;gap:8px}
  .fdr-tile.is-make img{height:46px}
  .fdr-tile.is-make span{font-size:.74rem}
  .fdr-tiles.is-years .fdr-tile b{font-size:.98rem}
}
.fdr-busy{opacity:.5;pointer-events:none}
/* results */
.fdr-card{background:#fff;border:1px solid var(--line,#e4e8f0);border-radius:16px;box-shadow:0 10px 30px -22px rgba(16,32,64,.35);padding:26px;margin-top:8px}
@media(max-width:760px){.fdr-card{padding:16px}}
.fdr-veh{display:flex;align-items:center;gap:18px;margin-bottom:20px}
.fdr-veh img{width:84px;height:60px;object-fit:contain}
.fdr-veh h2{margin:0;font-size:clamp(1.4rem,2.6vw,2rem);line-height:1.1} /* no uppercase: it turned "M340iX" into "M340IX" */
.fdr-veh p{margin:4px 0 0;color:var(--ink-faint,#697687)}
.fdr-note{background:var(--warn-bg,#fdf6e3);border:1px solid #ecd9a4;color:var(--warn,#9a6b12);border-radius:10px;padding:12px 16px;font-size:.92rem;margin:0 0 16px}
#fdr-table{min-width:0;width:100%}
#fdr-table th.hl{background:var(--blue-700,#0e3a8c);color:#fff}
#fdr-table th small{display:block;font-weight:400;opacity:.85}
#fdr-table td{white-space:normal;overflow-wrap:normal;word-break:normal;vertical-align:middle}
#fdr-table td:last-child{overflow-wrap:anywhere;min-width:160px}
#fdr-table th:first-child,#fdr-table td:first-child{min-width:104px}
#fdr-table td:nth-child(2){min-width:84px}
#fdr-table td[data-label="Loc"],#fdr-table td[data-label="Drive"],#fdr-table td[data-label="Make"]{white-space:nowrap}
#fdr-table td[data-label="Loc"]{white-space:normal;min-width:70px}
.fdr-sel{font-size:.78rem;color:var(--warn,#9a6b12);line-height:1.3;display:block;font-weight:600}
/* the open question: which step, and what to choose */
.fdr-q{margin:0 0 12px}
.fdr-q__n{margin:0 0 2px;font:600 .74rem var(--font-m);letter-spacing:.04em;text-transform:uppercase;color:var(--blue)}
.fdr-q h2{margin:0;font-size:1.25rem}
/* results held back while a step is open: the count, and a way to look early */
.fdr-wait{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px 16px;margin:18px 0 0;padding:14px 16px;
  background:var(--paper-2);border:1px solid var(--line);border-left:3px solid var(--blue);border-radius:var(--r)}
.fdr-wait b{display:block;font-size:1rem}
.fdr-wait span{display:block;font-size:.88rem;color:var(--ink-2)}
.fdr-missing{margin:0 0 12px;padding:10px 12px;background:var(--warn-bg,#fdf6e3);border:1px solid #ecd9a4;border-radius:var(--r-sm);color:var(--warn,#9a6b12);font-size:.88rem}
.fdr-ph{width:64px;height:40px;color:var(--ink-faint,#697687);opacity:.7}
.fdr-pic{width:70px;height:52px;object-fit:contain;background:#fff;border:1px solid var(--line,#e4e8f0);border-radius:5px;vertical-align:middle;cursor:zoom-in}
.fdr-zoom{position:fixed;width:320px;height:320px;background:#fff;border:1px solid var(--line-strong,#d4dae6);border-radius:10px;box-shadow:0 20px 50px -12px rgba(11,26,58,.5);padding:8px;display:none;z-index:300;pointer-events:none}
.fdr-zoom img{width:100%;height:100%;object-fit:contain}
.fdr-lb{position:fixed;inset:0;background:rgba(11,26,58,.85);display:none;place-items:center;z-index:300;cursor:zoom-out;padding:24px}
.fdr-lb.on{display:grid}
.fdr-lb img{max-width:92vw;max-height:90vh;object-fit:contain;background:#fff;border-radius:8px}
.fdr-pn{text-decoration:none;font-family:var(--font-m,monospace);font-weight:600;font-size:1.02rem;color:var(--ink,#141b28);white-space:nowrap}
.fdr-desc{display:block;font-size:.8rem;color:var(--ink-2,#3a4557)}
.fdr-was{font-family:var(--font-m,monospace);font-size:.8rem;color:var(--ink-faint,#697687);text-decoration:line-through;display:block}
.fdr-now{font-family:var(--font-m,monospace);font-weight:600;font-size:1.05rem;white-space:nowrap}
.fdr-save{display:block;font-size:.74rem;color:#c02a2a;font-style:italic}
.fdr-core{display:block;font-size:.74rem;color:var(--ink-faint,#697687)}
.fdr-call{font-size:.84rem;font-weight:600;color:var(--blue,#1246ab)}
.fdr-id{font-family:var(--font-m,monospace);font-size:.8rem}
.fdr-more{font-size:.76rem;color:var(--ink-faint,#697687);display:block;margin-top:2px}
.fdr-fits{margin:6px 0 0;padding:0;list-style:none;font-size:.76rem;color:var(--ink-2,#3a4557)}
.fdr-help{margin-top:26px;color:var(--ink-2,#3a4557);font-size:.95rem;text-align:center}
.fdr-addtxt{display:none}
/* phone: one compact card per shaft. Photo, part number and price on top; specs two per row; a full-width add button last */
@media(max-width:760px){
  #fdr-table tbody tr{display:flex;flex-wrap:wrap;align-items:flex-start;gap:0 12px;padding:14px;border-radius:12px}
  #fdr-table tbody td{display:block;border:0!important;padding:0;text-align:left;min-width:0!important;font-size:.92rem}
  #fdr-table tbody td::before{display:block;margin-bottom:2px}
  #fdr-table tbody td[data-th]::before{content:attr(data-th)}
  #fdr-table tbody td.is-empty{display:none}
  #fdr-table tbody td[data-label="Pic"]{order:1;flex:0 0 64px;padding:0}
  #fdr-table tbody td[data-label="Pic"] .fdr-pic,#fdr-table tbody td[data-label="Pic"] .fdr-ph{width:64px;height:52px}
  #fdr-table tbody td[data-label="Part number"]{order:2;flex:1 1 0}
  #fdr-table tbody td[data-label="Price"]{order:3;flex:0 0 auto;text-align:right}
  #fdr-table tbody td[data-label="Part number"]::before,#fdr-table tbody td[data-label="Price"]::before,#fdr-table tbody td[data-label="Pic"]::before{display:none}
  #fdr-table .fdr-pn{font-size:1rem;white-space:normal;overflow-wrap:anywhere}
  #fdr-table .fdr-now{font-size:1.15rem}
  #fdr-table tbody td[data-label="Model"],#fdr-table tbody td[data-label="Drive"],#fdr-table tbody td[data-label="Loc"],#fdr-table tbody td[data-label="Ctr. brg"]{
    order:4;flex:1 1 calc(50% - 6px);margin-top:12px;padding-top:10px;border-top:1px solid var(--line-2,#eef1f7)!important}
  #fdr-table tbody td[data-label="Notes"]{order:5;flex:1 1 100%;margin-top:12px;text-align:left}
  #fdr-table tbody td[data-label="Notes"] .fdr-id{font-family:inherit;font-size:.9rem;color:var(--ink,#141b28)}
  #fdr-table tbody td[data-label="Notes"] .fdr-more{font-size:.82rem;margin-top:4px}
  #fdr-table tbody td[data-label="Add"]{order:6;flex:1 1 100%;margin-top:14px;padding:0}
  #fdr-table tbody td[data-label="Add"]::before{display:none}
  #fdr-table td[data-label="Add"] .p-add,#fdr-table td[data-label="Add"] .p-plus{width:100%}
  #fdr-table td[data-label="Add"] .p-plus{height:46px;border-radius:10px;display:flex;align-items:center;justify-content:center;gap:8px;font-size:1.1rem;font-weight:600}
  #fdr-table .fdr-addtxt{display:inline;font-size:.95rem}
  #fdr-table td[data-label="Add"] .btn{width:100%;justify-content:center;height:46px}
  #fdr-table td[data-label="Add"] .fdr-sel{text-align:center;padding:10px;border:1px dashed var(--line-strong,#d4dae6);border-radius:10px}
}
</style>

<div class="wrap pagehead">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><a href="/shop">{{ __('Catalog') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Complete driveshafts') }}</span></nav>
  <div class="pagehead__row">
    <h1>{{ __('Complete driveshafts') }}</h1>
    <div class="btn-row"><a class="btn btn-ghost btn-sm" href="/online-builder"><x-icon name="wrench"/> {{ __('Build one to spec') }}</a></div>
  </div>
  <p class="pagehead__sub">{{ __('Pick your make, year, model and drive, and we narrow 4,500+ complete driveshafts down to the one that fits, with price and stock.') }}</p>
</div>

<section class="fdr-body"><div class="wrap" id="fdr">
  <div class="fdr-bar" id="fdr-bar"></div>
  <div class="fdr-quick" id="fdr-quick"></div>
  <div id="fdr-main" aria-live="polite"></div>
  <p class="fdr-help">{{ __('Not sure about a detail? Call (813) 524-6491 and a driveline tech will confirm the fit before you order.') }}</p>
  @php $vehMakes = app(\App\Services\VehiclePages::class)->makeMap(); @endphp
  @if($vehMakes)
  <nav class="veh-browse" aria-labelledby="veh-browse-h" style="margin-top:1.75rem;padding-top:1.1rem;border-top:1px solid var(--line)">
    <div class="catsec__head"><h2 id="veh-browse-h">{{ __('Browse driveshafts by make') }}</h2><a class="textlink" href="/driveshafts">{{ __('All makes') }} <x-icon name="arrow-right"/></a></div>
    <ul class="pg-chips">
      @foreach($vehMakes as $vSlug => $vName)<li><a href="/driveshafts/{{ $vSlug }}">{{ $vName }} {{ __('driveshafts') }}</a></li>@endforeach
    </ul>
  </nav>
  @endif
  <p class="xref-note">{{ __('Vehicle makes, logos, models and manufacturer part numbers are trademarks of their respective owners, used for identification and fitment reference only. Universal Driveshafts is not affiliated with them. Fitment data may contain errors, verify before ordering.') }} <a href="/terms">{{ __('Terms of use') }}</a></p>
</div></section>

@if(app()->getLocale() !== 'en')
<script>window.UDS_T=Object.assign(window.UDS_T||{},{!! json_encode(collect(['Step :n','Choose the :item',':n driveshafts fit so far','Answer the question above to see your exact driveshaft.','Show all :n now','Choose :item first','Missing step:','Choose it above to see which shaft is yours and add it to the cart.','Add :sku to cart','Remove :item','Loc','Speeds','Trans code','Wheelbase','Bed','Rear wheels','Could not load the catalog. Please try again or call (813) 524-6491.','&larr; Back','&times;','Clear all','UDS','Choose','Steps','Change :item','Change','Choose below','Next','Start over','No driveshaft found','Nothing in the catalog matches that combination. Change a selection, or call (813) 524-6491 and we will build one to your measurements.','Open the online builder','More than one driveshaft fits these answers. Match the ID code or center bearing number on your current shaft against the notes below, or call us and we will confirm it.','Weld to weld: ','(Save','core','Call for price','Please select','Add to cart','Get a quote','Open part page','Complete driveshaft','Add','Pic','Price','Part number','Model','Drive','Ctr. brg','Notes','Position','Center bearing','Could not add this part.','&larr; Back to vehicle search'])->mapWithKeys(fn ($k) => [$k => __($k)]), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP) !!});</script>
@endif
<script>var TT=function(s){return (window.UDS_T||{})[s]||s;};
(function(){
  var LABELS={!! json_encode(array_map(fn ($l) => __($l), $labels), JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) !!}, LOGOS=@json($logos), state=@json($state);
  var CSRF=document.querySelector('meta[name="csrf-token"]').content;
  var main=document.getElementById('fdr-main'), bar=document.getElementById('fdr-bar'), quick=document.getElementById('fdr-quick'), root=document.getElementById('fdr');
  var ORDER=['make','year','model','drive','loc','engine','trans','trans_speeds','trans_code','cab','bed','chassis','doors','trim','rear_wheels','transfer_case','differential'];
  var SHORT={loc:TT("Loc"),trans_speeds:TT("Speeds"),trans_code:TT("Trans code"),chassis:TT("Wheelbase"),bed:TT("Bed"),rear_wheels:TT("Rear wheels")};
  var PH='<svg class="fdr-ph" viewBox="0 0 64 40" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linejoin="round"><circle cx="9" cy="20" r="6"/><circle cx="55" cy="20" r="6"/><path d="M15 17h34v6H15z"/></svg>';
  function esc(s){return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });}
  function money(n){return '$'+Number(n).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function slug(s){return String(s).toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');}
  function logo(m){var s=slug(m);return LOGOS.indexOf(s)>-1?'/media/160/'+btoa('/img/makes/'+s+'.png').replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'')+'.webp':null;}
  function lab(k){return SHORT[k]||LABELS[k]||k;}
  function full(k){return LABELS[k]||lab(k);}
  // the part page confirms "Fits your …" from these, when its own fitment rows prove it
  function carry(c){var q=new URLSearchParams();['make','year','model','drive','loc','engine'].forEach(function(k){if(c&&c[k])q.set(k,c[k]);});var s=q.toString();return s?'?'+s:'';}   // in a sentence: "Choose the shaft location", never "Choose the loc"
  function qs(c){var p=new URLSearchParams();ORDER.forEach(function(k){if(c[k]!==undefined&&c[k]!=='')p.set(k,c[k]);});return p.toString();}
  function keys(c){return ORDER.filter(function(k){return c[k]!==undefined;});}

  var early=false;   // "Show all now" while a step is still open; reset by every answer
  function load(chosen,push){
    early=false; root.classList.add('fdr-busy'); var q=qs(chosen);
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
    var count=state.results?state.results.length:(state.candidate_count||0);
    var html=n?question(n,steps.indexOf(n.facet)+1,steps.length,count):'';
    if(n&&count){
      // a step is still open: the count and a way to look early, not a table full of "please select" rows
      html+='<div class="fdr-wait"><div><b>'+TT(":n driveshafts fit so far").replace(':n',count)+'</b><span>'+TT("Answer the question above to see your exact driveshaft.")+'</span></div>'
        +(state.results&&!early?'<button type="button" class="btn btn-ghost btn-sm" id="fdr-early">'+TT("Show all :n now").replace(':n',count)+'</button>':'')+'</div>';
      if(state.results&&early)html+=results(state.results,c,true);
    }
    else if(state.results)html+=results(state.results,c,false);
    else if(!n)html+=results([],c,false);
    main.innerHTML=html;
  }
  function question(n,idx,total,count){
    var isMake=n.facet==='make', longest=n.options.reduce(function(a,o){return Math.max(a,String(o).length);},0);
    var tiles=n.options.map(function(o){
      if(isMake){var l=logo(o);return '<button type="button" class="fdr-tile is-make" data-facet="make" data-val="'+esc(o)+'">'+(l?'<img src="'+l+'" alt="" loading="lazy">':'<i class="mono">'+TT("UDS")+'</i>')+'<span>'+esc(o)+'</span></button>';}
      return '<button type="button" class="fdr-tile'+(longest>12?' is-text':'')+'" data-facet="'+esc(n.facet)+'" data-val="'+esc(o)+'"><b>'+esc(o)+'</b></button>';
    }).join('');
    var head='<div class="fdr-q"><p class="fdr-q__n">'+TT("Step :n").replace(':n',idx)+'</p><h2>'+esc(TT("Choose the :item").replace(':item',full(n.facet).toLowerCase()))+'</h2></div>';
    return head+'<div class="fdr-tiles'+(isMake?' is-makes':(n.facet==='year'?' is-years':''))+(n.options.length<5&&!isMake?' few':'')+'">'+tiles+'</div>';
  }
  function results(rows,c,pending,title){
    if(!rows.length)return '<div class="fdr-card"><h2 style="margin-top:0">'+TT("No driveshaft found")+'</h2><p>'+TT("Nothing in the catalog matches that combination. Change a selection, or call (813) 524-6491 and we will build one to your measurements.")+'</p><a class="btn btn-primary" href="/online-builder">'+TT("Open the online builder")+'</a></div>';
    var l=c.make?logo(c.make):null;
    var head=title?'<div class="fdr-veh"><div><h2>'+esc(title)+'</h2></div></div>':'<div class="fdr-veh">'+(l?'<img src="'+l+'" alt="">':'')+'<div><h2>'+esc([c.make,c.model].filter(Boolean).join(' '))+'</h2><p>'+esc([c.year,c.drive].filter(Boolean).join(' · '))+'</p></div></div>';
    var open=state.next?state.next.facet:null;
    var missing=pending&&open?'<p class="fdr-missing"><b>'+TT("Missing step:")+' '+esc(lab(open))+'</b> '+TT("Choose it above to see which shaft is yours and add it to the cart.")+'</p>':'';
    var note=(!pending&&!title&&rows.length>1)?'<p class="fdr-note">'+TT("More than one driveshaft fits these answers. Match the ID code or center bearing number on your current shaft against the notes below, or call us and we will confirm it.")+'</p>':'';
    var nf=state.next?state.next.facet:null;
    var body=rows.map(function(r){
      var f=r.facets||{}, v=function(k){return f[k]?f[k].values.join(' / '):'-';};
      var extra=Object.keys(f).filter(function(k){return ['drive','loc'].indexOf(k)<0;}).map(function(k){return lab(k)+': '+f[k].values.join(' / ');});
      if(r.weld_to_weld&&r.weld_to_weld.length)extra.push(TT("Weld to weld: ")+r.weld_to_weld.join(' / '));
      var price=r.can_buy?((r.price_list?'<span class="fdr-was">'+money(r.price_list)+'</span>':'')+'<span class="fdr-now">'+money(r.price)+'</span>'+(r.price_list?'<span class="fdr-save">'+TT("(Save")+' '+money(r.price_list-r.price)+')</span>':'')+(r.core_price?'<span class="fdr-core">+ '+money(r.core_price)+' '+TT("core")+'</span>':'')):'<span class="fdr-call">'+TT("Call for price")+'</span>';
      var add=pending&&!title?'<span class="fdr-sel">'+esc(TT("Choose :item first").replace(':item',full(nf).toLowerCase()))+'</span>':(r.can_buy?'<span class="p-add"><button class="p-plus" data-add="'+esc(r.part_number)+'" aria-label="'+TT("Add :sku to cart").replace(':sku',esc(r.sku||r.part_number))+'" title="'+TT("Add to cart")+'">+<span class="fdr-addtxt">'+TT("Add to cart")+'</span></button></span>':'<button type="button" class="btn btn-line btn-sm" data-quote="'+esc(r.sku||r.part_number)+'" data-pn="'+esc(r.part_number)+'" data-vehicle="'+esc([c.year,c.make,c.model,c.drive].filter(Boolean).join(' '))+'">'+TT("Get a quote")+'</button>');
      var fits=r.fits&&r.fits.length?'<ul class="fdr-fits">'+r.fits.map(function(x){return '<li>'+esc(x)+'</li>';}).join('')+'</ul>':'';
      return '<tr><td data-label="Add">'+add+'</td><td data-label="Pic">'+(r.image?'<img class="fdr-pic" width="70" height="52" loading="lazy" src="'+esc(r.image)+'" data-full="'+esc(r.image)+'" alt="'+esc(r.sku||r.part_number)+'">':PH)+'</td><td data-label="Price">'+price+'</td>'+
        '<td data-label="Part number"><a class="fdr-pn" href="/catalog/driveshafts/part/'+encodeURIComponent(r.sku||r.part_number)+carry(c)+'" title="'+TT("Open part page")+'">'+esc(r.sku||r.part_number)+' &#8599;</a><span class="fdr-desc">'+esc(r.description||TT("Complete driveshaft"))+(r.condition&&r.condition!=='new'?' ('+esc(r.condition)+')':'')+'</span>'+fits+'</td>'+
        // data-label keys the layout; data-th is what a phone card shows ("Position", not "LOC"); empty notes are left out there
        (title?'':'<td data-label="Model" data-th="'+esc(TT("Model"))+'">'+esc(c.model||'-')+'</td>')+
        '<td data-label="Drive" data-th="'+esc(TT("Drive"))+'">'+esc(v('drive'))+'</td><td data-label="Loc" data-th="'+esc(TT("Position"))+'">'+esc(v('loc'))+'</td>'+
        '<td data-label="Ctr. brg" data-th="'+esc(TT("Center bearing"))+'"><span class="fdr-id">'+esc((r.c_brg||[]).join(' & ')||'-')+'</span></td>'+
        '<td data-label="Notes" data-th="'+esc(TT("Notes"))+'" class="l'+((r.notes||[]).length||extra.length?'':' is-empty')+'"><span class="fdr-id">'+esc((r.notes||[]).join(' | ')||'-')+'</span>'+(extra.length?'<span class="fdr-more">'+esc(extra.join(' · '))+'</span>':'')+'</td></tr>';
    }).join('');
    var th=function(k,t){return '<th'+(c[k]!==undefined?' class="hl"':'')+'>'+t+(c[k]!==undefined?'<small>['+esc(c[k])+']</small>':'')+'</th>';};
    return '<div class="fdr-card">'+head+missing+note+'<div class="ptbl-wrap"><table class="ptbl" id="fdr-table"><thead><tr><th>'+TT("Add")+'</th><th>'+TT("Pic")+'</th><th>'+TT("Price")+'</th><th>'+TT("Part number")+'</th>'+(title?'':th('model',TT("Model")))+th('drive',TT("Drive"))+th('loc',TT("Position"))+'<th>'+TT("Center bearing")+'</th><th class="l">'+TT("Notes")+'</th></tr></thead><tbody>'+body+'</tbody></table></div></div>';
  }
  document.addEventListener('click',function(e){
    var o=e.target.closest('.fdr-tile'); if(o){var c=Object.assign({},state.chosen||{});c[o.getAttribute('data-facet')]=o.getAttribute('data-val');load(c,true);window.scrollTo({top:root.getBoundingClientRect().top+scrollY-100,behavior:'smooth'});return;}
    var d=e.target.closest('[data-drop]'); if(d){dropFrom(d.getAttribute('data-drop'));return;}
    if(e.target.closest('#fdr-back')){var ks=keys(state.chosen||{});if(ks.length)dropFrom(ks[ks.length-1]);return;}
    if(e.target.closest('#fdr-clear')){load({},true);return;}
    if(e.target.closest('#fdr-early')){early=true;render();return;}
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
</script>
<script src="/js/alerts.js?v={{ @filemtime(public_path('js/alerts.js')) }}" defer></script>   {{-- quote modal for unpriced driveshafts (recovery agent) --}}
@endsection
