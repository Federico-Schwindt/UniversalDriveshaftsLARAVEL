@extends('admin.layout')
@section('title','Leads & quotes')
@section('crumb','Support')
@section('body')
<div class="chips">@foreach(['open'=>'Open','new'=>'New','quotes'=>'Quote requests','closed'=>'Closed','all'=>'All','spam'=>'Spam'] as $k=>$l)<a href="/admin/leads?tab={{ $k }}" class="chip @if($tab===$k) is-on @endif">{{ $l }} <b>{{ $counts[$k] }}</b></a>@endforeach</div>
<form class="toolbar" method="get" action="/admin/leads"><input type="hidden" name="tab" value="{{ $tab }}"><div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Name, e-mail, phone, message…"></div><select class="select" name="source" data-autosubmit><option value="">All sources</option>@foreach($sources as $s)<option @selected(($f['source'] ?? '')===$s)>{{ $s }}</option>@endforeach</select><button class="btn btn--sm">Search</button></form>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Lead</th><th>Source</th><th>Message</th><th>Status</th><th>Received</th></tr></thead><tbody>
  @forelse($rows as $l)
  <tr data-href="/admin/leads/{{ $l->id }}">
    <td class="primary"><span class="title">{{ $l->name ?: $l->email }}</span><small>{{ $l->email }}@if($l->phone) · {{ $l->phone }}@endif</small></td>
    <td data-label="Source"><span class="badge badge--plain">{{ $l->source }}</span></td>
    <td data-label="Message" class="small">{{ \Illuminate\Support\Str::limit($l->message ?: collect($l->d)->except(['replies'])->map(fn($v,$k)=>is_scalar($v)?"$k: $v":null)->filter()->implode(' · '), 110) }}</td>
    <td class="status-cell"><span class="badge badge--{{ $l->status }}">{{ ucfirst($l->status) }}</span></td>
    <td data-label="Received" class="small muted nowrap">{{ \Carbon\Carbon::parse($l->created_at)->format('M j, g:ia') }}</td>
  </tr>
  @empty<tr><td colspan="5"><div class="empty">{!! ic('inbox') !!}<b>Inbox zero</b></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>
@endsection
