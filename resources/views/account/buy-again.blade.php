@extends('account.layout')
@section('active','buy-again')
@section('atitle','Buy again')
@section('acct')
<div class="acct-h"><div><h2 class="t">{{ __('Buy again') }}</h2><p>{{ __('Every part you have ordered from us, newest first — set a quantity and add it straight to your cart at today\'s price.') }}</p></div></div>

<div class="apanel">
  <div class="apanel-h"><h2><span class="hi"><x-icon name="cart"/></span> {{ __('Your parts') }}</h2>@if(count($items))<span class="ba-count">{{ trans_choice(':count product|:count products', count($items)) }}</span>@endif</div>
  <div class="apanel-b">
    @if(count($items))
    <div class="ba-list">
      @foreach($items as $it)
        @php
          $stock = $it['stock_status'];
          $pill = $stock === 'instock' ? ['paid', __('In stock')] : ($stock === 'onbackorder' ? ['pending', __('Backorder')] : ['cancelled', __('Call')]);
        @endphp
        <div class="mini-row ba-row">
          <a class="ba-img" href="{{ $it['url'] }}" tabindex="-1" aria-hidden="true">@if($it['image'])<img src="{{ $it['image'] }}" alt="" loading="lazy" width="64" height="64">@else<x-icon name="package"/>@endif</a>
          <div class="mt ba-mt">
            <a class="ba-sku" href="{{ $it['url'] }}">{{ $it['sku'] }}</a>
            <b><a href="{{ $it['url'] }}">{{ \Illuminate\Support\Str::limit($it['name'], 90) }}</a></b>
            <span>{{ __('Last ordered :date · qty :qty', ['date' => \Carbon\Carbon::parse($it['last'])->isoFormat('ll'), 'qty' => $it['qty']]) }}@if($it['times'] > 1) · {{ __(':n orders', ['n' => $it['times']]) }} @endif</span>
          </div>
          <span class="pill pill-{{ $pill[0] }} ba-stock">{{ $pill[1] }}</span>
          <span class="mprice ba-price">@if($it['price'] !== null)${{ number_format($it['price'], 2) }}@else<small>{{ __('Call for price') }}</small>@endif</span>
          <form class="ba-add" data-ba-add data-kind="{{ $it['kind'] }}" data-id="{{ $it['id'] }}" data-pn="{{ $it['pn'] }}" data-sku="{{ $it['sku'] }}">
            <input type="number" class="ba-qty" name="qty" value="{{ max(1, (int) $it['qty']) }}" min="1" max="{{ $it['kind'] === 'driveshaft' ? 20 : 999 }}" inputmode="numeric" aria-label="{{ __('Quantity for :sku', ['sku' => $it['sku']]) }}" @if($it['price'] === null) disabled @endif>
            <button type="submit" class="mbtn pri" @if($it['price'] === null) disabled @endif><x-icon name="cart"/> {{ __('Add to cart') }}</button>
          </form>
        </div>
      @endforeach
    </div>
    @else
    <div class="aempty"><span class="ei"><x-icon name="package"/></span>{!! __('No past purchases on <b>:email</b> yet.', ['email' => e($user->email)]) !!}<br><a href="/shop">{{ __('Browse the catalog') }} <x-icon name="arrow-right"/></a></div>
    @endif
  </div>
</div>

@if(count($pastOrders))
<div class="apanel">
  <div class="apanel-h"><h2><span class="hi"><x-icon name="clock"/></span> {{ __('Reorder a past order') }}</h2></div>
  <p class="apanel-sub ba-sub">{{ __('Adds every part from that order to your cart at today\'s prices. Built-to-order driveshafts from the designer are not included.') }}</p>
  <div class="apanel-b" style="padding-top:14px">
    <div class="ba-list">
    @foreach($pastOrders as $o)
      <div class="mini-row ba-order">
        <div class="mt"><b>{{ __('Order :n', ['n' => $o['number']]) }}</b><span>{{ \Carbon\Carbon::parse($o['date'])->isoFormat('ll') }} · {{ trans_choice(':count part|:count parts', $o['count']) }}@if($o['source'] === 'wp') · {{ __('previous store') }} @endif</span></div>
        <span class="mprice">${{ number_format($o['total'], 2) }}</span>
        @if($o['url'])<a class="mbtn gho" href="{{ $o['url'] }}">{{ __('View') }}</a>@endif
        <button type="button" class="mbtn pri ba-reorder" data-ba-reorder="{{ $o['key'] }}">{{ __('Reorder all') }}</button>
      </div>
    @endforeach
    </div>
  </div>
