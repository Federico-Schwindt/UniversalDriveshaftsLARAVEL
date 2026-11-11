@extends('emails.recovery.layout')
@section('body')
  <p style="margin:0 0 6px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#c02a2a;font-weight:700;">New quote request</p>
  <h1 style="margin:0 0 14px;font-family:Poppins,Helvetica,Arial,sans-serif;font-size:22px;line-height:1.2;color:#0b1a33;">{{ $q['sku'] }}{{ $q['vehicle'] ? ' for ' . $q['vehicle'] : '' }}</h1>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:15px;line-height:1.7;color:#141b28;">
    <tr><td width="130" style="color:#5c6879;vertical-align:top;">Part number</td><td style="font-family:'IBM Plex Mono',Menlo,monospace;">{{ $q['sku'] }} <span style="color:#5c6879;">({{ $q['part_number'] }})</span></td></tr>
    @if($q['description'])<tr><td style="color:#5c6879;vertical-align:top;">Description</td><td>{{ $q['description'] }}</td></tr>@endif
    @if($q['fits'])<tr><td style="color:#5c6879;vertical-align:top;">Catalog fits</td><td>{{ $q['fits'] }}</td></tr>@endif
    <tr><td style="color:#5c6879;vertical-align:top;">Customer vehicle</td><td>{{ $q['vehicle'] ?: '—' }}</td></tr>
    <tr><td style="color:#5c6879;vertical-align:top;">E-mail</td><td><a href="mailto:{{ $q['email'] }}" style="color:#1246ab;">{{ $q['email'] }}</a></td></tr>
    <tr><td style="color:#5c6879;vertical-align:top;">Phone</td><td>{{ $q['phone'] ?: '—' }}</td></tr>
    @if($q['note'])<tr><td style="color:#5c6879;vertical-align:top;">Note</td><td>{{ $q['note'] }}</td></tr>@endif
    <tr><td style="color:#5c6879;vertical-align:top;">Received</td><td>{{ now()->format('D M j, g:i a') }} EST &middot; IP {{ $q['ip'] }}</td></tr>
  </table>
  <p style="margin:22px 0 0;font-size:14px;">Reply to this e-mail to answer the customer directly. @if($q['url'])<a href="{{ $q['url'] }}" style="color:#1246ab;">Part page</a> &middot; @endif<a href="{{ url('/admin/leads') }}" style="color:#1246ab;">All leads</a></p>
@endsection
@section('footer')
  Automatic notice from the website quote form.
@endsection
