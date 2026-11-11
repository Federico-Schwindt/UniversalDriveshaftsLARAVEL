@extends('emails.recovery.layout')
@php
  $btn = 'display:inline-block;background:#1246ab;color:#ffffff;text-decoration:none;font-family:Poppins,Helvetica,Arial,sans-serif;font-weight:600;font-size:16px;padding:15px 30px;border-radius:999px;';
@endphp
@section('body')
  <p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#1246ab;font-weight:700;">{{ $n === 1 ? __('Your cart is waiting') : __('Still thinking it over?') }}</p>
  <h1 style="margin:0 0 14px;font-family:Poppins,Helvetica,Arial,sans-serif;font-size:24px;line-height:1.2;color:#0b1a33;">{{ $name === 'there' ? __('Hi there, your driveline parts are saved.') : __('Hi :name, your driveline parts are saved.', ['name' => $name]) }}</h1>
  <p style="margin:0 0 22px;font-size:16px;line-height:1.6;color:#3a4557;">
    @if($n === 1)
      {{ $count === 1 ? __('You left a part in your cart at Universal Driveshafts. We kept everything exactly as you configured it, so you can pick up where you left off in one click.') : __('You left :count parts in your cart at Universal Driveshafts. We kept everything exactly as you configured it, so you can pick up where you left off in one click.', ['count' => $count]) }}
    @else
      {{ __('Your cart is still here. Everything below ships from our shop in Florida, and if you would rather pick it up at the counter, choose local pickup at checkout.') }}
    @endif
  </p>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e8f0;border-radius:12px;border-collapse:separate;overflow:hidden;">
    @foreach($items as $i)
    <tr>
      <td width="76" style="padding:12px 0 12px 12px;vertical-align:top;">
        @if($i['image'])<img src="{{ $i['image'] }}" width="64" height="64" alt="" style="display:block;width:64px;height:64px;object-fit:contain;border-radius:8px;background:#f6f8fc;border:1px solid #eef1f7;">@else<div style="width:64px;height:64px;border-radius:8px;background:#f6f8fc;border:1px solid #eef1f7;"></div>@endif
      </td>
      <td style="padding:12px;vertical-align:top;font-size:14px;line-height:1.45;border-bottom:1px solid #eef1f7;">
        <b style="color:#141b28;">{{ $i['name'] }}</b><br>
        @if($i['sku'])<span style="font-family:'IBM Plex Mono',Menlo,monospace;font-size:12px;color:#5c6879;">{{ $i['sku'] }}</span> &middot; @endif<span style="color:#5c6879;">{{ __('Qty :n', ['n' => $i['qty']]) }}</span>
        @if($i['avail'])<br><span style="color:#1a7f4b;font-weight:600;font-size:13px;">&#10003; {{ __($i['avail']) }}</span>@endif
      </td>
      <td align="right" style="padding:12px 12px 12px 0;vertical-align:top;white-space:nowrap;font-size:15px;font-weight:600;border-bottom:1px solid #eef1f7;">${{ number_format($i['line'], 2) }}</td>
    </tr>
    @endforeach
    <tr>
      <td colspan="2" align="right" style="padding:12px;font-size:14px;color:#3a4557;">{{ __('Subtotal') }}</td>
      <td align="right" style="padding:12px 12px 12px 0;font-size:18px;font-weight:700;color:#0b1a33;white-space:nowrap;">${{ number_format($subtotal, 2) }}</td>
    </tr>
  </table>
  <p style="margin:6px 0 0;font-size:12px;color:#5c6879;">{{ __('Prices are what you saw when the cart was saved; the cart re-checks them when it opens. Shipping is calculated at checkout.') }}</p>
  <p style="margin:26px 0 10px;text-align:center;"><a href="{{ $restoreUrl }}" style="{{ $btn }}">{{ __('Return to your cart') }} &rarr;</a></p>
  <p style="margin:0 0 24px;text-align:center;font-size:13px;color:#5c6879;">{{ __('The link rebuilds your cart on any device.') }}</p>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fc;border-radius:12px;">
    <tr><td style="padding:16px 18px;font-size:14px;line-height:1.6;color:#3a4557;">
      {!! __('<b style="color:#0b1a33;">Ready to ship from Florida.</b> In-stock parts ordered before 2pm EST on a weekday ship the same day. Prefer to collect? Choose <b>local pickup</b> at checkout, :hours.', ['hours' => e(__(config('uds.pickup.hours')))]) !!}<br>
      {!! __('Questions about fit or a measurement? Call :phone and a driveline tech will check it before you order.', ['phone' => '<a href="tel:+18135246491" style="color:#1246ab;font-weight:600;">(813) 524-6491</a>']) !!}
    </td></tr>
  </table>
@endsection
@section('footer')
  {{-- CAN-SPAM: marketing mail carries the postal address (owner chose the shop address for this only) --}}
  {{ __('You are receiving this because an e-mail address was entered at checkout on universaldriveshafts.com.') }} <a href="{{ $unsubUrl }}" style="color:#5c6879;">{{ __('Stop cart reminders') }}</a>.<br>{{ config('uds.postal_address') }}
@endsection
