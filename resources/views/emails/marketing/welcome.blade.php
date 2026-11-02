@extends('emails.layout', ['marketing' => true])
@php $base = rtrim(config('app.url'), '/'); $cats = [[__('Complete driveshafts'), '/catalog/driveshafts', __('By year, make and model — ready to ship or built in Florida.')], [__('U-joints'), '/shop/driveshaft-components/u-joints', __('1310 to 1810, Spicer, Neapco and UDS.')], [__('Yokes & flanges'), '/shop/driveshaft-components', __('Slip, weld, end and flange yokes for every series.')], [__('Custom tubing'), '/shop/custom-cut-tubing', __('DOM steel and aluminum, cut to your length.')]]; @endphp
@section('body')
  <p style="margin:0 0 14px;">{{ __('You are on the list. Expect occasional e-mails about new driveline parts, shop tips and the odd deal — never more than a couple a month, and you can leave any time.') }}</p>
  @include('emails.parts.button', ['label' => __('Browse the catalog'), 'url' => $base . '/shop', 'margin' => '4px 0 22px'])
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="stack">
    @foreach(array_chunk($cats, 2) as $row)<tr>@foreach($row as [$t, $u, $d])<td class="col" width="50%" style="vertical-align:top;padding:0 {{ $loop->first ? '8px 14px 0' : '0 14px 8px' }};"><a href="{{ $base.$u }}" style="text-decoration:none;color:inherit;display:block;border:1px solid #e4e8f0;border-radius:12px;padding:14px 16px;"><b style="font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;color:#0b1a33;font-size:15px;">{{ $t }} &rarr;</b><br><span style="font-size:13.5px;color:#5c6879;line-height:1.5;">{{ $d }}</span></a></td>@endforeach</tr>@endforeach
  </table>
  @include('emails.parts.notice', ['kind' => 'info', 'title' => __('Need a shaft for a specific vehicle?'), 'html' => __('Reply to this e-mail with year, make, model and engine/transmission and a tech will come back with the exact part number. Or call :phone.', ['phone' => '<a href="tel:+18135246491" style="color:#1246ab;font-weight:600;">(813) 524-6491</a>'])])
@endsection
