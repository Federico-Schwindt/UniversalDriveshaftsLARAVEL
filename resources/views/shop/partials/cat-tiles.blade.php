{{-- Category tiles (catalog, group pages, home): photo, name and part count. The category's description is in the
     tile but shows only on hover or keyboard focus (under a smaller copy of the photo), so the grid stays a picture list ("CATEGORY TILES" in uds-2026.css).
     $group = group slug, $subs = CatalogService::group()['subcategories'] --}}
<ul class="ctiles">
  @foreach($subs as $s)
    @php
      $name = \App\Support\PartGlossary::t($s['name']);
      $about = ($t = config("seo-copy.categories.{$s['slug']}.intro")) ? __((string) $t) : '';
    @endphp
    <li><a class="ctile" href="/shop/{{ $group }}/{{ $s['slug'] }}">
      <span class="ctile__img">@if($s['image'])<img src="{{ $s['image'] }}" alt="{{ $name }}" width="240" height="180" loading="lazy">@else<x-icon name="package"/>@endif</span>
      <span class="ctile__name">{{ $name }}</span>
      <span class="ctile__n">{{ __(':n parts', ['n' => number_format($s['count'])]) }}</span>
      @if($about !== '')<span class="ctile__about"><b aria-hidden="true">{{ $name }}</b><span>{{ \Illuminate\Support\Str::limit($about, 230) }}</span></span>@endif
    </a></li>
  @endforeach
</ul>
