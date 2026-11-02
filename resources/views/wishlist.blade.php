@extends('layouts.app')
@section('robots','noindex,follow')
@section('title','Your wishlist | Universal Driveshafts')
@section('desc','Your saved Universal Driveshafts parts and driveshafts.')
@section('content')
<section class="phero"><div class="wrap"><div class="crumb"><a href="/">{!! __('Home') !!}</a><span class="sep">/</span><span>{!! __('Wishlist') !!}</span></div><div class="accentline"></div>
<h1>{!! __('Your wishlist') !!}</h1></div></section>

<section class="section"><div class="wrap">
@if($items->isEmpty())
  <div class="cartempty">
    <p style="font-size:2.4rem;margin:0 0 8px"><x-icon name="heart"/></p>
    <p style="font-family:var(--font-h);font-weight:700;font-size:1.2rem;color:var(--ink);margin:0 0 8px">{!! __('Your wishlist is empty') !!}</p>
    <p style="margin:0 0 20px">{!! __('Tap the heart on any part to save it here.') !!}</p>
    <a href="/shop" class="btn btn-primary">{!! __('Browse the catalog') !!}</a>
  </div>
@else
  @php $ds = '<svg viewBox="0 0 64 40" width="26" height="26" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linejoin="round"><circle cx="12" cy="20" r="7"/><circle cx="52" cy="20" r="7"/><rect x="19" y="16.5" width="26" height="7" rx="2"/></svg>'; @endphp
  <div class="shop-count">{{ trans_choice(':count saved part|:count saved parts', count($items)) }}</div>
  <div class="ptbl-wrap"><table class="ptbl" id="wish-tbl">
    <thead><tr><th>{!! __('Add') !!}</th><th>{!! __('Pic') !!}</th><th>{!! __('Part #') !!}</th><th class="l">{!! __('Description') !!}</th><th>{!! __('Price') !!}</th></tr></thead>
    <tbody>
    @foreach($items as $it)
      <tr data-pid="{{ $it->wp_product_id }}">
        <td data-label="Add"><span class="p-add">
          <button class="p-rm" data-remove aria-label="{!! __('Remove from wishlist') !!}" title="{!! __('Remove from wishlist') !!}"><x-icon name="trash"/></button>
          <button class="p-plus" data-add aria-label="{!! __('Add to cart') !!}" title="{!! __('Add to cart') !!}"><x-icon name="plus"/></button>
        </span></td>
        <td data-label="Pic">@if($it->image)<img class="p-pic" src="{{ $it->image }}" alt="{{ $it->sku }}" loading="lazy">@else<span class="p-pic-ph">{!! $ds !!}</span>@endif</td>
        <td data-label="Part #">@if($it->sku)<a class="p-pn" href="/parts/{{ rawurlencode($it->sku) }}">{{ $it->sku }}<x-icon name="arrow-up-right"/></a>@else<span>, </span>@endif</td>
        <td data-label="Description" class="l">{{ $it->name }}</td>
        <td data-label="Price"><div class="p-price"><span class="now">${{ number_format($it->price,2) }}</span></div></td>
      </tr>
    @endforeach
    </tbody>
  </table></div>
@endif
</div></section>
<script>
(function(){
  var CSRF=document.querySelector('meta[name="csrf-token"]').content;
  var tbl=document.getElementById('wish-tbl');
  if(!tbl)return;
  function post(u,b){return fetch(u,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams(b)}).then(function(r){return r.json();});}
  tbl.addEventListener('click',function(e){
    var row=e.target.closest('tr[data-pid]'); if(!row)return;
    var pid=row.dataset.pid;
    var rm=e.target.closest('[data-remove]');
    if(rm){
      if(rm.disabled)return;
      rm.disabled=true;
      post('/wishlist/remove',{product_id:pid}).then(function(d){
        row.remove();
        var b=document.querySelector('[data-wish-count]');
        if(b&&d){b.textContent=d.count;b.hidden=d.count===0;}
        if(!tbl.querySelector('tbody tr')) location.reload(); // show the empty state
      });
      return;
    }
    var add=e.target.closest('[data-add]');
    if(add){
      if(add.disabled)return;
      add.disabled=true; add.classList.add('is-busy');
      post('/cart/add',{product_id:pid,quantity:1}).then(function(d){
        add.disabled=false; add.classList.remove('is-busy');
        if(d.data){
          var b=document.querySelector('[data-cart-count]'); if(b){b.textContent=d.data.count;b.hidden=false;}
          add.classList.add('is-done');
          setTimeout(function(){add.classList.remove('is-done');},1600);
        }
      }).catch(function(){add.disabled=false; add.classList.remove('is-busy');});
    }
  });
})();
</script>
@endsection
