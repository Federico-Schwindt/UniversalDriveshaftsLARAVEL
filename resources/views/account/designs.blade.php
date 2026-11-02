@extends('account.layout')
@section('active','designs')
@section('atitle','Driveshaft Designs')
@section('acct')
<div class="acct-h"><div><h2 class="t">{{ __('Your Driveshaft Designs') }}</h2><p>{{ __('Saved builds from the online builder and drawings from the Driveshaft Designer. Reopen any of them to keep configuring, print or order.') }}</p></div>
<a href="/online-builder" class="btn btn-primary" style="padding:10px 16px"><x-icon name="plus"/> {{ __('New build') }}</a> <a href="/driveshaft-designer" class="btn btn-ghost" style="padding:10px 16px"><x-icon name="ruler"/> {{ __('New drawing') }}</a></div>

<div class="apanel"><div class="apanel-b" style="padding-top:16px">
  @if($designs->count())
  <div class="mini">
    @foreach($designs as $d)@php $cfg = json_decode($d->config, true) ?: []; @endphp
      <div class="mini-row">
        <div class="mt"><b>{{ $d->title }}</b><span>{{ $cfg['summary'] ?? '' }} · {{ __('saved :date', ['date' => \Carbon\Carbon::parse($d->created_at)->isoFormat('ll')]) }} · {{ __('Ref') }} {{ $d->ref }}</span></div>
        @if(($cfg['kind'] ?? '') === 'designer')<span class="mprice" style="font-size:.8rem;color:var(--ink-faint)">{{ __('Drawing') }}</span>
        <a href="/account/designs/{{ $d->ref }}/load" class="btn btn-primary btn-sm">{{ __('Open drawing') }}</a>
        @else<span class="mprice">${{ number_format($d->price,2) }}</span>
        <a href="/account/designs/{{ $d->ref }}/load" class="btn btn-primary btn-sm">{{ __('Open in Builder') }}</a>@endif
        <form method="POST" action="/account/designs/{{ $d->ref }}/delete" onsubmit="return confirm({{ json_encode(__('Delete this design?')) }})" style="margin:0">@csrf<button class="mini-rm" type="submit">{{ __('Delete') }}</button></form>
      </div>
    @endforeach
  </div>
  @else
  <div class="aempty"><span class="ei"><x-icon name="sliders"/></span>{!! __('No saved designs yet. Build one in the designer and hit <b>Save Design</b>.') !!}<br><a href="/driveshaft-designer">{{ __('Open the Driveshaft Designer') }} <x-icon name="arrow-right"/></a></div>
  @endif
</div></div>
@endsection
