@extends('admin.layout')
@section('title','Monthly sales')
@section('crumb','Insights')
@section('sub')Net sales = products after coupons, less refunds, without shipping or tax — the same figure as WooCommerce Analytics. Eastern months; a refund counts in the month it was made. Includes the old website's history. @endsection
@section('actions')<a href="/admin/reports" class="btn btn--sm">{!! ic('chart') !!} Reports</a>@endsection
@use('App\Http\Controllers\Admin\AnalyticsController', 'A')
@php
  // ▲ 12% / ▼ 4%, or a dash when there is nothing to compare with
  $delta = function ($cur, $prev, $suffix = '') {
      $p = A::pct((float) $cur, $prev === null ? null : (float) $prev);
      if ($p === null) return '<span class="kpi__delta"><span class="muted">—</span></span>';
      return '<span class="kpi__delta an-delta ' . ($p >= 0 ? 'up' : 'down') . '"><span class="nowrap">' . ($p >= 0 ? '▲' : '▼') . ' ' . number_format(abs($p), 0) . '%</span>' . ($suffix ? '<span class="muted">' . e($suffix) . '</span>' : '') . '</span>';
  };
  $maxNet = max(array_map(fn ($x) => $x['cur']['net'], $rows) ?: [1]) ?: 1;
@endphp
@section('body')
<style>
  /* six money figures side by side: let the tiles share the row instead of clipping six-figure amounts */
  .an-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
  @media (min-width:900px){.an-kpis{grid-template-columns:repeat(3,minmax(0,1fr))}}
  @media (min-width:1600px){.an-kpis{grid-template-columns:repeat(6,minmax(0,1fr))}}
  .an-kpis .kpi__value{font-size:clamp(1.25rem,1.05rem + .7vw,1.6rem);white-space:nowrap}
  .an-delta{flex-wrap:wrap;column-gap:6px;row-gap:0}
  .an-month .prog{margin-top:5px}
</style>
<div class="chips">
  <a href="/admin/analytics" class="chip @if(!$year) is-on @endif">Last 12 months</a>
  @foreach($years as $y)<a href="/admin/analytics?year={{ $y }}" class="chip @if($year===$y) is-on @endif">{{ $y }}</a>@endforeach
</div>

<div class="kpis an-kpis mb">
  <div class="kpi"><span class="kpi__label" title="Net sales from the 1st to today, against the same days of last month">{{ $now->format('F') }} so far</span><span class="kpi__value">{{ money($kp['mtd']) }}</span>{!! $delta($kp['mtd'], $kp['mtd_prev'], 'vs first '.$kp['day'].' days of '.$now->copy()->subMonthNoOverflow()->format('M')) !!}</div>
  <div class="kpi"><span class="kpi__label" title="This month's pace carried to the end of the month">Heading for</span><span class="kpi__value">{{ $kp['projected'] !== null ? money($kp['projected']) : '—' }}</span>{!! $kp['projected'] !== null ? $delta($kp['projected'], $kp['last'], 'vs '.$now->copy()->subMonthNoOverflow()->format('M')) : '<span class="kpi__delta muted">too early to tell</span>' !!}</div>
  <div class="kpi"><span class="kpi__label">{{ $now->copy()->subMonthNoOverflow()->format('F') }}</span><span class="kpi__value">{{ money($kp['last']) }}</span>{!! $delta($kp['last'], $kp['last_ly'] ?: null, 'vs a year earlier') !!}</div>
  <div class="kpi"><span class="kpi__label" title="January 1 to today, against the same dates last year">{{ $now->year }} so far</span><span class="kpi__value">{{ money($kp['ytd']) }}</span>{!! $delta($kp['ytd'], $kp['ly_ytd'] ?: null, 'vs '.($now->year - 1).' to date') !!}</div>
  <div class="kpi"><span class="kpi__label">Avg. order this month</span><span class="kpi__value">{{ money($kp['aov']) }}</span>{!! $delta($kp['aov'], $kp['aov_last'] ?: null, 'vs last month') !!}</div>
  <div class="kpi"><span class="kpi__label">Customers this month</span><span class="kpi__value">{{ $kp['customers'] }}</span><span class="kpi__delta">{{ $kp['new'] }} first-time</span></div>
</div>

