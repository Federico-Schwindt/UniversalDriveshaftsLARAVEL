@extends('layouts.app')
@section('title','Premade driveshafts, ready to ship | Universal Driveshafts')
@section('desc','Ready-to-install premade driveshafts for common fitments, assembled, balanced and shipped the day you order from Florida. Find yours by vehicle.')
@section('content')

<section class="phero phero--rich"><div class="wrap">
  <nav class="ucrumb" aria-label="{{ __('Breadcrumb') }}"><a href="/">{{ __('Home') }}</a><x-icon name="caret-right"/><a href="/online-builder">{{ __('Driveshafts') }}</a><x-icon name="caret-right"/><span aria-current="page">{{ __('Premade driveshafts') }}</span></nav>
  <div class="phero__grid">
    <div>
      <span class="kicker">{{ __('Ready to ship') }}</span>
      <h1>{{ __('Premade driveshafts, assembled, balanced and out the door today') }}</h1>
      <p class="lead">{{ __('Common fitments built ahead of time so you are not waiting on a custom shaft. Every one is balanced in our shop and packed the day you order. Stock orders placed before 2pm ET ship the same day.') }}</p>
      <div class="phero__cta">
        <a href="/catalog/driveshafts" class="btn btn-primary btn-lg"><x-icon name="crosshair"/> {{ __('Find yours by vehicle') }}</a>
        <a href="/shop/complete-driveshafts/premade-driveshafts" class="btn btn-ghost btn-lg">{{ __('Shop all') }} {{ $total }} {{ __('premade') }}</a>
      </div>
      <ul class="phero__facts">
        <li><b class="u-mono">{{ $total }}</b><span>{{ __('premade shafts in stock') }}</span></li>
        <li><b class="u-mono">{{ $certified }}</b><span>{{ __('certified take-offs') }}</span></li>
        <li><b class="u-mono">{{ $shortCouple }}</b><span>{{ __('short couple shafts') }}</span></li>
      </ul>
    </div>
    <div class="phero__media"><picture><source srcset="{{ asset('img/home/premade-shaft.webp') }}" type="image/webp"><img src="{{ asset('img/home/premade-shaft.png') }}" width="1000" height="1000" alt="{{ __('A premade UDS driveshaft, assembled and balanced') }}"></picture></div>
  </div>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head s-head--between">
    <div><h2>{{ __('In stock right now') }}</h2><p>{{ __('Real inventory from the shelf, lowest price first. Prices include the balanced assembly.') }}</p></div>
    <a class="s-link" href="/shop/complete-driveshafts/premade-driveshafts">{{ __('All') }} {{ $total }} {{ __('premade driveshafts') }} <x-icon name="arrow-right"/></a>
  </div>
  <div class="pgrid">
    @foreach($products as $p)
      @php $nm = preg_replace('/^\s*'.preg_quote($p['sku'],'/').'\s*[\x{2013}\x{2014}|:-]*\s*/u', '', $p['name']) ?: $p['name']; @endphp
      <a class="pcard" href="/parts/{{ $p['sku'] }}">
        <span class="pcard__media">@if($p['image'])<img src="{{ $p['image'] }}" alt="{{ $nm }}" loading="lazy">@else<x-icon name="package"/>@endif</span>
        <span class="pcard__body">
          <span class="pcard__sku u-mono">{{ $p['sku'] }}</span>
          <span class="pcard__name">{{ \Illuminate\Support\Str::limit($nm, 80) }}</span>
          <span class="pcard__foot"><b class="u-mono u-nums">${{ number_format($p['price'], 2) }}</b>@if($p['stock_status'] === 'instock')<small class="pcard__ok">{{ __('In stock') }}</small>@else<small>{{ __('Call for stock') }}</small>@endif</span>
        </span>
      </a>
    @endforeach
  </div>
</div></section>

<section class="section section--tint"><div class="wrap">
  <div class="s-head"><h2>{{ __('How a premade shaft gets to you') }}</h2><p>{{ __('Three steps, no measuring if your vehicle is in the finder.') }}</p></div>
  <ol class="steps3">
    <li><span class="steps3__n u-mono">1</span><b>{{ __('Match it') }}</b><p>{{ __('Pick your make, year and model in the finder, or search the part number stamped on the old shaft.') }}</p></li>
    <li><span class="steps3__n u-mono">2</span><b>{{ __('We pull and check it') }}</b><p>{{ __('Each shaft is balanced before it goes on the shelf and checked again before it is packed.') }}</p></li>
    <li><span class="steps3__n u-mono">3</span><b>{{ __('It ships today') }}</b><p>{{ __('Stock orders before 2pm ET leave the same day. Free ground shipping over $') }}{{ config('uds.free_shipping_min') }}.</p></li>
  </ol>
</div></section>

<section class="section"><div class="wrap">
  <div class="s-head"><h2>{{ __('Premade, or built to your measurements?') }}</h2><p>{{ __('Premade covers the common fitments and ships the day you order. If the vehicle is modified, lifted or the length is non-standard, the builder prices the exact assembly from six measurements.') }}</p></div>
  <div class="vs2">
    <div class="vs2__col">
      <span class="kicker">{{ __('Premade') }}</span>
      <h3>{{ __('Factory-length replacement') }}</h3>
      <ul class="checks"><li>{{ __('Balanced, boxed, on the shelf') }}</li><li>{{ __('Ships same day on stock') }}</li><li>{{ __('Best price for an OEM fitment') }}</li><li>{{ __('Returnable if it does not fit') }}</li></ul>
      <a href="/shop/complete-driveshafts/premade-driveshafts" class="btn btn-primary">{{ __('Shop premade') }}</a>
    </div>
    <div class="vs2__col">
      <span class="kicker">{{ __('Built to spec') }}</span>
      <h3>{{ __('Custom length, your yokes') }}</h3>
      <ul class="checks"><li>{{ __('Pick duty, series, material and yokes') }}</li><li>{{ __('Enter six measurements, see the price live') }}</li><li>{{ __('One piece or two, steel or aluminum') }}</li><li>{{ __('Built and balanced in Florida') }}</li></ul>
      <a href="/online-builder" class="btn btn-ghost">{{ __('Open the online builder') }}</a>
    </div>
  </div>
</div></section>

<section class="section section--tint"><div class="wrap wrap--narrow">
  <div class="s-head"><h2>{{ __('Frequently asked questions') }}</h2></div>
  @include('partials.faq', ['faqs' => [
    ['How do I know if I need a driveshaft replacement?', 'Vibration that changes with speed, a clunk when you shift into gear, or play in the u-joints are the usual signs. If the tube is dented or the slip yoke is worn, replace the shaft rather than the joints.'],
    ['Is a premade driveshaft as good as a custom one?', 'For a stock vehicle, yes: it is the same assembly and balancing process, built ahead of time to the factory length. Custom makes sense when the vehicle is lifted, swapped or the length is non-standard.'],
    ['How long does shipping take?', 'Stock orders placed before 2pm ET ship the same day from Florida. Most of the U.S. sees the shaft in 2 to 5 business days by ground.'],
    ['What if it does not fit?', 'Unused premade shafts can be returned under the return policy. Measure twice: the part page lists the overall length and the yoke series.'],
  ]])
</div></section>

@endsection
