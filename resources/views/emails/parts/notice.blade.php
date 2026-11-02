@php $kinds = ['info' => ['#eef3fc', '#b7cdf0', '#0b2455'], 'warn' => ['#fdf3e2', '#f1d9a5', '#7a5410'], 'ok' => ['#e7f5ec', '#b7e0c4', '#145c38'], 'err' => ['#fcecec', '#f2c4c4', '#8a1f1f']]; [$bg, $line, $ink] = $kinds[$kind ?? 'info']; @endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;"><tr><td style="background:{{ $bg }};border:1px solid {{ $line }};border-radius:10px;padding:12px 16px;font-size:14.5px;line-height:1.55;color:{{ $ink }};">
  @if(!empty($title))<b style="display:block;margin-bottom:2px;">{{ $title }}</b>@endif{!! $html ?? e($text ?? '') !!}
</td></tr></table>
