{{--
  Driveshaft-builder configuration, rendered identically wherever it appears:
  cart, checkout summary, order confirmation, invoice and the admin order screen.
  Rows come from App\Support\BuildConfig so there is one source of truth.

  $config  array   the cart line / order item meta['config']
  $open    bool    render expanded instead of behind a disclosure (default false)
  $compact bool    tighter type, for the checkout side panel (default false)
  $popup   bool    a button that opens the rows in a dialog (the cart: the list no longer stretches the line)
--}}
@php $bcRows = \App\Support\BuildConfig::rows($config ?? []); @endphp
@if(count($bcRows) && ($popup ?? false))
  @php $bcId = 'bcfg-' . substr(md5(json_encode($config)), 0, 8); @endphp
  <button type="button" class="bcfg-open" data-bcfg-open="{{ $bcId }}" aria-haspopup="dialog"><x-icon name="sliders"/> {{ __('Build configuration') }} <span>{{ count($bcRows) }}</span></button>
  <dialog class="bcfg-dlg" id="{{ $bcId }}" aria-labelledby="{{ $bcId }}-h">
    <div class="bcfg-dlg__head"><h2 id="{{ $bcId }}-h">{{ __('Build configuration') }}</h2><button type="button" class="bcfg-dlg__x" data-bcfg-close aria-label="{{ __('Close') }}"><x-icon name="x"/></button></div>
    <dl class="bcfg__list">
      @foreach($bcRows as $r)
        <div class="bcfg__row"><dt>{{ __($r['label']) }}</dt><dd>{{ $r['value'] }}</dd></div>
      @endforeach
    </dl>
  </dialog>
  @once
  <script>
  /* build configuration dialogs: open from the line button, close with the x, Esc or a click on the backdrop */
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-bcfg-open]'); if (o) { var d = document.getElementById(o.getAttribute('data-bcfg-open')); if (d && d.showModal) d.showModal(); return; }
    if (e.target.closest('[data-bcfg-close]') || (e.target.matches && e.target.matches('dialog.bcfg-dlg'))) { var x = e.target.closest('dialog.bcfg-dlg'); if (x) x.close(); }
  });
  </script>
  @endonce
@elseif(count($bcRows))
  <details class="bcfg{{ ($compact ?? false) ? ' bcfg--compact' : '' }}" @if($open ?? false) open @endif>
    <summary><x-icon name="sliders"/> {{ __('Build configuration') }} <span>{{ count($bcRows) }}</span></summary>
    <dl class="bcfg__list">
      @foreach($bcRows as $r)
        <div class="bcfg__row"><dt>{{ __($r['label']) }}</dt><dd>{{ $r['value'] }}</dd></div>
      @endforeach
    </dl>
  </details>
@endif
