@extends('emails.recovery.layout')
@section('body')
  <p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#1a7f4b;font-weight:700;">{{ __('Back in stock') }}</p>
  <h1 style="margin:0 0 14px;font-family:Poppins,Helvetica,Arial,sans-serif;font-size:24px;line-height:1.2;color:#0b1a33;">{{ __(':name is available again.', ['name' => $part['name']]) }}</h1>
  <p style="margin:0 0 22px;font-size:16px;line-height:1.6;color:#3a4557;">{{ $part['stock'] ? __('You asked us to let you know. It is on the shelf in Florida right now (:stock available), and stock on this part moves quickly.', ['stock' => stock_label($part['stock'])]) : __('You asked us to let you know. It is on the shelf in Florida right now, and stock on this part moves quickly.') }}</p>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e4e8f0;border-radius:12px;border-collapse:separate;">
    <tr>
      @if($part['image'])<td width="110" style="padding:14px;"><img src="{{ $part['image'] }}" width="96" height="96" alt="" style="display:block;width:96px;height:96px;object-fit:contain;border-radius:8px;background:#f6f8fc;"></td>@endif
      <td style="padding:14px;font-size:14px;line-height:1.5;">
        <b style="font-size:15px;color:#141b28;">{{ $part['name'] }}</b><br>
        @if($part['sku'])<span style="font-family:'IBM Plex Mono',Menlo,monospace;font-size:12px;color:#5c6879;">{{ __('Part # :sku', ['sku' => $part['sku']]) }}</span><br>@endif
        @if($part['vehicle'])<span style="color:#5c6879;">{{ __('For: :vehicle', ['vehicle' => $part['vehicle']]) }}</span><br>@endif
        @if($part['price'] !== null)<span style="font-size:20px;font-weight:700;color:#0b1a33;">${{ number_format($part['price'], 2) }}</span>@endif
      </td>
    </tr>
  </table>
  <p style="margin:26px 0 24px;text-align:center;"><a href="{{ $part['url'] }}" style="display:inline-block;background:#1246ab;color:#ffffff;text-decoration:none;font-family:Poppins,Helvetica,Arial,sans-serif;font-weight:600;font-size:16px;padding:15px 30px;border-radius:999px;">{{ __('View and order') }} &rarr;</a></p>
  <p style="margin:0;font-size:14px;line-height:1.6;color:#3a4557;">{!! __('Orders placed before 2pm EST on a weekday ship the same day, or pick it up at the counter (:hours). Questions: :phone.', ['hours' => e(__(config('uds.pickup.hours'))), 'phone' => '<a href="tel:+18135246491" style="color:#1246ab;font-weight:600;">(813) 524-6491</a>']) !!}</p>
@endsection
@section('footer')
  {{ __('One-time notice for the part you subscribed to on universaldriveshafts.com. No newsletter, nothing else follows.') }}
@endsection
