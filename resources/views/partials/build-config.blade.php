{{--
  Driveshaft-builder configuration, rendered identically wherever it appears:
  cart, checkout summary, order confirmation, invoice and the admin order screen.
  Rows come from App\Support\BuildConfig so there is one source of truth.

  $config  array   the cart line / order item meta['config']
  $open    bool    render expanded instead of behind a disclosure (default false)
  $compact bool    tighter type, for the checkout side panel (default false)
--}}
@php $bcRows = \App\Support\BuildConfig::rows($config ?? []); @endphp
@if(count($bcRows))
  <details class="bcfg{{ ($compact ?? false) ? ' bcfg--compact' : '' }}" @if($open ?? false) open @endif>
    <summary><x-icon name="sliders"/> {{ __('Build configuration') }} <span>{{ count($bcRows) }}</span></summary>
    <dl class="bcfg__list">
      @foreach($bcRows as $r)
        <div class="bcfg__row"><dt>{{ __($r['label']) }}</dt><dd>{{ $r['value'] }}</dd></div>
      @endforeach
    </dl>
  </details>
@endif
