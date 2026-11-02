@extends('emails.layout')
@section('body')
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border:1px solid #e4e8f0;border-radius:12px;"><tr>
    <td style="padding:16px 18px;">
      <p style="margin:0 0 4px;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#5c6879;font-weight:700;">{{ $carrier }}@if($service) · {{ $service }}@endif</p>
      <p style="margin:0;font-family:Menlo,Consolas,monospace;font-size:20px;color:#0b1a33;font-weight:700;">{{ $tracking }}</p>
    </td>
    <td align="right" style="padding:16px 18px;vertical-align:middle;">@include('emails.parts.button', ['label' => __('Track package'), 'url' => $trackUrl, 'margin' => '0'])</td>
  </tr></table>
  <p style="margin:0 0 14px;">{!! __('It usually takes a few hours for the carrier to show the first scan. The :link updates as the shipment moves and when it is delivered.', ['link' => '<a href="' . e($statusUrl) . '" style="color:#1246ab;font-weight:600;">' . e(__('order status page')) . '</a>']) !!}</p>
  @if(!empty($items)) @include('emails.parts.items', ['items' => $items]) @endif
  <p style="margin:18px 0 0;color:#5c6879;font-size:14px;">{{ __('Questions? Reply to this e-mail or call (813) 524-6491.') }}</p>
@endsection
