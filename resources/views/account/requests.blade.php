@extends('account.layout')
@section('active','requests')
@section('atitle','Custom Requests')
@section('acct')
<div class="acct-h"><div><h2 class="t">{{ __('Your Custom Requests') }}</h2><p>{{ __('Custom driveshaft quote requests you\'ve submitted and where they stand.') }}</p></div>
<a href="/online-builder" class="btn btn-primary" style="padding:10px 16px"><x-icon name="plus"/> {{ __('New request') }}</a></div>

<div class="apanel"><div class="apanel-b" style="padding-top:16px">
  @if($requests->count())
  <div class="mini">
    @foreach($requests as $q)@php $data = json_decode($q->data, true) ?: []; @endphp
      <div class="mini-row" style="flex-wrap:wrap">
        <div class="mt" style="flex:1;min-width:220px"><b>{{ $data['vehicle_info'] ?? ($data['vehicle'] ?? __('Custom Driveshaft Request')) }}</b>
          <span>{{ __('Submitted :date', ['date' => \Carbon\Carbon::parse($q->created_at)->isoFormat('ll')]) }}@if(!empty($q->message)) · {{ \Illuminate\Support\Str::limit($q->message, 60) }}@endif</span></div>
        <span class="pill pill-{{ $q->status==='closed'?'completed':($q->status==='contacted'?'processing':'new') }}">{{ __(ucfirst($q->status)) }}</span>
      </div>
    @endforeach
  </div>
  @else
  <div class="aempty"><span class="ei"><x-icon name="mail"/></span>{{ __('No custom requests yet.') }}<br><a href="/online-builder">{{ __('Request a custom driveshaft') }} <x-icon name="arrow-right"/></a></div>
  @endif
</div></div>
@endsection
