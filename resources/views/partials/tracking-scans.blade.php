{{-- Carrier scan history (EasyPost tracking_details), newest first. Times are the scan's local time, as the carrier shows them. --}}
@php
  $scans = json_decode((string) ($order->tracking_events ?? ''), true) ?: [];
  $label = ['pre_transit' => __('Label created'), 'in_transit' => __('On the way'), 'out_for_delivery' => __('Out for delivery'), 'delivered' => __('Delivered'),
    'available_for_pickup' => __('Ready for pickup at the carrier'), 'return_to_sender' => __('Returning to sender'), 'failure' => __('Delivery problem'), 'cancelled' => __('Cancelled')];
  $stage = ['pre_transit' => 0, 'in_transit' => 1, 'available_for_pickup' => 2, 'out_for_delivery' => 2, 'delivered' => 3];
  $latest = $scans[0]['status'] ?? null;
  $now = $order->delivered_at ? 3 : max(array_map(fn ($s) => $stage[$s['status']] ?? 0, $scans ?: [['status' => 'pre_transit']]));
  $trouble = in_array($latest, ['failure', 'return_to_sender'], true);
  $at = fn ($s) => $s['at'] ? \Carbon\Carbon::parse($s['at']) : null;
  $byDay = [];
  foreach ($scans as $i => $s) { $d = $at($s); $byDay[$d ? $d->format('Y-m-d') : '—'][] = $s + ['i' => $i, 'c' => $d]; }
  $est = !$order->delivered_at && !empty($order->tracking_est) ? \Carbon\Carbon::parse($order->tracking_est) : null;
  $shown = 5;
@endphp
@if($scans)
<div class="trk">
  <ol class="trk-bar {{ $trouble ? 'is-trouble' : '' }}" aria-label="{{ __('Shipment progress') }}">
    @foreach([__('Label created'), __('On the way'), __('Out for delivery'), __('Delivered')] as $k => $t)
      <li class="{{ $k < $now ? 'is-done' : ($k === $now ? ($k === 3 ? 'is-done' : 'is-now') : '') }}" @if($k === $now) aria-current="step" @endif><span>{{ $t }}</span></li>
    @endforeach
  </ol>
  @if($est)<p class="trk-est"><x-icon name="calendar"/> {{ __('Estimated delivery') }}: <b>{{ $est->isoFormat('dddd, ll') }}</b></p>@endif

  <h3 class="trk-h">{{ __('Tracking history') }} <small>{{ trans_choice(':n update|:n updates', count($scans), ['n' => count($scans)]) }}</small></h3>
  <div class="trk-list">
    @php $n = 0; $opened = false; @endphp
    @foreach($byDay as $day => $rows)
      @if($n >= $shown && !$opened)<details class="trk-more"><summary>{{ __('Show all :n updates', ['n' => count($scans)]) }}</summary>@php $opened = true; @endphp @endif
      <section class="trk-day">
        <h4>{{ $rows[0]['c'] ? $rows[0]['c']->isoFormat('dddd, ll') : '' }}</h4>
        <ul>
        @foreach($rows as $s)
          <li class="trk-ev trk-ev--{{ $s['status'] }} {{ $s['i'] === 0 ? 'is-latest' : '' }}">
            <time datetime="{{ $s['at'] }}">{{ $s['c'] ? $s['c']->isoFormat('LT') : '' }}</time>
            <div>
              <b>{{ $label[$s['status']] ?? ($s['message'] ?: __('Update')) }}</b>
              @if($s['message'] && $s['message'] !== ($label[$s['status']] ?? null))<span class="trk-msg">{{ $s['message'] }}</span>@endif
              @if($s['place'])<span class="trk-place"><x-icon name="pin"/>{{ $s['place'] }}</span>@endif
            </div>
          </li>
          @php $n++; @endphp
        @endforeach
        </ul>
      </section>
    @endforeach
    @if($opened)</details>@endif
  </div>
  @if($order->tracking_events_at)<p class="trk-upd">{{ __('Updated :ago from :carrier', ['ago' => \Carbon\Carbon::parse($order->tracking_events_at)->diffForHumans(), 'carrier' => $order->tracking_carrier ?: __('the carrier')]) }}</p>@endif
</div>
@endif
