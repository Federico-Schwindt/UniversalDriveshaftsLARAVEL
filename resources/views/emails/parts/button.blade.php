@php $color = $color ?? '#1246ab'; @endphp
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:{{ $margin ?? '18px 0 22px' }};"><tr><td style="border-radius:999px;background:{{ $color }};">
  <a href="{{ $url }}" class="btn" style="display:inline-block;padding:14px 30px;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-weight:600;font-size:15.5px;color:#ffffff;text-decoration:none;border-radius:999px;background:{{ $color }};">{{ $label }}</a>
</td></tr></table>
