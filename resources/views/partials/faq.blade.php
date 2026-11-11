@php
  /* Single source of truth: $faqs is [[question, answer], ...]. This partial
     renders the visible accordion AND the FAQPage schema from the same array,
     which is what Google requires for the rich result to be eligible. */
  $faqs = $faqs ?? [];
@endphp
@if(count($faqs))
  <div class="faq">
    @foreach($faqs as [$q, $a])
      <details><summary>{{ $q }}</summary><p>{{ $a }}</p></details>
    @endforeach
  </div>
  @push('schema')
  <script type="application/ld+json">{!! json_encode([
    '@@context' => 'https://schema.org',
    '@type'    => 'FAQPage',
    'mainEntity' => collect($faqs)->map(fn ($f) => [
        '@type' => 'Question', 'name' => $f[0],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
    ])->all(),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
  @endpush
@endif
