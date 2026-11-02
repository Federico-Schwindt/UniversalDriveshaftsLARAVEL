@php $base = rtrim(config('app.url'), '/'); @endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:6px 0 10px;border:1px solid #e4e8f0;border-radius:12px;">
  <tr><td style="padding:10px 14px;background:#f6f8fc;border-bottom:1px solid #e4e8f0;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#5c6879;font-weight:700;border-radius:12px 12px 0 0;">{{ __('Items') }}</td></tr>
  @foreach($items as $it)
  @php $meta = is_array($it->meta ?? null) ? $it->meta : (json_decode($it->meta ?? '{}', true) ?: []); $img = $it->image ?? ''; @endphp
  <tr><td style="padding:12px 14px;border-bottom:1px solid #eef1f7;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
      @if($img)<td width="56" style="vertical-align:top;padding-right:12px;"><img src="{{ str_starts_with($img,'/') ? $base.$img : $img }}" width="56" height="56" alt="" style="display:block;width:56px;height:56px;border:1px solid #e4e8f0;border-radius:8px;background:#fff;object-fit:contain;"></td>@endif
      <td style="vertical-align:top;font-size:14.5px;line-height:1.45;color:#141b28;">
        <b>{{ $it->name }}</b>@if($it->sku)<br><span style="font-family:Menlo,Consolas,monospace;font-size:12.5px;color:#5c6879;">{{ $it->sku }}</span>@endif
        @if(!empty($meta['summary']))<br><span style="font-size:13px;color:#1f9e88;">{{ $meta['summary'] }}</span>@endif
        @if(!empty($meta['config']) && !empty($showConfig))<div style="margin-top:6px;font-size:12.5px;color:#3a4557;">@foreach(\App\Support\BuildConfig::rows($meta['config']) as $rw)<span style="color:#8a94a6;">{{ $rw['label'] }}:</span> {{ $rw['value'] }}@if(!$loop->last) &middot; @endif @endforeach</div>@endif
      </td>
      <td align="right" style="vertical-align:top;white-space:nowrap;font-size:14.5px;color:#141b28;padding-left:12px;"><span style="color:#5c6879;">{{ $it->qty }} &times;</span> ${{ number_format($it->price, 2) }}<br><b>${{ number_format($it->line_total, 2) }}</b></td>
    </tr></table>
  </td></tr>
  @endforeach
</table>
