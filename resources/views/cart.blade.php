@extends('layouts.app')
@section('robots','noindex,follow')
@section('title','Your cart | Universal Driveshafts')
@section('desc','Review the parts in your Universal Driveshafts cart before checkout.')

@push('scripts')
<script src="{{ asset('js/cart.js') }}?v={{ @filemtime(public_path('js/cart.js')) }}" defer></script>
@include('shop.partials.js-i18n')
<script src="{{ asset('js/catalog.js') }}?v={{ @filemtime(public_path('js/catalog.js')) }}" defer></script>
<script>
(function(){
  var f=document.getElementById('cart-quickadd'); if(!f) return;
  var sku=f.querySelector('#qa-sku'), qty=f.querySelector('#qa-qty'), btn=f.querySelector('button'), msg=f.querySelector('.cartadd__msg');
  var csrf=(document.querySelector('meta[name="csrf-token"]')||{}).content||'';
  function say(t,bad){msg.textContent=t;msg.hidden=!t;msg.classList.toggle('is-bad',!!bad);}
  f.addEventListener('submit',function(e){
    e.preventDefault();
    var v=sku.value.trim(); if(!v){sku.focus();return;}
    btn.disabled=true; say('');
    fetch('/quick-order/add',{method:'POST',headers:{'X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({lines:JSON.stringify([{sku:v,qty:Math.max(1,parseInt(qty.value,10)||1)}])})})
      .then(function(r){return r.json().catch(function(){return {success:false};});})
      .then(function(d){
        if(d.success){location.reload();return;}
        btn.disabled=false; say(d.error||udsT('Part not found.'),true); sku.select();
      }).catch(function(){btn.disabled=false;say(udsT('Network error. Please try again.'),true);});
  });
})();
</script>
@endpush

@section('content')
<div class="wrap">
  <nav class="ucrumb" aria-label="{!! __('Breadcrumb') !!}">
    <a href="/">{!! __('Home') !!}</a><x-icon name="caret-right"/><span aria-current="page">{!! __('Cart') !!}</span>
  </nav>
</div>

<section class="section" style="padding-top:1rem">
  <div class="wrap">
    <div class="s-head" style="margin-bottom:1.25rem"><h1>{!! __('Your cart') !!}</h1></div>

    {{-- quick add by part number, like a parts counter (PTI's cart): type the number, the quantity, Add item --}}
    <form class="cartadd" id="cart-quickadd" autocomplete="off" novalidate>
      <label class="cartadd__lbl" for="qa-sku">{!! __('Add by part number') !!}</label>
      <input class="cartadd__sku" id="qa-sku" name="sku" type="text" placeholder="{!! __('Part number, e.g. 5-1310XU') !!}" spellcheck="false" required>
      <input class="cartadd__qty" id="qa-qty" name="qty" type="number" min="1" max="999" value="1" inputmode="numeric" aria-label="{!! __('Quantity') !!}">
      <button type="submit" class="btn btn-primary">{!! __('Add item') !!}</button>
      <a class="cartadd__more" href="/quick-order">{!! __('Add many at once') !!}</a>
      <p class="cartadd__msg" role="status" hidden></p>
    </form>

    @if(empty($items))
      <div class="cartempty">
        <x-icon name="cart"/>
        <h2>{!! __('Nothing in the cart yet') !!}</h2>
        <p>{!! __('Search a part number, browse the catalog, or build a driveshaft to your own measurements.') !!}</p>
        <div class="cartempty__cta">
          <a href="/shop" class="btn btn-primary">{!! __('Browse the catalog') !!} <x-icon name="arrow-right"/></a>
          <a href="/online-builder" class="btn btn-ghost">{!! __('Open the online builder') !!}</a>
        </div>
      </div>
    @else
      @php $freeBase = $freeBase ?? $subtotal; $remaining = max(0, $freeMin - $freeBase); $pct = $freeMin > 0 ? min(100, round($freeBase / $freeMin * 100)) : 100; @endphp
      <div class="cartpage" data-cart>
        <div class="cartmain">
        <div class="cartlist">
          @foreach($items as $i)
            @php
              // the part's own page: catalog parts and vehicle driveshafts have one, a custom build does not
              $lineUrl = !$i['sku'] ? null : match ($i['type'] ?? 'product') {
                'product' => '/parts/' . rawurlencode($i['sku']),
                'driveshaft' => '/catalog/driveshafts/part/' . rawurlencode($i['sku']),
                default => null,
              };
              // hover shows the whole photo, larger (the media cache serves the same picture at 640px)
              $zoom = !empty($i['image']) ? preg_replace('#/media/\d+/#', '/media/640/', $i['image']) : '';
            @endphp
            <article class="cartline" data-key="{{ $i['key'] }}" data-price="{{ $i['price'] }}">
              <span class="cartline__media">
                @if(!empty($i['image']))<img class="p-pic" src="{{ \App\Services\CartService::thumb($i['image']) }}" data-zoom="{{ $zoom }}" alt="{{ $i['sku'] }}" width="88" height="88">@else<x-icon name="package"/>@endif
              </span>
              <div class="cartline__info">
                @if($i['sku'])<span class="cartline__sku">{{ $i['sku'] }}</span>@endif
                <p class="cartline__name">@if($lineUrl)<a href="{{ $lineUrl }}">{{ \Illuminate\Support\Str::limit($i['name'],90) }}</a>@else{{ \Illuminate\Support\Str::limit($i['name'],90) }}@endif</p>
                @if(!empty($i['meta']['summary']))<p class="cartline__cfg">{{ $i['meta']['summary'] }}</p>@endif
                <span class="cartline__unit">${{ number_format($i['price'],2) }} each</span>
                @if(!empty($i['meta']['config']))@include('partials.build-config', ['config' => $i['meta']['config'], 'popup' => true])@endif
              </div>
              <div class="cartline__right">
                <span class="cartline__total" data-line-total>${{ number_format($i['price']*$i['qty'],2) }}</span>
                <div class="uqty">
                  <button type="button" data-q="-1" aria-label="{!! __('Decrease quantity') !!}"><x-icon name="minus"/></button>
                  <input type="number" min="1" value="{{ $i['qty'] }}" data-qty aria-label="{{ __('Quantity for :sku', ['sku' => $i['sku'] ?: __('item')]) }}">
                  <button type="button" data-q="1" aria-label="{!! __('Increase quantity') !!}"><x-icon name="plus"/></button>
                </div>
                <button type="button" class="cartline__rm" data-remove><x-icon name="trash"/> {!! __('Remove') !!}</button>
              </div>
            </article>
          @endforeach
        </div>

          {{-- "Complete the job": parts that fit what is in the cart (App\Services\CompleteJob), below the lines box, not inside it --}}
          @if(!empty($job['items']) || !empty($job['build']))
          <section class="cjob" aria-labelledby="cjob-h">
            <h2 id="cjob-h">{{ __('Complete the job') }}</h2>
            <ul class="cjob__list">
              @foreach($job['items'] as $s)
              <li class="cjob__card">
                <a class="cjob__img" href="/parts/{{ rawurlencode($s['sku']) }}" tabindex="-1" aria-hidden="true">@if($s['image'])<img src="{{ $s['image'] }}" alt="" loading="lazy" width="80" height="80">@else<x-icon name="package"/>@endif</a>
                <div class="cjob__info">
                  <p class="cjob__why">{{ $s['reason'] }}</p>
                  <a class="cjob__name" href="/parts/{{ rawurlencode($s['sku']) }}"><b>{{ $s['sku'] }}</b> {{ \Illuminate\Support\Str::limit($s['name'], 64) }}</a>
                  <span class="cjob__price">${{ number_format($s['price'], 2) }}@if($s['unit'] === 'ft') <small>{{ __('per foot') }}</small>@endif</span>
                </div>
                @if($s['unit'] === 'ft')
                  <a class="btn btn-ghost btn-sm cjob__add" href="/parts/{{ rawurlencode($s['sku']) }}">{{ __('Choose length') }}</a>
                @else
                  <button type="button" class="btn btn-primary btn-sm cjob__add" data-cjob-add="{{ $s['id'] }}" aria-label="{{ __('Add :sku to cart', ['sku' => $s['sku']]) }}"><x-icon name="cart"/> {{ __('Add') }}</button>
                @endif
              </li>
              @endforeach
              @if(!empty($job['build']))
              <li class="cjob__card cjob__card--build">
                <span class="cjob__img"><x-icon name="wrench"/></span>
                <div class="cjob__info">
                  <p class="cjob__why">{{ __('Rather have the shaft built?') }}</p>
                  <span class="cjob__name">{{ __('We weld, balance and ship it, priced online to your length.') }}</span>
                </div>
                <a class="btn btn-ghost btn-sm cjob__add" href="/online-builder">{{ __('Price a custom driveshaft') }}</a>
              </li>
              @endif
            </ul>
          </section>
          <script>
          document.addEventListener('click',function(e){
            var b=e.target.closest('[data-cjob-add]');if(!b)return;
            b.disabled=true;
            fetch('/cart/add',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'X-Requested-With':'XMLHttpRequest','Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'},
              body:new URLSearchParams({product_id:b.getAttribute('data-cjob-add'),quantity:1})})
              .then(function(r){return r.json();}).then(function(d){if(d&&d.success!==false){location.reload();}else{b.disabled=false;window.alert((d&&d.error)||'Error');}})
              .catch(function(){b.disabled=false;});
          });
          </script>
          @endif
        </div>{{-- /cartmain --}}

        <aside class="cartsum">
          <h2>{!! __('Order summary') !!}</h2>
          <div class="freebar {{ $remaining <= 0 ? 'is-done' : '' }}" data-freebar data-min="{{ $freeMin }}" data-tube="{{ round($subtotal - $freeBase, 2) }}">
            <div class="freebar__track"><span class="freebar__fill" style="width:{{ $pct }}%"></span></div>
            <p data-freetext>
              @if($subtotal - $freeBase > 0.009) {{ __('Tubing and driveshafts ship at the carrier rate and do not qualify for free shipping.') }}
              @elseif($remaining <= 0) {{ __('Free US ground shipping applied.') }}
              @else {!! __(':amount more for free US ground shipping.', ['amount' => '<b>$'.number_format($remaining,2).'</b>']) !!} @endif
            </p>
          </div>
          <div class="cartsum__row"><span>{{ __('Subtotal') }} ({{ trans_choice(':count item|:count items', count($items)) }})</span><span data-subtotal>${{ number_format($subtotal,2) }}</span></div>
          <div class="cartsum__row"><span>{!! __('Shipping') !!}</span><span>{!! __('Calculated at checkout') !!}</span></div>
          <div class="cartsum__total"><span>{!! __('Total') !!}</span><span data-total>${{ number_format($subtotal,2) }}</span></div>
          <a href="/checkout" class="btn btn-primary btn-block btn-lg">{!! __('Checkout') !!} <x-icon name="arrow-right"/></a>
          @include('partials.ship-countdown', ['class' => 'shipcd--cart'])
          @php
            $arrItems = collect($items)->filter(fn ($i) => ($i['type'] ?? 'product') === 'product' && !empty($i['wp_product_id']))->map(fn ($i) => (int) $i['wp_product_id'] . ':' . (int) $i['qty'])->implode(',');
            $arrBuilt = collect($items)->contains(fn ($i) => in_array($i['type'] ?? '', ['custom_build', 'premade_build'], true));
          @endphp
          @if($arrItems !== '' || $arrBuilt)<p class="arrive arrive--cart" data-arrive data-items="{{ $arrItems }}" @if($arrBuilt) data-built @endif data-zip="{{ \App\Support\Arrival::savedZip(auth()->user()) }}" aria-live="polite"></p>@endif
          <script src="{{ asset('js/arrival.js') }}?v={{ @filemtime(public_path('js/arrival.js')) }}" defer></script>
          @include('partials.affirm-msg', ['amount' => $subtotal, 'class' => 'affirm-msg--cart'])
          <a href="/shop" class="textlink" style="justify-content:center"><x-icon name="arrow-left"/> {!! __('Keep shopping') !!}</a>
          <p class="cartsum__note">{!! __('Shipping is calculated at checkout. Prices exclude any core charge, which is quoted separately.') !!}</p>
        </aside>
      </div>
    @endif
  </div>
</section>
<div class="img-pop" id="img-pop" aria-hidden="true"><img alt=""></div>
@endsection
@if($items)
@push('scripts')
@include('partials.track', ['ev' => 'view_cart', 'p' => \App\Support\Funnel::cart(array_values($items))])
@endpush
@endif
