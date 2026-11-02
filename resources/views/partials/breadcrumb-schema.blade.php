@php
  /* $crumbs: array of [label, absolute url]. Emits a BreadcrumbList so Google
     can render the trail under the result instead of the bare URL. */
  $__bc = collect($crumbs ?? [])->values()->map(fn ($c, $i) => [
      '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1],
  ])->all();
@endphp
@if(count($__bc) > 1)
<script type="application/ld+json">{!! json_encode(['@@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$__bc], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endif
