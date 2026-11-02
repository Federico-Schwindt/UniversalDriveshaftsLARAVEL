@extends('emails.layout')
@section('body')
  @foreach(($paragraphs ?? []) as $p)<p style="margin:0 0 14px;">{!! is_array($p) ? $p['html'] : nl2br(e($p)) !!}</p>@endforeach
  @if(!empty($notice)) @include('emails.parts.notice', $notice) @endif
  @if(!empty($kv)) @include('emails.parts.kv', ['rows' => $kv, 'title' => $kvTitle ?? null, 'rows' => $kv]) @endif
  @if(!empty($button)) @include('emails.parts.button', ['label' => $button[0], 'url' => $button[1]]) @endif
  @if(!empty($html)){!! $html !!}@endif
  @if(!empty($pre))<pre style="margin:0 0 16px;padding:14px;background:#f6f8fc;border:1px solid #e4e8f0;border-radius:10px;font:13px/1.5 Menlo,Consolas,monospace;color:#141b28;white-space:pre-wrap;">{{ $pre }}</pre>@endif
  @foreach(($after ?? []) as $p)<p style="margin:0 0 14px;">{!! is_array($p) ? $p['html'] : nl2br(e($p)) !!}</p>@endforeach
  @if(!isset($signoff) || $signoff !== false)<p style="margin:18px 0 0;color:#5c6879;font-size:14px;">{{ $signoff ?? __('Questions? Reply to this e-mail or call (813) 524-6491.') }}</p>@endif
@endsection
