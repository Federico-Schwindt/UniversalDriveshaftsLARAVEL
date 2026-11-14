@extends('admin.layout')
@section('title', ($lead->name ?: $lead->email))
@section('crumb')<a href="/admin/leads">Leads</a> › #{{ $lead->id }} · {{ $lead->source }}@endsection
@section('sub')<span class="badge badge--{{ $lead->status }}">{{ ucfirst($lead->status) }}</span> received {{ \Carbon\Carbon::parse($lead->created_at)->format('M j, Y g:ia') }}@endsection
@section('actions')
  @if($lead->status!=='closed')<a href="#" data-post="/admin/leads/{{ $lead->id }}" data-fields='{"status":"closed"}' class="btn btn--sm">{!! ic('check') !!} Close</a>@else<a href="#" data-post="/admin/leads/{{ $lead->id }}" data-fields='{"status":"contacted"}' class="btn btn--sm">Reopen</a>@endif
  <a href="/admin/orders/new?{{ $customer ? 'customer='.$customer->id : '' }}" class="btn btn--primary btn--sm">{!! ic('plus') !!} Create order</a>
  <a href="#" class="btn btn--sm btn--danger" data-post="/admin/leads/{{ $lead->id }}/delete" data-confirm="Delete this lead?">{!! ic('trash') !!}</a>
@endsection
@section('body')
<div class="grid grid--main">
  <div class="stack">
    <div class="card"><div class="card__head"><h2>Request</h2></div><div class="card__body">
      @if($lead->message)<div class="note" style="white-space:pre-line;margin-bottom:12px">{{ $lead->message }}</div>@endif
      <dl class="dl">@foreach(collect($lead->d)->except(['replies']) as $k=>$v) @continue(!is_scalar($v) && !is_array($v))<dt>{{ ucwords(str_replace('_',' ',$k)) }}</dt><dd>{{ is_array($v) ? implode(', ', array_map(fn($x)=>is_scalar($x)?$x:json_encode($x), $v)) : $v }}</dd>@endforeach</dl>
      @if($part)<div class="flash flash--info mt">{!! ic('shaft') !!}<div><b>U{{ $part->part_number }}</b> — {{ $part->description }} · {{ \App\Services\DriveshaftFinder::customerPrice($part) !== null ? money(\App\Services\DriveshaftFinder::customerPrice($part)) : 'Call for price' }} · build spec: {{ $part->spec->status ?? 'none' }} · <a href="/admin/driveshafts/{{ rawurlencode($part->part_number) }}">open part</a></div></div>@endif
    </div></div>
    <div class="card"><div class="card__head"><h2>Reply by e-mail</h2></div>
      <form method="post" action="/admin/leads/{{ $lead->id }}/reply" class="card__body stack">@csrf
        <div class="field"><label>To</label><input class="input" value="{{ $lead->email }}" readonly></div>
        <div class="field"><label>Subject</label><input class="input" name="subject" value="Re: your {{ $lead->source==='quote' ? 'quote request' : 'message' }} — Universal Driveshafts" required></div>
        <div class="field"><label>Message</label><textarea class="input" name="body" rows="7" required>Hi {{ explode(' ', $lead->name ?: 'there')[0] }},

Thanks for reaching out.

</textarea></div>
        <button class="btn btn--primary" style="align-self:flex-start">{!! ic('send') !!} Send reply</button>
      </form>
      @if(!empty($lead->d['replies']))<div class="card__foot" style="display:block">@foreach(array_reverse($lead->d['replies']) as $rp)<div class="note note--internal mb"><div class="note__meta"><span><b>{{ $rp['by'] }}</b> · {{ $rp['at'] }} · {{ $rp['subject'] }}</span></div>{!! nl2br(e($rp['body'])) !!}</div>@endforeach</div>@endif
    </div>
  </div>
  <div class="stack">
    <div class="card"><div class="card__head"><h2>Contact</h2></div><div class="card__body">
      <dl class="dl dl--tight"><dt>Name</dt><dd>{{ $lead->name ?: '—' }}</dd><dt>E-mail</dt><dd><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></dd><dt>Phone</dt><dd>@if($lead->phone)<a href="tel:{{ preg_replace('/[^0-9+]/','',$lead->phone) }}">{{ $lead->phone }}</a>@else—@endif</dd><dt>Account</dt><dd>@if($customer)<a href="/admin/customers/{{ $customer->id }}">{{ $customer->name }}</a>@else<span class="muted">none</span>@endif</dd><dt>IP</dt><dd class="mono muted">{{ $lead->ip }}</dd></dl>
      <form method="post" action="/admin/leads/{{ $lead->id }}" class="mt flex">@csrf<select class="select select--sm" name="status">@foreach(['new'=>'New','contacted'=>'Contacted','closed'=>'Closed','spam'=>'Spam'] as $k=>$l)<option value="{{ $k }}" @selected($lead->status===$k)>{{ $l }}</option>@endforeach</select><button class="btn btn--sm">Update</button></form>
    </div></div>
    @if($orders->count())<div class="card"><div class="card__head"><h2>Their orders</h2></div><div class="card__body card__body--flush">@foreach($orders as $o)<a href="/admin/orders/{{ $o->id }}" class="stat-row" style="padding:8px 16px;color:inherit;text-decoration:none"><span class="mono">{{ $o->order_number }}</span><span class="small">{{ money($o->total) }} <span class="badge badge--{{ $o->status }} tiny">{{ $o->status }}</span></span></a>@endforeach</div></div>@endif
    @if($others->count())<div class="card"><div class="card__head"><h2>Other requests</h2></div><div class="card__body card__body--flush">@foreach($others as $o)<a href="/admin/leads/{{ $o->id }}" class="stat-row" style="padding:8px 16px;color:inherit;text-decoration:none"><span><span class="badge badge--plain tiny">{{ $o->source }}</span> {{ \Illuminate\Support\Str::limit($o->message, 40) }}</span><span class="small muted">{{ \Carbon\Carbon::parse($o->created_at)->format('M j') }}</span></a>@endforeach</div></div>@endif
  </div>
</div>
@endsection