</div>
@endif
<div class="ba-toast" id="ba-toast" hidden role="status"></div>
@endsection

@php $i18nT = ['viewCart'=>__('View cart'),'added'=>__('Added :qty × :sku to your cart.'),'addFail'=>__('Could not add :sku. Call (813) 524-6491.'),'reAdded'=>__('Added :n :parts to your cart.'),'part'=>__('part'),'parts'=>__('parts'),'gone'=>__(':n no longer available'),'reFail'=>__('Could not reorder. Call (813) 524-6491.')]; @endphp
@push('scripts')
<script>
(function(){
  var T=@json($i18nT);
  function fmt(s,o){return s.replace(/:(\w+)/g,function(m,k){return o[k]!==undefined?o[k]:m;});}
  var CSRF=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
  var toast=document.getElementById('ba-toast'),toastT;
  function say(msg,ok){
    if(!toast)return;
    // text only: msg can carry a SKU or a server error, never markup
    toast.textContent=msg;
    if(ok){var a=document.createElement('a');a.href='/cart';a.textContent=T.viewCart;toast.appendChild(document.createTextNode(' '));toast.appendChild(a);}
    toast.hidden=false; clearTimeout(toastT); toastT=setTimeout(function(){toast.hidden=true;},4200);
  }
  function bump(n){var b=document.querySelector('[data-cart-count]');if(b&&n!=null){b.textContent=n;b.hidden=false;}}
  function post(url,data){
    return fetch(url,{method:'POST',headers:{'X-CSRF-TOKEN':CSRF,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body:new URLSearchParams(data)})
      .then(function(r){return r.json().catch(function(){return {success:false};});});
  }
  function busy(btn,on){btn.disabled=on;btn.classList.toggle('is-busy',on);}
  document.querySelectorAll('[data-ba-add]').forEach(function(f){
    f.addEventListener('submit',function(e){
      e.preventDefault();
      var btn=f.querySelector('button'),qty=Math.max(1,parseInt(f.qty.value,10)||1),sku=f.dataset.sku;
      var url=f.dataset.kind==='driveshaft'?'/catalog/driveshafts/add':'/shop/catalog/add';
      var body=f.dataset.kind==='driveshaft'?{part_number:f.dataset.pn,qty:qty}:{id:f.dataset.id,qty:qty};
      // the same response as every Add button on the site (uds.js udsAdd), plus this page's own line
      window.udsAdd(btn,url,body,{quiet:true}).then(function(d){
        if(d.busy)return;
        if(d.ok)say(fmt(T.added,{qty:qty,sku:sku}),true);
        else say(d.error||d.message||fmt(T.addFail,{sku:sku}),false);
      });
    });
  });
  document.querySelectorAll('[data-ba-reorder]').forEach(function(btn){
    btn.addEventListener('click',function(){
      busy(btn,true);
      post('/account/buy-again/reorder',{order:btn.dataset.baReorder}).then(function(d){
        busy(btn,false);
        if(d&&d.success){bump(d.count);say(fmt(T.reAdded,{n:d.added,parts:d.added===1?T.part:T.parts})+(d.skipped?' ('+fmt(T.gone,{n:d.skipped})+')':''),true);}
        else say((d&&d.error)||T.reFail,false);
      }).catch(function(){busy(btn,false);say(T.reFail,false);});
    });
  });
})();
</script>
@endpush