<div class="card mb">
  <div class="card__head"><h2>{{ $year ?: 'Last 12 months' }} against a year earlier</h2><div class="legend"><span><i style="background:var(--blue)"></i>{{ $year ?: 'Last 12 months' }}</span><span><i style="background:var(--blue-200)"></i>A year earlier</span></div></div>
  <div class="card__body"><svg class="chart" role="img" aria-label="Net sales per month against the same month a year earlier" data-chart='{{ json_encode($chart) }}'></svg></div>
  @if($kp['best'])<div class="card__foot small muted"><span>Best month on record: <b>{{ $kp['best']['label'] }}</b> · {{ money($kp['best']['net']) }}</span></div>@endif
</div>

<div class="card mb">
  <div class="card__head"><h2>Month by month</h2><span class="muted">newest first</span></div>
  <div class="tbl-wrap"><table class="tbl tbl--compact">
    <thead><tr><th>Month</th><th class="right">Net sales</th><th class="right">vs prior month</th><th class="right">vs last year</th><th class="right">Orders</th><th class="right">Avg. order</th><th class="right">Customers</th><th class="right">Refunds</th><th class="right" title="Shipping charged, less shipping refunded">Shipping</th><th class="right" title="Net sales + shipping + tax: what customers paid">Total sales</th><th class="right" title="Share of net sales from approved business accounts">B2B</th></tr></thead>
    <tbody>
    @foreach(array_reverse($rows) as $x)
      @continue($x['future'])
      @php $c = $x['cur']; @endphp
      <tr>
        <td class="nowrap"><b>{{ $x['label'] }}</b>@if($x['current']) <span class="badge badge--info badge--plain">so far</span>@endif</td>
        <td class="right num an-month" style="min-width:110px"><b>{{ money($c['net']) }}</b><div class="prog"><i style="width:{{ round($c['net'] / $maxNet * 100) }}%"></i></div></td>
        <td class="right">@if($x['current'])<span class="muted small">in progress</span>@else{!! $delta($c['net'], $x['prev']['net'] ?? null) !!}@endif</td>
        <td class="right">@if($x['current'])<span class="muted small">in progress</span>@else{!! $delta($c['net'], $x['ly']['net'] ?? null) !!}@endif</td>
        <td class="right num">{{ $c['orders'] }}</td>
        <td class="right num">{{ $c['orders'] ? money($c['net'] / $c['orders']) : '—' }}</td>
        <td class="right num">{{ $c['customers'] }}@if($c['new'])<div class="muted tiny">{{ $c['new'] }} new</div>@endif</td>
        <td class="right num">{{ $c['refunds'] > 0 ? money($c['refunds']) : '—' }}</td>
        <td class="right num">{{ money($c['shipping']) }}</td>
        <td class="right num">{{ money($c['net'] + $c['shipping'] + $c['tax']) }}</td>
        <td class="right num">{{ $c['net'] > 0 && $c['b2b'] > 0 ? round($c['b2b'] / $c['net'] * 100) . '%' : '—' }}</td>
      </tr>
    @endforeach
    </tbody>
    <tfoot><tr><th>{{ $year ?: '12 months' }}</th><th class="right num">{{ money($totals['net']) }}</th><th></th><th></th><th class="right num">{{ $totals['orders'] }}</th><th class="right num">{{ $totals['orders'] ? money($totals['net'] / $totals['orders']) : '—' }}</th><th></th><th class="right num">{{ money($totals['refunds']) }}</th><th class="right num">{{ money($totals['shipping']) }}</th><th class="right num">{{ money($totals['net'] + $totals['shipping'] + $totals['tax']) }}</th><th class="right num">{{ $totals['net'] > 0 ? round($totals['b2b'] / $totals['net'] * 100) . '%' : '—' }}</th></tr></tfoot>
  </table></div>
</div>

@if($channels->count())
<div class="card">
  <div class="card__head"><h2>Where the sales came from</h2><span class="muted">net sales per channel</span></div>
  <div class="tbl-wrap"><table class="tbl tbl--compact">
    <thead><tr><th>Month</th>@foreach($channels as $ch)<th class="right">{{ A::CHANNELS[$ch] ?? ucfirst($ch) }}</th>@endforeach</tr></thead>
    <tbody>
    @foreach(array_reverse($rows) as $x)
      @continue($x['future'])
      <tr><td class="nowrap">{{ $x['label'] }}</td>@foreach($channels as $ch)<td class="right num">{{ ($x['cur']['channels'][$ch] ?? 0) > 0 ? money($x['cur']['channels'][$ch]) : '—' }}</td>@endforeach</tr>
    @endforeach
    </tbody>
  </table></div>
  <div class="card__foot small muted">"Old website" is the order history imported from WooCommerce; "Website" is checkout on this site.</div>
</div>
@endif
@endsection
