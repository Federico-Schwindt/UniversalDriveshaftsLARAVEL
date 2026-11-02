@extends('admin.layout')
@section('title','Assistant chats')
@section('crumb','Support')
@section('body')
<div class="chips">
  @foreach(['waiting' => 'Waiting for a person', 'open' => 'Open', 'all' => 'All'] as $k => $l)<a href="/admin/chats?f={{ $k }}" class="chip @if($f === $k) is-on @endif">{{ $l }}</a>@endforeach
</div>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Customer</th><th>Last message</th><th>Who answers</th><th class="center">Messages</th><th>Updated</th></tr></thead><tbody>
  @forelse($rows as $c)
    @php $m = $last[$c->id] ?? null; $waiting = $c->status === 'open' && ($c->needs_human_at || ($c->mode === 'human' && $m && $m->role === 'user')); @endphp
    <tr onclick="location.href='/admin/chats/{{ $c->id }}'" style="cursor:pointer">
      <td class="primary"><span class="title">{{ $c->customer ?: 'Customer #' . $c->user_id }}</span><small>{{ $c->customer_email }}</small>
        @if($waiting)<span class="badge badge--warn tiny">{{ $c->needs_human_at ? 'Asked for a person' : 'Waiting for your reply' }}</span>@endif</td>
      <td data-label="Last" class="small" style="max-width:420px">@if($m)<b>{{ $m->agent_id ? 'You: ' : ($m->role === 'user' ? 'Customer: ' : 'AI: ') }}</b>{{ \Illuminate\Support\Str::limit($m->display, 120) }}@endif @if(!empty($photos[$c->id]))<span class="badge badge--plain tiny">{{ $photos[$c->id] }} photo{{ $photos[$c->id] > 1 ? 's' : '' }}</span>@endif</td>
      <td data-label="Mode"><span class="badge {{ $c->mode === 'human' ? 'badge--info' : 'badge--plain' }}">{{ $c->status !== 'open' ? 'Closed' : ($c->mode === 'human' ? 'A person' : 'AI · ' . ucfirst($c->provider)) }}</span></td>
      <td class="center num" data-label="Messages">{{ $c->turns }}</td>
      <td data-label="Updated" class="small muted nowrap">{{ \Carbon\Carbon::parse($c->updated_at)->diffForHumans() }}</td>
    </tr>
  @empty<tr><td colspan="5"><div class="empty">{!! ic('inbox') !!}<b>No chats here</b></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>
@endsection
