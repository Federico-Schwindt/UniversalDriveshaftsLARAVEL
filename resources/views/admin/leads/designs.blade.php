@extends('admin.layout')
@section('title','Designer builds')
@section('crumb','Catalog')
@section('sub')Driveshafts customers configured in the online builder and drawings saved in the designer. @endsection
@section('body')
<form class="toolbar" method="get" action="/admin/designs"><div class="search">{!! ic('search') !!}<input class="input" type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Title, ref, customer…"></div><button class="btn btn--sm">Search</button></form>
<div class="card"><div class="tbl-wrap"><table class="tbl tbl--cards">
  <thead><tr><th>Design</th><th>Customer</th><th>Kind</th><th>Summary</th><th class="right">Price</th><th>Saved</th></tr></thead><tbody>
  @forelse($rows as $d)
  <tr><td class="primary"><span class="title">{{ $d->title ?: 'Untitled' }}</span><small class="mono">{{ $d->ref }}</small></td>
    <td data-label="Customer" class="small">@if($d->user_name)<a href="/admin/customers/{{ $d->user_id }}">{{ $d->user_name }}</a><br><span class="muted">{{ $d->user_email }}</span>@else<span class="muted">—</span>@endif</td>
    <td data-label="Kind"><span class="badge badge--plain">{{ $d->kind==='designer' ? 'Drawing' : 'Builder' }}</span></td>
    <td data-label="Summary" class="small muted">{{ \Illuminate\Support\Str::limit($d->kind==='designer' ? ($d->cfg['section'] ?? $d->cfg['sections'][0]['code'] ?? '') : \App\Support\BuildConfig::inline($d->cfg, 5), 90) }}</td>
    <td class="right num" data-label="Price">{{ $d->price ? money($d->price) : '—' }}</td>
    <td data-label="Saved" class="small muted">{{ \Carbon\Carbon::parse($d->created_at)->format('M j, Y') }}</td></tr>
  @empty<tr><td colspan="6"><div class="empty">{!! ic('ruler') !!}<b>No saved designs yet</b></div></td></tr>@endforelse
  </tbody></table></div>@include('admin.partials.pager')</div>
@endsection
