{{-- one funnel event when the page has loaded: @include('partials.track', ['ev' => 'view_item', 'p' => [...]]) inside @push('scripts') --}}
<script>window.udsTrack && udsTrack(@json($ev), @json($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE));</script>
