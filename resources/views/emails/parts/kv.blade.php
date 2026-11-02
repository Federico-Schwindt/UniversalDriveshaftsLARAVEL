<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px;border:1px solid #e4e8f0;border-radius:12px;">
  @if(!empty($title))<tr><td colspan="2" style="padding:10px 14px;background:#f6f8fc;border-bottom:1px solid #e4e8f0;font-family:Poppins,'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:#5c6879;font-weight:700;">{{ __($title) }}</td></tr>@endif
  @foreach($rows as $k => $v) @continue($v === null || $v === '')
  <tr><td width="36%" style="padding:8px 14px;font-size:13.5px;color:#5c6879;border-bottom:1px solid #eef1f7;vertical-align:top;">{{ __((string) $k) }}</td><td style="padding:8px 14px;font-size:14px;color:#141b28;border-bottom:1px solid #eef1f7;vertical-align:top;white-space:pre-line;">{!! is_array($v) ? $v['html'] : e($v) !!}</td></tr>
  @endforeach
</table>
