@extends('emails.layout')
@section('body')
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;background:#0b1a33;border-radius:14px;"><tr><td align="center" style="padding:24px 20px;">
    <p style="margin:0 0 4px;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11.5px;letter-spacing:.14em;text-transform:uppercase;color:#8fa0c4;font-weight:700;">{{ __('Amount due · order :order', ['order' => $order->order_number]) }}</p>
    <p style="margin:0 0 14px;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:38px;line-height:1;color:#ffffff;font-weight:700;letter-spacing:-.02em;">${{ number_format($balance, 2) }}</p>
    @include('emails.parts.button', ['label' => __('Pay securely now'), 'url' => $payUrl, 'margin' => '0'])
    <p style="margin:12px 0 0;font-size:12.5px;color:#8fa0c4;">{{ __('All major cards · encrypted by Stripe') }}</p>
  </td></tr></table>
  @if(!empty($note))<p style="margin:0 0 14px;">{!! nl2br(e($note)) !!}</p>@endif
  @include('emails.parts.items', ['items' => $items])
  @include('emails.parts.totals', ['order' => $order, 'totalLabel' => __('Due now'), 'totalValue' => $balance])
  <p style="margin:0;color:#5c6879;font-size:14px;">{{ __('Prefer the phone? Call (813) 524-6491 with your order number. Link not working? Copy this address into your browser:') }}<br><a href="{{ $payUrl }}" style="color:#1246ab;word-break:break-all;">{{ $payUrl }}</a></p>
@endsection
