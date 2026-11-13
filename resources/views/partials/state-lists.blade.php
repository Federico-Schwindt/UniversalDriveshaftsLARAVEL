{{-- state / province dropdowns for text fields marked data-state-list="<country select id>" (public/js/states.js) --}}
@once
<script>window.UDS_STATES = @json(['lists' => config('states'), 'pick' => ['US' => __('Select a state'), 'CA' => __('Select a province')]]);</script>
<script src="/js/states.js?v={{ @filemtime(public_path('js/states.js')) }}"></script>
@endonce
