{{-- Live "order within 2 h 14 min, ships today" against the same-day cutoff (Settings → Shipping). After the cutoff,
     or on a weekend, it says the next ship date. Stock parts only: custom builds ship when they are finished. --}}
@php
  $cdCut = \App\Services\CartService::cutoffToday();
  $cdShip = \App\Services\CartService::shipDate();
  $cdNext = $cdShip->isToday() ? $cdShip->copy()->addWeekday() : $cdShip;
  // "ship tomorrow" / "ship on Monday, Nov 3"; es "salen mañana" / "salen el lunes 3 de noviembre" (the article lives in 'on :date', not in the sentence)
  $cdLoc = app()->getLocale();
  $cdFmt = ['es' => 'l j \\d\\e F', 'fr' => 'l j F', 'zh' => 'n月j日 l', 'ja' => 'n月j日 l'][$cdLoc] ?? 'l, M j';
  $cdDay = fn ($d) => $d->isTomorrow() ? __('tomorrow') : __('on :date', ['date' => $d->copy()->locale($cdLoc)->translatedFormat($cdFmt)]);
@endphp
<p class="shipcd {{ $class ?? '' }}" data-shipcd data-cut="{{ $cdCut ? $cdCut->getTimestamp() * 1000 : '' }}"
   data-today="{{ __('Order within :time and in-stock parts ship today.') }}"
   data-after="{{ __('In-stock parts ordered now ship :day.', ['day' => $cdDay($cdNext)]) }}">
  <x-icon name="clock"/>
  <span>@if($cdCut){{ __('Order within :time and in-stock parts ship today.', ['time' => '…']) }}@else{{ __('In-stock parts ordered now ship :day.', ['day' => $cdDay($cdShip)]) }}@endif</span>
</p>
@once
<script>
(function(){
  function fmt(ms){var m=Math.max(1,Math.ceil(ms/60000)),h=Math.floor(m/60);m=m%60;return (h?h+' h ':'')+m+' min';}
  function tick(){
    document.querySelectorAll('[data-shipcd]').forEach(function(el){
      var cut=Number(el.getAttribute('data-cut')||0),left=cut-Date.now(),s=el.querySelector('span');
      if(cut&&left>0){s.textContent=el.getAttribute('data-today').replace(':time',fmt(left));el.classList.add('is-today');}
      else{s.textContent=el.getAttribute('data-after');el.classList.remove('is-today');}
    });
  }
  tick();setInterval(tick,30000);
})();
</script>
@endonce
