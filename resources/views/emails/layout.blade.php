@php
  $base = rtrim(config('app.url'), '/');
  $abs = fn ($u) => $u && str_starts_with($u, '/') ? $base . $u : $u;
  $fontH = "Poppins,'Segoe UI',Helvetica,Arial,sans-serif"; $fontB = "'Hanken Grotesk','Segoe UI',Helvetica,Arial,sans-serif";
  $marketing = $marketing ?? false;
  $tl = fn ($s) => \App\Support\MailLocale::tr($s, app()->getLocale());
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" xmlns:v="urn:schemas-microsoft-com:vml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light">
<title>{{ $tl($title ?? 'Universal Driveshafts') }}</title>
<!--[if mso]><noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript><![endif]-->
<style>
  body{margin:0;padding:0;-webkit-text-size-adjust:100%;background:#eef2f8}
  table{border-collapse:collapse}
  img{border:0;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
  a{color:#1246ab}
  .btn:hover{background:#1657cf!important}
  @media only screen and (max-width:620px){
    .wrap{width:100%!important;border-radius:0!important}
    .pad{padding-left:20px!important;padding-right:20px!important}
    .h1{font-size:22px!important}
    .stack td.col{display:block!important;width:100%!important;padding:0 0 14px!important}
    .hide-m{display:none!important}
    .btn{display:block!important;text-align:center!important}
  }
</style>
</head>
<body style="margin:0;padding:0;background:#eef2f8;font-family:{{ $fontB }};color:#141b28;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;mso-hide:all;">{{ $tl($preheader ?? '') }}&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef2f8;">
<tr><td align="center" style="padding:26px 12px 34px;">
  <table role="presentation" class="wrap" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 6px 24px -12px rgba(11,26,51,.25);">
    {{-- header --}}
    <tr><td class="pad" style="padding:22px 32px 18px;background:#ffffff;border-bottom:4px solid #1246ab;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
        <td style="vertical-align:middle;"><a href="{{ $base }}" style="text-decoration:none;"><img src="{{ $base }}/img/email/logo.png" width="190" height="30" alt="Universal Driveshafts" style="display:block;width:190px;height:auto;"></a></td>
        <td align="right" class="hide-m" style="vertical-align:middle;font-family:{{ $fontB }};font-size:12.5px;color:#5c6879;line-height:1.4;">Florida<br><a href="tel:+18135246491" style="color:#1246ab;text-decoration:none;font-weight:600;">(813) 524-6491</a></td>
      </tr></table>
    </td></tr>
    @isset($hero)
    <tr><td class="pad" style="padding:30px 32px 6px;background:#ffffff;">
      @if(!empty($hero['kicker']))<p style="margin:0 0 8px;font-family:{{ $fontH }};font-size:11.5px;letter-spacing:.14em;text-transform:uppercase;color:#1246ab;font-weight:700;">{{ $tl($hero['kicker']) }}</p>@endif
      <h1 class="h1" style="margin:0 0 10px;font-family:{{ $fontH }};font-size:26px;line-height:1.2;color:#0b1a33;font-weight:700;letter-spacing:-.01em;">{{ $tl($hero['title']) }}</h1>
      @if(!empty($hero['text']))<p style="margin:0;font-size:16px;line-height:1.6;color:#3a4557;">{{ $tl($hero['text']) }}</p>@endif
    </td></tr>
    @endisset
    <tr><td class="pad" style="padding:22px 32px 10px;font-size:15.5px;line-height:1.6;color:#3a4557;">
      @yield('body')
    </td></tr>
    {{-- footer --}}
    <tr><td class="pad" style="padding:22px 32px 26px;background:#0b1a33;color:#aab7d1;font-size:12.5px;line-height:1.7;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
        <td style="vertical-align:top;">
          <p style="margin:0 0 6px;font-family:{{ $fontH }};font-weight:700;font-size:14px;color:#ffffff;">Universal Driveshafts</p>
          <p style="margin:0;">Florida, USA<br><a href="tel:+18135246491" style="color:#ffffff;text-decoration:none;">(813) 524-6491</a> &middot; <a href="mailto:{{ config('uds.email') }}" style="color:#ffffff;text-decoration:none;">{{ config('uds.email') }}</a></p>
          <p style="margin:10px 0 0;"><a href="{{ $base }}/track-order" style="color:#aab7d1;">{{ __('Track order') }}</a> &nbsp;·&nbsp; <a href="{{ $base }}/returns" style="color:#aab7d1;">{{ __('Returns') }}</a> &nbsp;·&nbsp; <a href="{{ $base }}/contact" style="color:#aab7d1;">{{ __('Contact') }}</a> &nbsp;·&nbsp; <a href="{{ $base }}/shop" style="color:#aab7d1;">{{ __('Shop') }}</a></p>
          @hasSection('footer')<p style="margin:10px 0 0;color:#7f91b4;">@yield('footer')</p>@endif
          @if($marketing)<p style="margin:10px 0 0;color:#7f91b4;">{{ __('You are receiving this because you signed up or bought from us.') }} <a href="{{ $unsubscribe ?? 'mailto:' . config('uds.email') . '?subject=Unsubscribe' }}" style="color:#aab7d1;">{{ __('Unsubscribe') }}</a><br>{{ config('uds.postal_address') }}</p>@endif
        </td>
      </tr></table>
    </td></tr>
  </table>
  <p style="margin:14px 0 0;font-size:11px;color:#8a94a6;">&copy; {{ date('Y') }} Universal Driveshafts LLC. {!! __('Spicer&reg;, Dana&reg; and other names are trademarks of their owners, used for identification only.') !!}</p>
</td></tr>
</table>
</body>
</html>
