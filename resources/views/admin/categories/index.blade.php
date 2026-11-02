@extends('admin.layout')
@section('title','Categories')
@section('crumb','Catalog')
@section('sub')Storefront groups come from the catalog config; each category sits in one group (or stays hidden). @endsection
@section('actions')<a href="/admin/categories/new" class="btn btn--primary btn--sm">{!! ic('plus') !!} New category</a>@endsection
@section('body')
@php $inGroup = fn($g) => $cats->where('group',$g)->sortBy('name'); $assigned = $cats->whereNotNull('group')->pluck('slug'); @endphp
<div class="stack">
@foreach($groups as $gslug=>$g)
  <div class="card">
    <div class="card__head"><h2>{{ $g['label'] }} <span class="muted">· /shop/{{ $gslug }}</span></h2><span class="muted small">{{ $inGroup($gslug)->count() }} categories · {{ $inGroup($gslug)->sum('count') }} products</span></div>
    <div class="tbl-wrap"><table class="tbl tbl--cards tbl--compact">
      <thead><tr><th class="w-img"></th><th>Category</th><th>Slug</th><th class="right">Products</th><th>Visible</th></tr></thead>
      <tbody>
      @forelse($inGroup($gslug) as $c)
        <tr data-href="/admin/categories/{{ $c['slug'] }}"><td class="w-img">@if($c['image'])<img src="{{ media_url($c['image'],160) }}" class="thumb" alt="">@endif</td><td class="primary"><span class="title">{{ $c['name'] }}</span>@if($c['blurb'])<small>{{ \Illuminate\Support\Str::limit($c['blurb'],80) }}</small>@endif</td><td data-label="Slug" class="mono small">{{ $c['slug'] }}</td><td class="right num" data-label="Products">{{ $c['count'] }}</td><td class="status-cell">@if($c['hidden'])<span class="badge badge--warn">Hidden</span>@else<span class="badge badge--ok">Live</span>@endif</td></tr>
      @empty<tr><td colspan="5" class="muted" style="padding:14px">No categories in this group.</td></tr>@endforelse
      </tbody></table></div>
  </div>
@endforeach
  <div class="card">
    <div class="card__head"><h2>Not in any group</h2><span class="muted small">Exist in the catalog but are not shown in the shop navigation. Open one and pick a group to publish it.</span></div>
    <div class="tbl-wrap"><table class="tbl tbl--cards tbl--compact">
      <thead><tr><th>Category</th><th>Slug</th><th>Parent</th><th class="right">Products</th></tr></thead>
      <tbody>
      @foreach($cats->whereNull('group')->sortByDesc('count') as $c)
        <tr data-href="/admin/categories/{{ $c['slug'] }}"><td class="primary"><span class="title">{{ $c['name'] }}</span></td><td data-label="Slug" class="mono small">{{ $c['slug'] }}</td><td data-label="Parent" class="small">{{ $c['parent'] ? ($cats->firstWhere('term_id',$c['parent'])['name'] ?? '#'.$c['parent']) : '—' }}</td><td class="right num" data-label="Products">{{ $c['count'] }}</td></tr>
      @endforeach
      </tbody></table></div>
  </div>
</div>
@endsection
