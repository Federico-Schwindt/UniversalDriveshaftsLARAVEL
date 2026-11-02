@extends('admin.layout')
@section('title','Search')
@section('crumb','Admin')
@section('sub') @if($q !== '')Results for “{{ $q }}”@else Type above to search orders, products, driveshafts and customers. @endif @endsection
@section('body')
@php $total = array_sum(array_map(fn($g)=>count($g['items']), $groups)); @endphp
@if($q !== '' && !$total)
  <div class="card"><div class="empty">{!! ic('search') !!}<b>Nothing matched “{{ $q }}”</b>Try a part number without the dash (5-1310X → 51310X), an order number, an e-mail or a vehicle like “2015 Ford F-150”.</div></div>
@endif
<div class="stack">
@foreach($groups as $g)
  @continue(!count($g['items']))
  <div class="card">
    <div class="card__head"><h2>{!! ic($g['icon']) !!} {{ $g['label'] }} <span class="muted">({{ count($g['items']) }})</span></h2></div>
    <div class="card__body card__body--flush">
      @foreach($g['items'] as $it)
        <a class="adm-search__row" style="padding:10px 16px;border-bottom:1px solid var(--line-2);border-radius:0" href="{{ $it['url'] }}">
          @if(!empty($it['img']))<img src="{{ $it['img'] }}" alt="">@else{!! ic($g['icon']) !!}@endif
          <span><b>{{ $it['title'] }}</b>@if(!empty($it['sub'])) <span class="muted">{{ $it['sub'] }}</span>@endif</span>
          @if(!empty($it['meta']))<small>{{ $it['meta'] }}</small>@endif
        </a>
      @endforeach
    </div>
  </div>
@endforeach
</div>
@endsection
