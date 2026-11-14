@extends('emails.recovery.layout')
@section('body')
  <p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#1246ab;font-weight:700;">{{ __('Quote request received') }}</p>
  <h1 style="margin:0 0 14px;font-family:Poppins,Helvetica,Arial,sans-serif;font-size:24px;line-height:1.2;color:#0b1a33;">{{ __('We have your request for :sku.', ['sku' => $q['sku']]) }}</h1>
  <p style="margin:0 0 18px;font-size:16px;line-height:1.6;color:#3a4557;">{!! __($q['phone'] ? 'A driveline tech will price this driveshaft and reply to this e-mail or call you at :phone, usually within one business day. Need it sooner? Call :shop and mention part number :sku.' : 'A driveline tech will price this driveshaft and reply to this e-mail, usually within one business day. Need it sooner? Call :shop and mention part number :sku.', ['phone' => e($q['phone']), 'sku' => e($q['sku']), 'shop' => '<a href="tel:+18135246491" style="color:#1246ab;font-weight:600;">(813) 524-6491</a>']) !!}</p>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f8fc;border-radius:12px;">
    <tr><td style="padding:16px 18px;font-size:14px;line-height:1.7;color:#3a4557;">
      <b style="color:#0b1a33;">{{ __('Part number:') }}</b> <span style="font-family:'IBM Plex Mono',Menlo,monospace;">{{ $q['sku'] }}</span><br>
      @if($q['description'])<b style="color:#0b1a33;">{{ __('Description:') }}</b> {{ $q['description'] }}<br>@endif
    </td></tr>
  </table>
  @if($q['url'])<p style="margin:18px 0 0;font-size:14px;"><a href="{{ $q['url'] }}" style="color:#1246ab;">{{ __('Part page on our site') }} &rarr;</a></p>@endif
@endsection
@section('footer')
  {{ __('Sent once, in reply to the quote request you submitted on universaldriveshafts.com.') }}
@endsection
