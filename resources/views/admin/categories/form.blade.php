@extends('admin.layout')
@section('title', $c['term_id'] ? $c['name'] : 'New category')
@section('crumb')<a href="/admin/categories">Categories</a>@endsection
@section('actions')
  @if($c['term_id'] && $c['group'] && !$c['hidden'])<a href="/shop/{{ $c['group'] }}/{{ $c['slug'] }}" target="_blank" class="btn btn--sm">{!! ic('eye') !!} View on site</a>@endif
  @if($c['term_id'])<a href="/admin/products?cat={{ $c['slug'] }}" class="btn btn--sm">{!! ic('box') !!} {{ $c['count'] }} products</a>
  <a href="/admin/products/new?cat={{ $c['slug'] }}" class="btn btn--primary btn--sm">{!! ic('plus') !!} Add product</a>@endif
@endsection
@section('body')
<form method="post" action="{{ $c['term_id'] ? '/admin/categories/'.$c['slug'] : '/admin/categories' }}" data-savebar="#savebar" class="grid grid--main">@csrf
  <div class="stack">
    <div class="card"><div class="card__body form-grid">
      <div class="field"><label>Name</label><input class="input" name="name" value="{{ old('name', $c['name']) }}" required></div>
      <div class="field"><label>Slug</label><input class="input mono" name="slug" value="{{ old('slug', $c['slug']) }}" @if($c['term_id']) readonly @endif placeholder="auto"></div>
      <div class="field span-2"><label>Short blurb <span class="muted">(category cards)</span></label><input class="input" name="blurb" value="{{ old('blurb', $c['blurb']) }}" maxlength="400"></div>
      <div class="field span-2"><label>Description <span class="muted">(category page intro — good for SEO)</span></label><textarea class="input" name="description" rows="5">{{ old('description', $c['description']) }}</textarea></div>
      <div class="field"><label>SEO title</label><input class="input" name="seo_title" value="{{ old('seo_title', $c['seo_title']) }}"></div>
      <div class="field"><label>Meta description</label><input class="input" name="seo_desc" value="{{ old('seo_desc', $c['seo_desc']) }}"></div>
    </div></div>
    <div class="card"><div class="card__head"><h2>Image</h2></div>
      <div class="card__body stack" data-uploader data-url="/admin/media/upload" data-name="images[]">
        <div class="media-grid" data-grid>@if($c['image'])<div class="media-item"><img src="{{ media_url($c['image'],320) }}" alt=""><input type="hidden" name="images[]" value="{{ $c['image'] }}"><button type="button" class="rm">{!! ic('x','ic--s') !!}</button></div>@endif</div>
        <label class="dropzone">{!! ic('upload') !!}<div><b>Drop an image</b> or tap to choose</div><input type="file" accept="image/*"></label>
        <input type="hidden" name="image" value="{{ $c['image'] }}">
      </div></div>
    @isset($schema)
    <div class="card"><div class="card__head"><h2>Table columns &amp; filters <span class="muted">(automatic)</span></h2></div>
      <div class="card__body small">
        <p>The category page shows the attributes most of its products share. Columns right now: @foreach($schema['columns'] as $col)<span class="pill">{{ $col['label'] }}</span> @endforeach</p>
        <p>Filters: @forelse($schema['filters'] as $fl)<span class="pill">{{ $fl['label'] }} ({{ count($fl['options']) }})</span> @empty <span class="muted">none yet</span>@endforelse</p>
        <p class="muted">Fill the same attributes on at least 25% of the products in the category and they appear automatically.</p>
      </div></div>
    @endisset
  </div>
  <div class="stack sticky">
    <div class="card"><div class="card__head"><h2>Placement</h2></div><div class="card__body stack">
      <div class="field"><label>Storefront group</label><select class="select" name="group"><option value="">— not in navigation —</option>@foreach($groups as $gs=>$g)<option value="{{ $gs }}" @selected(old('group', $c['group'])===$gs)>{{ $g['label'] }}</option>@endforeach</select></div>
      <div class="field"><label>Parent category</label><select class="select" name="parent"><option value="0">None (top level)</option>@foreach(collect($all)->sortBy('name') as $o) @continue($o['term_id']===$c['term_id'])<option value="{{ $o['term_id'] }}" @selected((int)old('parent', $c['parent'])===$o['term_id'])>{{ $o['name'] }}</option>@endforeach</select></div>
      <div class="field"><label>Sort order</label><input class="input num" type="number" name="order" value="{{ old('order', $c['order']) }}"></div>
      <label class="switch"><input type="checkbox" name="hidden" value="1" @checked(old('hidden', $c['hidden']))><i></i> Hide from the storefront</label>
      <button class="btn btn--primary btn--block">{{ $c['term_id'] ? 'Save category' : 'Create category' }}</button>
      @if($c['term_id'] && $c['count']===0 && adm_can('catalog_write'))<a href="#" class="btn btn--danger btn--block" data-post="/admin/categories/{{ $c['slug'] }}/delete" data-confirm="Delete this empty category?">{!! ic('trash') !!} Delete</a>@endif
    </div></div>
  </div>
  <div class="savebar" id="savebar" style="grid-column:1/-1"><span>Unsaved changes</span><button type="submit" class="btn btn--primary btn--sm">Save</button></div>
</form>
@endsection
