@php $pct = $prev > 0 ? (($cur - $prev) / $prev) * 100 : null; @endphp
<span class="kpi__delta {{ $pct === null ? '' : ($pct >= 0 ? 'up' : 'down') }}">
  @if($pct === null)<span class="muted">no prior data</span>@else{{ $pct >= 0 ? '▲' : '▼' }} {{ number_format(abs($pct), 0) }}% <span class="muted">vs prev {{ $days }}d</span>@endif
</span>
